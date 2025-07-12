<?php

namespace App\Livewire\Commercial;

use Livewire\Component;
use App\Models\Note;
use Filament\Notifications\Notification;
use App\Models\AnotacionVisita;
use App\Filament\Commercial\Resources\VentaResource;

class NotasToday extends Component
{
    protected $listeners = ['notaActualizada' => '$refresh', 'guardarUbicacion' => 'guardarUbicacion'];


    public ?float $ubicacionLat = null;
    public ?float $ubicacionLng = null;

    public function guardarUbicacion($notaId, $lat, $lng)
    {
        $note = Note::find($notaId);
        $note->lat = $lat;
        $note->lng = $lng;
        $note->save();

        AnotacionVisita::create([
            'nota_id' => $notaId,
            'author_id' => auth()->id(),
            'asunto' => 'GPS',
            'cuerpo' => "Ubicación capturada: Latitud $lat, Longitud $lng"
        ]);


        Notification::make()
            ->title('Ubicación capturada')
            ->success()
            ->body("Ubicación guardada para la nota #$notaId: [$lat, $lng]")
            ->send();

        // Aquí puedes opcionalmente guardar en BD si quieres
        // $note = Note::find($notaId);
        // $note->lat = $lat;
        // $note->lng = $lng;
        // $note->save();
    }

    public function toggleDeCamino($noteId)
    {
        $note = Note::find($noteId);

        if (!$note || $note->comercial_id !== auth()->id()) {
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
            'cuerpo' => $nuevoEstado ? "Va de camino" : "No va de camino"
        ]);

        if (!$nuevoEstado) {
            Notification::make()
                ->title('Estado actualizado')
                ->warning()
                ->body('La nota ha sido marcada como NO EN CAMINO')
                ->send();
        } else {
            Notification::make()
                ->title('Estado actualizado')
                ->success()
                ->body('La nota ha sido marcada como EN CAMINO')
                ->send();
        }

        $this->dispatch('notaActualizada');
    }

    public function redirigirAVenta(int $noteId)
    {
        // URL “create” del recurso Venta, pasándole la nota
        $url = VentaResource::getUrl('create', ['note' => $noteId], panel: 'comercial');

        return redirect()->to($url);
    }

    public function getNotesProperty()
    {
        $hoy = now()->format('Y-m-d');



        return Note::with(['customer', 'comercial'])
            ->where('comercial_id', auth()->id())
            ->whereDate('assignment_date', $hoy)
            ->whereNull('estado_terminal')
            ->latest()
            ->get()
            ->map(function ($note) {
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
                ];
            });
    }

    public function render()
    {
        return view('livewire.commercial.notas-today');
    }
}
