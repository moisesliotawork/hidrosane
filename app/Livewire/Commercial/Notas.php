<?php

namespace App\Livewire\Commercial;

use Livewire\Component;
use App\Models\Note;
use App\Models\Team;
use App\Models\AnotacionVisita;
use Filament\Notifications\Notification;
use App\Filament\Commercial\Resources\NoteResource;
use Illuminate\Support\Str;
use App\Enums\EstadoTerminal;
use App\Models\NoteSalaEvent;
use App\Enums\NoteStatus;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class Notas extends Component
{
    public array $selectedNotes = [];
    public string $search = '';
    public ?string $statusFilter = null;


    protected $listeners = [
        'notaActualizada' => '$refresh',
        'guardarUbicacion' => 'guardarUbicacion',
        'guardarUbicacionDentro' => 'guardarUbicacionDentro',
        'avisarSinDentro' => 'avisarSinDentro',
    ];

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = null;
    }

    public function canAlwaysSeePhones(): bool
    {
        $user = auth()->user();
        return $user && $user->hasAnyRole(['team_leader', 'sales_manager']);
    }

    public function getTabsProperty(): array
    {
        $user = auth()->user();

        // Si no es jefe de equipo, no mostramos tabs
        if (!$user?->hasRole('team_leader')) {
            return [];
        }

        // rango hoy-5 a hoy
        $desde = now()->subDays(5)->toDateString();
        $hasta = now()->toDateString();

        // tab activo
        $active = request()->query('activeTab', 'todas');

        // IDs visibles: líder + miembros
        $visibleIds = [$user->id];
        $team = Team::with('members:id,empleado_id,name')
            ->where('team_leader_id', $user->id)
            ->first();

        if ($team) {
            $visibleIds = array_values(array_unique(array_merge(
                $visibleIds,
                $team->members->pluck('id')->all()
            )));
        }

        // filtro de estado_terminal (mismo que usas)
        $estadoFiltro = function ($q) {
            $q->whereNull('estado_terminal')
                ->orWhere('estado_terminal', '')
                ->orWhereRaw("LOWER(TRIM(estado_terminal)) = 'ausente'");
        };

        // helper para contar
        $countFor = function ($idsOrId) use ($desde, $hasta, $estadoFiltro) {
            $q = Note::query()
                ->whereDoesntHave('venta')
                ->whereNotNull('assignment_date')
                ->whereBetween(DB::raw('DATE(assignment_date)'), [$desde, $hasta])
                ->where('reten', false)
                ->where($estadoFiltro);

            if (is_array($idsOrId)) {
                $q->whereIn('comercial_id', $idsOrId);
            } else {
                $q->where('comercial_id', $idsOrId);
            }

            return $q->count();
        };

        $tabs = [];

        // TAB "Todas"
        $tabs[] = [
            'key' => 'todas',
            'label' => 'Todas',
            'badge' => $countFor($visibleIds),
            'icon' => 'list', // opcional
            'active' => $active === 'todas' || $active === null || $active === '',
        ];

        // tabs por comercial
        $comerciales = collect([$user]);
        if ($team) {
            $comerciales = $comerciales->merge($team->members);
        }

        foreach ($comerciales as $c) {
            $key = "com_{$c->id}";
            $label = trim(($c->empleado_id ?? '') . ' ' . ($c->name ?? ''));

            $tabs[] = [
                'key' => $key,
                'label' => $label !== '' ? $label : "Comercial #{$c->id}",
                'badge' => $countFor($c->id),
                'icon' => 'user', // opcional
                'active' => $active === $key,
            ];
        }

        return $tabs;
    }

    public function getStatusOptionsProperty(): array
    {
        // Si tu enum ya tiene options() como en Filament, úsalo:
        return NoteStatus::options();
    }

    public function getActiveFiltersCountProperty(): int
    {
        $count = 0;
        if (!empty(trim($this->search)))
            $count++;
        if (!empty($this->statusFilter))
            $count++;
        return $count;
    }

    /**
     * IDs de comerciales permitidos (según rol).
     */
    protected function allowedComercialIds(): array
    {
        $user = auth()->user();

        // sales_manager: sin filtro por comercial
        if ($user->hasRole('sales_manager')) {
            return [];
        }

        $ids = collect([$user->id]);

        if ($user->hasRole('team_leader')) {
            $team = Team::where('team_leader_id', $user->id)->first();

            if ($team) {
                $ids = $ids->merge(
                    $team->members()->pluck('users.id')
                )->unique();
            }
        }

        return $ids->values()->all();
    }

    /**
     * Chequeo de acceso a una nota para acciones.
     */
    protected function canAccessNote(Note $note): bool
    {
        $user = auth()->user();

        if ($user->hasRole('sales_manager')) {
            return true;
        }

        $allowed = $this->allowedComercialIds();

        return in_array((int) $note->comercial_id, array_map('intval', $allowed), true);
    }

    public function avisarSinDentro($notaId): void
    {
        Notification::make()
            ->title('Sin ubicación en GPS')
            ->body("La nota #{$notaId} no tiene coordenadas de GPS guardadas.")
            ->danger()
            ->send();
    }

    public function guardarUbicacion($notaId, $lat, $lng): void
    {
        $note = Note::find($notaId);
        if (!$note || !$this->canAccessNote($note)) {
            Notification::make()->title('Acceso denegado')->danger()->send();
            return;
        }

        $note->lat = $lat;
        $note->lng = $lng;
        $note->save();

        AnotacionVisita::create([
            'nota_id' => $notaId,
            'author_id' => auth()->id(),
            'asunto' => 'GPS',
            'cuerpo' => "Ubicación capturada: Latitud $lat, Longitud $lng",
        ]);

        Notification::make()
            ->title('Ubicación capturada')
            ->success()
            ->body("Ubicación guardada para la nota #$notaId: [$lat, $lng]")
            ->send();

        $this->dispatch('notaActualizada');
    }

    public function guardarUbicacionDentro($notaId, $lat, $lng): void
    {
        $note = Note::find($notaId);
        if (!$note || !$this->canAccessNote($note)) {
            Notification::make()->title('Acceso denegado')->danger()->send();
            return;
        }

        $note->lat_dentro = $lat;
        $note->lng_dentro = $lng;
        $note->save();

        AnotacionVisita::create([
            'nota_id' => $notaId,
            'author_id' => auth()->id(),
            'asunto' => 'DENTRO',
            'cuerpo' => "Ubicación DENTRO: Latitud $lat, Longitud $lng",
        ]);

        Notification::make()
            ->title('Ubicación DENTRO capturada')
            ->success()
            ->body("Guardada para nota #$notaId: [$lat, $lng]")
            ->send();

        $this->dispatch('notaActualizada');
    }

    public function toggleDeCamino($noteId): void
    {
        $note = Note::find($noteId);

        if (!$note || !$this->canAccessNote($note)) {
            Notification::make()
                ->title('Acceso denegado')
                ->danger()
                ->body('No tienes permiso para modificar esta nota.')
                ->send();
            return;
        }

        $nuevoEstado = !$note->de_camino;
        $note->de_camino = $nuevoEstado;
        $note->save();

        AnotacionVisita::create([
            'nota_id' => $noteId,
            'author_id' => auth()->id(),
            'asunto' => 'DE CAMINO',
            'cuerpo' => $nuevoEstado ? "Va de camino" : "No va de camino",
        ]);

        Notification::make()
                    ->title('Estado actualizado')
                    ->body($nuevoEstado ? 'La nota ha sido marcada como EN CAMINO' : 'La nota ha sido marcada como NO EN CAMINO')
            ->{$nuevoEstado ? 'success' : 'warning'}()
                ->send();

        $this->dispatch('notaActualizada');
    }

    public function redirigirAVenta(int $noteId)
    {
        $note = Note::find($noteId);

        if (!$note || !$this->canAccessNote($note)) {
            Notification::make()->title('Acceso denegado')->danger()->send();
            return;
        }

        $url = NoteResource::getUrl(
            'edit',
            ['record' => $noteId],
            panel: 'comercial'
        );

        return redirect()->to($url);
    }

    protected function getSelectedNoteIds(): array
    {
        return collect($this->selectedNotes)
            ->flatten()
            ->filter(fn($id) => is_numeric($id))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }


    /**
     * ✅ Masivo: Enviar a Retén (igual que bulkAction del Resource)
     */
    public function bulkEnviarAReten(): void
    {
        $ids = $this->getSelectedNoteIds();

        if (empty($ids)) {
            Notification::make()
                ->title('No hay notas seleccionadas')
                ->warning()
                ->send();
            return;
        }

        // Seguridad: solo TL / sales_manager (igual que bulkAction)
        if (!auth()->user()?->hasAnyRole(['team_leader', 'sales_manager'])) {
            Notification::make()->title('Acceso denegado')->danger()->send();
            return;
        }

        $ids = $this->getSelectedNoteIds();

        $allowed = Note::query()
            ->whereIn('id', $ids)
            ->get()
            ->filter(fn(Note $note) => $this->canAccessNote($note))
            ->pluck('id')
            ->values()
            ->all();


        if (empty($allowed)) {
            Notification::make()
                ->title('No hay notas válidas')
                ->warning()
                ->send();
            return;
        }

        $updated = Note::query()
            ->whereIn('id', $allowed)
            ->update(['reten' => true]);

        Notification::make()
            ->title('Notas enviadas a Retén')
            ->body("Cantidad: {$updated}")
            ->success()
            ->send();

        // limpiar selección + refrescar
        $this->selectedNotes = [];
        $this->dispatch('notaActualizada');
    }

    /**
     * ✅ Masivo: Enviar a Oficina (SALA) (igual que bulkAction del Resource)
     */
    public function bulkEnviarASala(): void
    {
        $allIds = $this->getSelectedNoteIds();

        if (empty($allIds)) {
            Notification::make()
                ->title('No hay notas seleccionadas')
                ->warning()
                ->send();
            return;
        }

        $ids = $this->getSelectedNoteIds();

        $allowed = Note::query()
            ->whereIn('id', $ids)
            ->get()
            ->filter(fn(Note $note) => $this->canAccessNote($note))
            ->pluck('id')
            ->values()
            ->all();


        if (empty($allIds)) {
            Notification::make()
                ->title('No hay notas válidas')
                ->warning()
                ->send();
            return;
        }

        // Elegibles: sin venta y TN ∈ { null, '', 'ausente' }
        $eligible = Note::query()
            ->whereIn('id', $allIds)
            ->whereDoesntHave('venta')
            ->where(function ($q) {
                $q->whereNull('estado_terminal')
                    ->orWhere('estado_terminal', '')
                    ->orWhereRaw("LOWER(TRIM(estado_terminal)) = 'ausente'");
            })
            ->pluck('id')
            ->all();

        $skipped = count($allIds) - count($eligible);

        if (empty($eligible)) {
            Notification::make()
                ->title('No hay notas válidas para enviar a Oficina')
                ->body('Todas las seleccionadas tienen venta o su TN es NULO/CONFIRMADO/VENTA.')
                ->warning()
                ->send();
            return;
        }

        \DB::transaction(function () use ($eligible) {
            $now = now();
            $userId = auth()->id();

            // 1) Actualizar todas las notas elegibles
            Note::whereIn('id', $eligible)->update([
                'estado_terminal' => EstadoTerminal::SALA->value,
                'printed' => false,
                'reten' => false,
                'sent_to_sala_at' => $now,
                'fecha_declaracion' => $now,
            ]);

            // 2) Historial masivo
            $rows = [];
            foreach ($eligible as $noteId) {
                $rows[] = [
                    'note_id' => $noteId,
                    'sent_by_user_id' => $userId,
                    'via' => 'masivo',
                    'sent_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($rows)) {
                NoteSalaEvent::insert($rows);
            }

            // 3) Evento afterCommit (igual)
            \DB::afterCommit(function () use ($eligible) {
                $comercial = auth()->user();

                event(new \App\Events\NotasEnviadasAOficinaBulk(
                    $eligible,
                    $comercial
                ));
            });
        });

        Notification::make()
            ->title('Notas enviadas a Oficina')
            ->body('Actualizadas: ' . count($eligible) . ($skipped ? ' • Omitidas: ' . $skipped : ''))
            ->success()
            ->send();

        $this->selectedNotes = [];
        $this->dispatch('notaActualizada');
    }


    /**
     * ✅ ESTE es el query “de arriba” pero en Livewire.
     */
    public function getNotesProperty()
    {
        $user = auth()->user();

        $query = Note::query()->with(['customer', 'comercial', 'user']);

        // 1) Filtro por comercial (commercial / team_leader)
        if (!$user->hasRole('sales_manager')) {
            $ids = $this->allowedComercialIds();
            $query->whereIn('comercial_id', $ids);

            $active = request()->query('activeTab', '');
            if (Str::startsWith($active, 'com_')) {
                $comId = (int) Str::after($active, 'com_');
                if ($comId > 0) {
                    $query->where('comercial_id', $comId);
                }
            }
        } else {
            // sales_manager también puede filtrar por activeTab
            $active = request()->query('activeTab', '');
            if (Str::startsWith($active, 'com_')) {
                $comId = (int) Str::after($active, 'com_');
                if ($comId > 0) {
                    $query->where('comercial_id', $comId);
                }
            }
        }

        // 2) Estado terminal: null, '', o AUSENTE (case/trim)
        $query->where(function ($q) {
            $q->whereNull('estado_terminal')
                ->orWhere('estado_terminal', '')
                ->orWhereRaw("LOWER(TRIM(estado_terminal)) = 'ausente'");
        })->whereDoesntHave('venta');

        // 3) Rango de fecha: hoy-5 hasta hoy (INCLUSIVO)
        $desde = now()->subDays(5)->toDateString();
        $hasta = now()->toDateString();
        $query->whereBetween(\DB::raw('DATE(assignment_date)'), [$desde, $hasta]);

        // 4) Sin reten
        $query->where('reten', false);

        // 5) ✅ BÚSQUEDA (reactiva)
        $term = trim((string) $this->search);

        if ($term !== '') {
            $term = preg_replace('/\s+/', ' ', $term);

            $query->where(function ($q) use ($term) {

                // Nota: nro_nota (búsqueda parcial)
                $q->where('nro_nota', 'like', "%{$term}%");

                // Customer (tu BD usa first_names / last_names)
                $q->orWhereHas('customer', function ($qc) use ($term) {
                    $qc->where(function ($w) use ($term) {
                        $w->where('first_names', 'like', "%{$term}%")
                            ->orWhere('last_names', 'like', "%{$term}%")
                            ->orWhereRaw(
                                "CONCAT(COALESCE(first_names,''),' ',COALESCE(last_names,'')) LIKE ?",
                                ["%{$term}%"]
                            )
                            ->orWhere('dni', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%")
                            ->orWhere('secondary_phone', 'like', "%{$term}%")
                            ->orWhere('primary_address', 'like', "%{$term}%")
                            ->orWhere('postal_code', 'like', "%{$term}%")
                            ->orWhere('ciudad', 'like', "%{$term}%");
                    });
                });

                // Teleoperadora (user): empleado_id
                $q->orWhereHas('user', function ($qu) use ($term) {
                    $qu->where('empleado_id', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhereRaw("CONCAT(COALESCE(name,''),' ',COALESCE(last_name,'')) LIKE ?", ["%{$term}%"]);
                });
            });
        }

        if (!empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }


        return $query
            ->latest('assignment_date')
            ->get()
            ->map(function ($note) {
                $customer = $note->customer;

                // ==== misma lógica de dirección formateada ====
                $primary = trim((string) ($customer->primary_address ?? ''));
                $nroPiso = trim((string) ($customer->nro_piso ?? ''));
                $postalCode = trim((string) ($customer->postal_code ?? ''));
                $city = trim((string) ($customer->ciudad ?? ''));
                $province = trim((string) ($customer->provincia ?? ''));
                $ayto = trim((string) ($customer->ayuntamiento ?? ''));

                $cpCity = trim(implode(' ', array_filter([$postalCode, $city])));
                $cpCity = preg_replace('/^(\d{4,5})\s+[A-ZÁÉÍÓÚÑ]\b\s+/u', '$1 ', $cpCity);

                $provinceFormatted = $province ? "($province)" : null;

                $dirL1 = $primary;

                $dirL2Parts = [];
                if ($nroPiso !== '')
                    $dirL2Parts[] = $nroPiso;
                if ($cpCity !== '')
                    $dirL2Parts[] = $cpCity;
                if ($ayto !== '')
                    $dirL2Parts[] = $ayto;

                $dirL2 = implode(' - ', $dirL2Parts);
                if ($provinceFormatted)
                    $dirL2 = trim($dirL2 . ' ' . $provinceFormatted);

                $toTitleCase = function (?string $text): string {
                    $t = trim((string) $text);
                    if ($t === '')
                        return '';
                    $t = mb_strtolower($t, 'UTF-8');
                    return mb_convert_case($t, MB_CASE_TITLE, 'UTF-8');
                };

                $dirL1 = $toTitleCase($dirL1);
                $dirL2 = $toTitleCase($dirL2);

                $dirOneLine = trim(
                    preg_replace('/\s+/', ' ', trim($dirL1 . ($dirL2 ? ' - ' . $dirL2 : ''))),
                    ' -'
                );

                $fullAddress = $dirOneLine !== '' ? $dirOneLine : 'Sin dirección';

                $postalCodeSimple = $customer->postal_code ?? null;
                $citySimple = $customer->ciudad ?? null;
                $addressInfo = $postalCodeSimple && $citySimple
                    ? "$postalCodeSimple, $citySimple"
                    : ($postalCodeSimple ?? $citySimple ?? 'Sin ubicación');
                // ============================================
    
                return [
                    'id' => $note->id,
                    'nro_nota' => $note->nro_nota,
                    'customer' => $customer->name ?? 'Sin cliente',
                    'full_address' => $fullAddress,
                    'primary_address' => $customer->primary_address ?? 'Sin dirección',
                    'address_info' => $addressInfo,
                    'comercial' => $note->comercial->empleado_id ?? 'Sin asignar',
                    'visit_date' => $note->visit_date ? \Carbon\Carbon::parse($note->visit_date)->format('d/m/Y') : '--/--/----',
                    'visit_schedule' => $note->visit_schedule ?? '--:--',
                    'observations' => $note->observations,
                    'fuente' => $note->fuente->value,
                    'fuente_label' => $note->fuente->getLabel(),
                    'fuente_puntaje' => $note->fuente->getPuntaje(),
                    'de_camino' => $note->de_camino,
                    'show_phone' => $note->show_phone,
                    'phone' => $customer->phone ?? null,
                    'secondary_phone' => $customer->secondary_phone ?? null,
                    'lat_dentro' => $note->lat_dentro,
                    'lng' => $note->lng,
                    'lat' => $note->lat,
                    'lng_dentro' => $note->lng_dentro,
                ];
            });
    }

    public function render()
    {
        return view('livewire.commercial.notas');
    }
}
