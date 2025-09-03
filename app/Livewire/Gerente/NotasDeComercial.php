<?php

namespace App\Livewire\Gerente;

use Livewire\Component;
use App\Models\Note;
use App\Models\AnotacionVisita;
use Filament\Notifications\Notification;
use App\Filament\Commercial\Resources\NoteResource;

class NotasDeComercial extends Component
{
    public int $comercialId;

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

        // ⚠️ Igual que el comercial, pero el Gerente puede forzar el cambio
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
                ->body($note->de_camino ? 'La nota ha sido marcada como EN CAMINO'
                    : 'La nota ha sido marcada como NO EN CAMINO')
                ->send();

        $this->dispatch('notaActualizada');
    }

    public function redirigirAVenta(int $noteId)
    {
        // Igual que el comercial: abre el recurso del panel comercial
        $url = NoteResource::getUrl('edit', ['record' => $noteId], panel: 'comercial');
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
            ->latest('assignment_date')
            ->get()
            ->map(fn($note) => $this->mapNote($note));
    }

    /** Todas las notas */
    public function getNotesAllProperty()
    {
        return \App\Models\Note::with(['customer', 'comercial'])
            ->where('comercial_id', $this->comercialId)
            ->whereDate('assignment_date', '<>', today())   // ⬅️ excluye las de hoy
            ->latest('assignment_date')
            ->get()
            ->map(fn($note) => $this->mapNote($note));
    }


    private function mapNote(Note $note): array
    {
        $postalCode = $note->customer->postalCode->code ?? null;
        $city = $note->customer->postalCode->city->title ?? null;
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
