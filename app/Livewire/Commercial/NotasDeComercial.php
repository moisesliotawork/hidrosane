<?php

namespace App\Livewire\Commercial;

use Livewire\Component;
use App\Models\Note;
use App\Models\User;
use App\Models\AnotacionVisita;
use App\Models\NoteReassignmentBatch;
use App\Models\NoteReassignmentLog;
use Filament\Notifications\Notification;
use App\Filament\Commercial\Resources\NoteResource;
use App\Filament\Commercial\Resources\RetenResource;
use App\Enums\EstadoTerminal;
use App\Models\NoteSalaEvent;
use Illuminate\Support\Facades\DB;
use App\Support\NoteRouteGps;
use App\Support\ActionGps;
use App\Support\NoteSalaActions;
use App\Livewire\Concerns\ValidatesLivewireGps;


class NotasDeComercial extends Component
{
    use ValidatesLivewireGps;
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

    public bool $showBulkReassignModal = false;
    public ?string $bulkNewComercialId = null;

    protected $listeners = [
        'notaActualizada' => '$refresh',
        'guardarUbicacion' => 'guardarUbicacion',
        'guardarUbicacionDentro' => 'guardarUbicacionDentro',
        'avisarSinDentro' => 'avisarSinDentro',
        'toggleDeCamino' => 'toggleDeCamino',
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

        $fromComercialId = $note->comercial_id;

        $updateData = [
            'comercial_id' => $this->newComercialId,
            'assignment_date' => now()->startOfDay(),
            'reten' => false,
        ];

        $note->update($updateData);

        // Log de reasignación
        $batch = NoteReassignmentBatch::create([
            'author_id'       => auth()->id(),
            'to_comercial_id' => $this->newComercialId,
            'to_reten'        => false,
            'reassigned_at'   => now(),
        ]);
        NoteReassignmentLog::create([
            'batch_id'          => $batch->id,
            'note_id'           => $note->id,
            'from_comercial_id' => $fromComercialId,
        ]);

        $extra = $this->esReten
            ? ' Se reasignó, se actualizó la fecha y salió de Retén.'
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
        return User::query()
            ->role(['commercial', 'team_leader', 'sales_manager'])
            ->whereNull('baja')   // ✅ solo activos
            ->orderBy('empleado_id')
            ->get()
            ->mapWithKeys(fn($u) => [
                $u->id => trim("{$u->empleado_id} {$u->name} {$u->last_name}"),
            ])
            ->toArray();
    }

    public function selectAll(): void
    {
        $this->selectedNotes = collect($this->notesToday)
            ->pluck('id')
            ->merge(collect($this->notesAll)->pluck('id'))
            ->unique()
            ->map(fn($id) => (string) $id)
            ->values()
            ->toArray();
    }

    public function deselectAll(): void
    {
        $this->selectedNotes = [];
    }

    public function openBulkReassignModal(): void
    {
        $this->bulkNewComercialId = null;
        $this->showBulkReassignModal = true;
    }

