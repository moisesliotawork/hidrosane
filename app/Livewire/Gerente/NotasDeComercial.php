<?php

namespace App\Livewire\Gerente;

use Livewire\Component;
use App\Models\Note;
use App\Models\User;
use App\Models\AnotacionVisita;
use Filament\Notifications\Notification;

class NotasDeComercial extends Component
{
    public int $comercialId;

    /** Modal de reasignación */
    public bool $showReassignModal = false;
    public ?int $reassignNoteId = null;
    public ?int $newComercialId = null;

    protected $listeners = [
        'notaActualizada' => '$refresh',
        'guardarUbicacion' => 'guardarUbicacion',
        'guardarUbicacionDentro' => 'guardarUbicacionDentro',
        'avisarSinDentro' => 'avisarSinDentro',
    ];

    public function mount(int $comercialId): void
    {
        $this->comercialId = $comercialId;
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

        // Reasigna SIN tocar la fecha de asignación
        $note->update(['comercial_id' => $this->newComercialId]);

        // Bitácora
        AnotacionVisita::create([
            'nota_id' => $note->id,
            'author_id' => auth()->id(),
            'asunto' => 'REASIGNACIÓN',
            'cuerpo' => "Nota #{$note->nro_nota} reasignada al comercial {$nombre} - {$empleado} (sin cambiar fecha de asignación).",
        ]);

        $this->showReassignModal = false;

        // ✅ Notificación con el formato solicitado
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
        return User::role(['commercial', 'team_leader'])
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

    public function redirigirAVenta(int $noteId)
    {
        $url = \App\Filament\Gerente\Resources\NotasGerenteResource::getUrl(
            'edit',
            ['record' => $noteId],
            panel: 'gerente'
        );

        return redirect()->to($url);
    }

    /** Notas de HOY */
    public function getNotesTodayProperty()
    {
        return Note::with(['customer', 'comercial'])
            ->where('comercial_id', $this->comercialId)
            ->whereDate('assignment_date', today())
            ->where(function ($q) {
                $q->whereNull('estado_terminal')
                    ->orWhere('estado_terminal', '')
                    ->orWhere('estado_terminal', 'ausente');
            })
            ->whereDoesntHave('venta')
            ->orderByDesc('assignment_date')
            ->orderByRaw('CAST(nro_nota AS UNSIGNED) DESC')
            ->get()
            ->map(fn($note) => $this->mapNote($note));
    }

    /** TODAS (excepto hoy) */
    public function getNotesAllProperty()
    {
        return Note::with(['customer', 'comercial'])
            ->where('comercial_id', $this->comercialId)
            ->whereDate('assignment_date', '<', today())
            ->where(function ($q) {
                $q->whereNull('estado_terminal')
                    ->orWhere('estado_terminal', '')
                    ->orWhere('estado_terminal', 'ausente');
            })
            ->whereDoesntHave('venta')
            ->where('assignment_date', '>=', now()->subDays(5)->startOfDay())
            ->orderByDesc('assignment_date')
            ->orderByRaw('CAST(nro_nota AS UNSIGNED) DESC')
            ->get()
            ->map(fn($note) => $this->mapNote($note));
    }

    private function mapNote(Note $note): array
    {
        $postalCode = $note->customer->postal_code ?? null;
        $city = $note->customer->ciudad ?? null;
        $addressInfo = $postalCode && $city ? "$postalCode, $city" : ($postalCode ?? $city ?? 'Sin ubicación');

        return [
            'id' => $note->id,
            'nro_nota' => $note->nro_nota,
            'customer' => $note->customer->name ?? 'Sin cliente',
            'primary_address' => $note->customer->primary_address ?? 'Sin dirección',
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
            'phone' => $note->customer->phone ?? null,
            'secondary_phone' => $note->customer->secondary_phone ?? null,
            'lat_dentro' => $note->lat_dentro,
            'lng' => $note->lng,
            'lat' => $note->lat,
            'lng_dentro' => $note->lng_dentro,
        ];
    }

    public function render()
    {
        return view('livewire.gerente.notas-de-comercial');
    }
}
