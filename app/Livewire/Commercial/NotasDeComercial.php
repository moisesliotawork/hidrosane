<?php

namespace App\Livewire\Commercial;

use Livewire\Component;
use App\Models\Note;
use App\Models\User;
use App\Models\AnotacionVisita;
use Filament\Notifications\Notification;
use App\Filament\Commercial\Resources\NoteResource;
use App\Filament\Commercial\Resources\RetenResource;
use App\Enums\EstadoTerminal;
use App\Models\NoteSalaEvent;
use Illuminate\Support\Facades\DB;


class NotasDeComercial extends Component
{
    /** Puede ser ID numérico o la cadena 'reten' */
    public string|int $comercialId;

    /** Flag para saber si estamos en modo RETEN */
    public bool $esReten = false;

    /** Modal de reasignación */
    public bool $showReassignModal = false;
    public ?int $reassignNoteId = null;
    public ?int $newComercialId = null;

    /** IDs seleccionados (hoy + todas) */
    public array $selectedNotes = [];


    protected $listeners = [
        'notaActualizada' => '$refresh',
        'guardarUbicacion' => 'guardarUbicacion',
        'guardarUbicacionDentro' => 'guardarUbicacionDentro',
        'avisarSinDentro' => 'avisarSinDentro',
    ];

    public function mount(string|int $comercialId): void
    {
        $this->comercialId = $comercialId;
        $this->esReten = ($comercialId === 'reten');

        if ($this->esReten) {
            $this->selectedNotes = [];
        }
    }

    public function canAlwaysSeePhones(): bool
    {
        $user = auth()->user();
        return $user && $user->hasAnyRole(['team_leader', 'sales_manager']);
    }


    /** ====== Botón Reasignar ====== */
    public function openReassignModal(int $noteId): void
    {
        $this->reassignNoteId = $noteId;
        $this->newComercialId = null;
        $this->showReassignModal = true;
    }

    /** Reasignar SIN cambiar assignment_date */
    public function reassignVisit(): void
    {
        if (!$this->reassignNoteId || !$this->newComercialId) {
            Notification::make()->title('Selecciona un comercial')->warning()->send();
            return;
        }

        $note = Note::find($this->reassignNoteId);
        if (!$note) {
            Notification::make()->title('Nota no encontrada')->danger()->send();
            return;
        }

        // Datos del nuevo comercial (para el mensaje)
        $nuevo = User::find($this->newComercialId);
        $nombre = $nuevo ? trim(($nuevo->name ?? '') . ' ' . ($nuevo->last_name ?? '')) : 'Desconocido';
        $empleado = $nuevo->empleado_id ?? 'SIN-ID';

        $updateData = [
            'comercial_id' => $this->newComercialId,
            'assignment_date' => now()->startOfDay(), // o now() si quieres guardar hora exacta
        ];

        if ($this->esReten) {
            $updateData['reten'] = false;
        }

        $note->update($updateData);


        $extra = $this->esReten
            ? ' Se reasignó, se actualizó la fecha y salió de Retén (reten=false).'
            : ' Se reasignó y se actualizó la fecha.';

        AnotacionVisita::create([
            'nota_id' => $note->id,
            'author_id' => auth()->id(),
            'asunto' => 'REASIGNACIÓN',
            'cuerpo' => "Nota #{$note->nro_nota} reasignada al comercial {$nombre} - {$empleado}.{$extra}",
        ]);

        $this->showReassignModal = false;

        Notification::make()
            ->title("Nota #{$note->nro_nota} reasignada al comercial {$nombre} - {$empleado}")
            ->success()
            ->body('Se ha reasignado coreectamente la nota.')
            ->send();

        $this->dispatch('notaActualizada');
    }

    /** Opciones para el select del modal */
    public function getComercialesProperty(): array
    {
        return User::role(['commercial', 'team_leader', 'sales_manager'])
            ->orderBy('empleado_id')
            ->get()
            ->mapWithKeys(fn($u) => [
                $u->id => "{$u->empleado_id} {$u->name} {$u->last_name}",
            ])
            ->toArray();
    }

    public function avisarSinDentro($notaId): void
    {
        Notification::make()
            ->title('Sin ubicación en GPS')
            ->body("La nota #{$notaId} no tiene coordenadas de GPS guardadas.")
            ->danger()
            ->send();
    }