    public function reassignBulkVisit(): void
    {
        if (empty($this->selectedNotes) || !$this->bulkNewComercialId) {
            Notification::make()->title('Selecciona notas y un destino')->warning()->send();
            return;
        }

        $notes = Note::whereIn('id', $this->selectedNotes)->get();
        $count = 0;

        // Capturamos from_comercial_id antes de actualizar
        $fromComercials = $notes->pluck('comercial_id', 'id');

        if ($this->bulkNewComercialId === 'reten') {
            $batch = NoteReassignmentBatch::create([
                'author_id'       => auth()->id(),
                'to_comercial_id' => null,
                'to_reten'        => true,
                'reassigned_at'   => now(),
            ]);

            foreach ($notes as $note) {
                $note->update([
                    'reten' => true,
                    'assignment_date' => now()->startOfDay(),
                ]);
                AnotacionVisita::create([
                    'nota_id' => $note->id,
                    'author_id' => auth()->id(),
                    'asunto' => 'REASIGNACIÓN MASIVA',
                    'cuerpo' => "Nota #{$note->nro_nota} enviada a Retén de forma masiva.",
                ]);
                NoteReassignmentLog::create([
                    'batch_id'          => $batch->id,
                    'note_id'           => $note->id,
                    'from_comercial_id' => $fromComercials[$note->id] ?? null,
                ]);
                $count++;
            }
            $destino = 'Retén';
        } else {
            $nuevo = User::find((int) $this->bulkNewComercialId);
            $nombre = $nuevo ? trim(($nuevo->name ?? '') . ' ' . ($nuevo->last_name ?? '')) : 'Desconocido';
            $empleado = $nuevo->empleado_id ?? 'SIN-ID';

            $batch = NoteReassignmentBatch::create([
                'author_id'       => auth()->id(),
                'to_comercial_id' => (int) $this->bulkNewComercialId,
                'to_reten'        => false,
                'reassigned_at'   => now(),
            ]);

            foreach ($notes as $note) {
                $note->update([
                    'comercial_id' => (int) $this->bulkNewComercialId,
                    'assignment_date' => now()->startOfDay(),
                    'reten' => false,
                ]);
                AnotacionVisita::create([
                    'nota_id' => $note->id,
                    'author_id' => auth()->id(),
                    'asunto' => 'REASIGNACIÓN MASIVA',
                    'cuerpo' => "Nota #{$note->nro_nota} reasignada masivamente al comercial {$nombre} - {$empleado}.",
                ]);
                NoteReassignmentLog::create([
                    'batch_id'          => $batch->id,
                    'note_id'           => $note->id,
                    'from_comercial_id' => $fromComercials[$note->id] ?? null,
                ]);
                $count++;
            }
            $destino = "{$nombre} - {$empleado}";
        }

        $this->selectedNotes = [];
        $this->bulkNewComercialId = null;
        $this->showBulkReassignModal = false;

        Notification::make()
            ->title('Reasignación masiva completada')
            ->body("{$count} notas reasignadas a: {$destino}")
            ->success()
            ->send();

        $this->dispatch('notaActualizada');
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
        $coords = $this->validatedGpsOrNotify($lat, $lng);

        if ($coords === null) {
            return;
        }

        $note = Note::find($notaId);
        if (!$note)
            return;

        $note->lat_dentro = $coords['lat'];
        $note->lng_dentro = $coords['lng'];
        $note->save();

        AnotacionVisita::create([
            'nota_id' => $notaId,
            'author_id' => auth()->id(),
            'asunto' => 'DENTRO',
            'cuerpo' => "Ubicación DENTRO: Latitud {$coords['lat']}, Longitud {$coords['lng']}",
        ]);

        Notification::make()
            ->title('Ubicación DENTRO capturada')
            ->success()
            ->body("Guardada para nota #$notaId: [{$coords['lat']}, {$coords['lng']}]")
            ->send();

        $this->dispatch('notaActualizada');
    }

    public function guardarUbicacion($notaId, $lat, $lng): void
    {
        $coords = $this->validatedGpsOrNotify($lat, $lng);

        if ($coords === null) {
            return;
        }

        $note = Note::find($notaId);
        if (!$note)
            return;

        $note->lat = $coords['lat'];
        $note->lng = $coords['lng'];
        $note->save();

        AnotacionVisita::create([
            'nota_id' => $notaId,
            'author_id' => auth()->id(),
            'asunto' => 'GPS',
            'cuerpo' => "Ubicación capturada: Latitud {$coords['lat']}, Longitud {$coords['lng']}",
        ]);

        Notification::make()
            ->title('Ubicación capturada')
            ->success()
            ->body("Ubicación guardada para la nota #$notaId: [{$coords['lat']}, {$coords['lng']}]")
            ->send();
    }

    public function toggleDeCamino($noteId, $lat = null, $lng = null): void
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

        $enCamino = ! $note->de_camino;

        if (! NoteRouteGps::toggleDeCamino($note, auth()->id(), $lat, $lng)) {
            Notification::make()
                ->title('GPS requerido')
                ->warning()
                ->body('Debes permitir la geolocalización para marcar DE CAMINO.')
                ->send();

            return;
        }

