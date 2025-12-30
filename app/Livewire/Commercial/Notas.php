<?php

namespace App\Livewire\Commercial;

use Livewire\Component;
use App\Models\Note;
use App\Models\Team;
use App\Models\AnotacionVisita;
use Filament\Notifications\Notification;
use App\Filament\Commercial\Resources\NoteResource;
use Illuminate\Support\Str;

class Notas extends Component
{
    public array $selectedNotes = [];

    protected $listeners = [
        'notaActualizada' => '$refresh',
        'guardarUbicacion' => 'guardarUbicacion',
        'guardarUbicacionDentro' => 'guardarUbicacionDentro',
        'avisarSinDentro' => 'avisarSinDentro',
    ];

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

    /**
     * ✅ ESTE es el query “de arriba” pero en Livewire.
     */
    public function getNotesProperty()
    {
        $user = auth()->user();

        $query = Note::query()->with(['customer', 'comercial']);

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