    public function guardarUbicacionDentro($notaId, $lat, $lng): void
    {
        $note = Note::find($notaId);
        if (!$note)
            return;

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

    public function guardarUbicacion($notaId, $lat, $lng): void
    {
        $note = Note::find($notaId);
        if (!$note)
            return;

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
    }

    public function toggleDeCamino($noteId): void
    {
        $note = Note::find($noteId);
        if (!$note) {
            Notification::make()->title('Nota no encontrada')->danger()->send();
            return;
        }

        $puede = auth()->user()?->hasRole('gerente') || $note->comercial_id === auth()->id();
        if (!$puede) {
            Notification::make()
                ->title('Acceso denegado')
                ->danger()
                ->body('No tienes permiso para modificar esta nota.')
                ->send();
            return;
        }

        $note->de_camino = !$note->de_camino;
        $note->save();

        AnotacionVisita::create([
            'nota_id' => $noteId,
            'author_id' => auth()->id(),
            'asunto' => 'DE CAMINO',
            'cuerpo' => $note->de_camino ? 'Va de camino' : 'No va de camino',
        ]);

        Notification::make()
                    ->title('Estado actualizado')
            ->{$note->de_camino ? 'success' : 'warning'}()
                ->body($note->de_camino ? 'La nota ha sido marcada como EN CAMINO' : 'La nota ha sido marcada como NO EN CAMINO')
                ->send();

        $this->dispatch('notaActualizada');
    }

    public function sendSelectedToReten(): void
    {
        $ids = array_values(array_filter($this->selectedNotes));

        if (empty($ids)) {
            Notification::make()
                ->title('No hay notas seleccionadas')
                ->warning()
                ->send();
            return;
        }

        // Solo mover a retén las que tengan comercial asignado
        $updated = Note::query()
            ->whereIn('id', $ids)
            ->whereNotNull('comercial_id')
            ->update([
                'reten' => true,
                // Si al enviar a retén quieres refrescar la fecha de asignación:
                'assignment_date' => now()->startOfDay(),
            ]);

        Notification::make()
            ->title('Enviadas a Retén')
            ->body("Se enviaron {$updated} notas a retén.")
            ->success()
            ->send();

        // Limpiar selección
        $this->selectedNotes = [];

        // refrescar
        $this->dispatch('notaActualizada');
    }

    public function sendSelectedToOfficeFromReten(): void
    {
        $ids = array_values(array_filter($this->selectedNotes));

        if (empty($ids)) {
            Notification::make()
                ->title('No hay notas seleccionadas')
                ->warning()
                ->send();
            return;
        }

        $allIds = collect($ids)->values()->all();

        // Elegibles: reten=true + sin venta + TN ∈ { null, '', 'ausente' }
        $eligible = Note::query()
            ->whereIn('id', $allIds)
            ->where('reten', true)
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
                ->body('Todas las seleccionadas tienen venta o su TN es NULO/CONFIRMADO/VENTA (o no están en retén).')
                ->warning()
                ->send();
            return;
        }

        DB::transaction(function () use ($eligible) {
            $now = now();
            $userId = auth()->id();

            // 1) Actualizar notas elegibles (SAL A + salir de retén)
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

            // 3) Evento después del commit (igual que tu bulk action)
            DB::afterCommit(function () use ($eligible) {
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

        // limpiar selección y refrescar
        $this->selectedNotes = [];
        $this->dispatch('notaActualizada');
    }

    public function redirigirAVenta(int $noteId)
    {
        $note = Note::select('id', 'reten')->find($noteId);

        if (!$note) {
            Notification::make()->title('Nota no encontrada')->danger()->send();
            return;
        }

        $url = ($note->reten)
            ? RetenResource::getUrl('edit', ['record' => $noteId], panel: 'comercial')
            : NoteResource::getUrl('edit', ['record' => $noteId], panel: 'comercial');

        return redirect()->to($url);
    }

    /** Notas de HOY */
    public function getNotesTodayProperty()
    {
        $query = Note::with(['customer', 'comercial'])
            ->whereDate('assignment_date', today())
            ->where(function ($q) {
                $q->whereNull('estado_terminal')
                    ->orWhere('estado_terminal', '')
                    ->orWhere('estado_terminal', 'ausente');
            })
            ->whereDoesntHave('venta');

        if ($this->esReten) {
            // 🔴 RETEN: no importa el comercial, solo reten = true
            $query->where('reten', true);
        } else {
            // 🧑‍💼 Comercial normal: mismas querys de antes + reten = false
            $query->where('comercial_id', $this->comercialId)
                ->where('reten', false);
            // si quieres incluir null también:
            // ->where(function ($q) {
            //     $q->whereNull('reten')->orWhere('reten', false);
            // });
        }

        return $query
            ->latest('assignment_date')
            ->get()
            ->map(fn($note) => $this->mapNote($note));
    }

    /** TODAS (excepto hoy) */
    public function getNotesAllProperty()
    {
        $query = Note::with(['customer', 'comercial'])
            ->whereDate('assignment_date', '<>', today())
            ->where(function ($q) {
                $q->whereNull('estado_terminal')
                    ->orWhere('estado_terminal', '')
                    ->orWhere('estado_terminal', 'ausente');
            })
            ->whereDoesntHave('venta')
            ->where('assignment_date', '>=', now()->subDays(5)->startOfDay());

        if ($this->esReten) {
            // 🔴 RETEN: todas las notas reten = true (con los mismos filtros de arriba)
            $query->where('reten', true);
        } else {
            // 🧑‍💼 Comercial normal: mismas querys de antes + reten = false
            $query->where('comercial_id', $this->comercialId)
                ->where('reten', false);
            // o versión con null permitido igual que antes:
            // ->where(function ($q) {
            //     $q->whereNull('reten')->orWhere('reten', false);
            // });
        }

        return $query
            ->latest('assignment_date')
            ->get()
            ->map(fn($note) => $this->mapNote($note));
    }

    private function mapNote(Note $note): array
    {
        $customer = $note->customer;

        // ========== MISMA LÓGICA DEL PDF ==========

        $primary = trim((string) ($customer->primary_address ?? ''));
        $nroPiso = trim((string) ($customer->nro_piso ?? ''));
        $postalCode = trim((string) ($customer->postal_code ?? ''));
        $city = trim((string) ($customer->ciudad ?? ''));
        $province = trim((string) ($customer->provincia ?? ''));
        $ayto = trim((string) ($customer->ayuntamiento ?? ''));

        $cpCity = trim(implode(' ', array_filter([$postalCode, $city])));

        // FIX letra huérfana tras CP (igual que en el PDF)
        $cpCity = preg_replace('/^(\d{4,5})\s+[A-ZÁÉÍÓÚÑ]\b\s+/u', '$1 ', $cpCity);

        $provinceFormatted = $province ? "($province)" : null;

        // Línea 1: solo dirección
        $dirL1 = $primary;

        // Línea 2: piso → CP+Ciudad → ayto
        $dirL2Parts = [];
        if ($nroPiso !== '') {
            $dirL2Parts[] = $nroPiso;
        }
        if ($cpCity !== '') {
            $dirL2Parts[] = $cpCity;
        }
        if ($ayto !== '') {
            $dirL2Parts[] = $ayto;
        }

        $dirL2 = implode(' - ', $dirL2Parts);

        if ($provinceFormatted) {
            $dirL2 = trim($dirL2 . ' ' . $provinceFormatted);
        }

        // TitleCase como en el PDF
        $toTitleCase = function (?string $text): string {
            $t = trim((string) $text);
            if ($t === '') {
                return '';
            }
            $t = mb_strtolower($t, 'UTF-8');
            return mb_convert_case($t, MB_CASE_TITLE, 'UTF-8');
        };

        $dirL1 = $toTitleCase($dirL1);
        $dirL2 = $toTitleCase($dirL2);

        // Dirección 1: una sola línea (igual que $dirOneLine en el PDF)
        $dirOneLine = trim(
            preg_replace(
                '/\s+/',
                ' ',
                trim($dirL1 . ($dirL2 ? ' - ' . $dirL2 : ''))
            ),
            ' -'
        );

        $fullAddress = $dirOneLine !== '' ? $dirOneLine : 'Sin dirección';

        $postalCodeSimple = $customer->postal_code ?? null;
        $citySimple = $customer->ciudad ?? null;
        $addressInfo = $postalCodeSimple && $citySimple
            ? "$postalCodeSimple, $citySimple"
            : ($postalCodeSimple ?? $citySimple ?? 'Sin ubicación');

        return [
            'id' => $note->id,
            'nro_nota' => $note->nro_nota,
            'customer' => $customer->name ?? 'Sin cliente',
            'full_address' => $fullAddress,
            'primary_address' => $customer->primary_address ?? 'Sin dirección',
            'address_info' => $addressInfo,
            'comercial' => $note->comercial->empleado_id ?? 'Sin asignar',
            'visit_date' => \Carbon\Carbon::parse($note->visit_date)->format('d/m/Y'),
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
    }

    public function render()
    {
        return view('livewire.commercial.notas-de-comercial');
    }
}