        Notification::make()
            ->title('Estado actualizado')
            ->{$enCamino ? 'success' : 'warning'}()
            ->body($enCamino ? 'La nota ha sido marcada como EN CAMINO' : 'La nota ha sido marcada como NO EN CAMINO')
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

        // Capturar comerciales antes de actualizar
        $notesParaReten = Note::query()
            ->whereIn('id', $ids)
            ->whereNotNull('comercial_id')
            ->get(['id', 'comercial_id']);

        // Solo mover a retén las que tengan comercial asignado
        $updated = Note::query()
            ->whereIn('id', $ids)
            ->whereNotNull('comercial_id')
            ->update([
                'reten' => true,
                'assignment_date' => now()->startOfDay(),
            ]);

        // Log de reasignación a retén
        if ($updated > 0) {
            $batch = NoteReassignmentBatch::create([
                'author_id'       => auth()->id(),
                'to_comercial_id' => null,
                'to_reten'        => true,
                'reassigned_at'   => now(),
            ]);
            foreach ($notesParaReten as $noteData) {
                NoteReassignmentLog::create([
                    'batch_id'          => $batch->id,
                    'note_id'           => $noteData->id,
                    'from_comercial_id' => $noteData->comercial_id,
                ]);
            }
        }

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

    public function sendSelectedToOfficeFromReten(?string $lat = null, ?string $lng = null): void
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

        ['lat' => $lat, 'lng' => $lng] = ActionGps::resolveFromCoords($lat, $lng);

        NoteSalaActions::sendBulkToOffice(
            $eligible,
            auth()->id(),
            $lat,
            $lng,
            addMassObservation: false,
        );

        $enviadas = count($eligible);
        Notification::make()
            ->title('✅ ' . $enviadas . ' nota' . ($enviadas === 1 ? '' : 's') . ' enviada' . ($enviadas === 1 ? '' : 's') . ' a Oficina')
            ->body($skipped ? "Omitidas (no elegibles): {$skipped}" : null)
            ->success()
            ->persistent()
            ->send();

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
            // 🧑‍💼 Comercial específico: solo notas activas (no en retén)
            $query->where('comercial_id', '!=', 1)
                ->where('comercial_id', $this->comercialId)
                ->where('reten', false);
        }

        return $query
            ->latest('assignment_date')
            ->get()
            ->map(fn($note) => $this->mapNote($note));
    }

    /** TODAS (excepto hoy) */
    public function getNotesAllProperty()
    {
        // 1. Iniciamos la consulta
        $query = Note::query()->with(['customer', 'comercial']);

        // 2. Filtros de fecha (Líneas independientes para evitar errores de paréntesis)
        $query->whereDate('assignment_date', '<>', today());
        $query->whereDate('assignment_date', '<', today());
        $query->whereDate('assignment_date', '>=', now()->subDays(5)->startOfDay());
        $query->where(function ($subquery) {
            $subquery->whereNull('estado_terminal')->orWhere('estado_terminal', '')->orWhere('estado_terminal', 'ausente');
        });

        // 4. Filtro de venta
        $query->whereDoesntHave('venta');

        // 5. Filtro de Retén o Comercial
        if ($this->esReten) {
            $query->where('reten', true);
        } else {
            // Comercial específico: solo notas activas (no en retén)
            $query->where('comercial_id', '!=', 1)
                ->where('comercial_id', $this->comercialId)
                ->where('reten', false);
        }

        return $query->latest('assignment_date')
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
            ? "$citySimple, $postalCodeSimple"
            : ($citySimple ?? $postalCodeSimple ?? 'Sin ubicación');

        return [
            'id' => $note->id,
            'nro_nota' => $note->nro_nota,
            'customer' => $customer->name ?? 'Sin cliente',
            'full_address' => $fullAddress,
            'primary_address' => $customer->primary_address ?? 'Sin dirección',
            'address_info' => $addressInfo,
            'locality' => $addressInfo,
            'comercial' => $note->comercial->empleado_id ?? 'Sin asignar',
            'visit_date' => \Carbon\Carbon::parse($note->assignment_date)->format('d/m/Y'),
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
