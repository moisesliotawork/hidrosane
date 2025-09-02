<?php

namespace App\Livewire\Commercial;

use Livewire\Component;
use App\Models\Note;
use Filament\Notifications\Notification;
use App\Models\AnotacionVisita;
use App\Filament\Commercial\Resources\NoteResource;

class NotasToday extends Component
{
    protected $listeners = [
        'notaActualizada' => '$refresh',
        'guardarUbicacion' => 'guardarUbicacion',
        'guardarUbicacionDentro' => 'guardarUbicacionDentro',
        'avisarSinDentro' => 'avisarSinDentro',
    ];

    public function avisarSinDentro($notaId): void
    {
        \Filament\Notifications\Notification::make()
            ->title('Sin ubicación DENTRO')
            ->body("La nota #{$notaId} no tiene coordenadas DENTRO guardadas.")
            ->danger()
            ->send();
    }

    public function guardarUbicacionDentro($notaId, $lat, $lng)
    {
        $note = Note::find($notaId);
        if (!$note) {
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

        \Filament\Notifications\Notification::make()
            ->title('Ubicación DENTRO capturada')
            ->success()
            ->body("Guardada para nota #$notaId: [$lat, $lng]")
            ->send();

        $this->dispatch('notaActualizada');
    }


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

        $url = NoteResource::getUrl(
            'edit',
            ['record' => $noteId],
            panel: 'comercial'
        );

        return redirect()->to($url);
    }



    public function getNotesProperty()
    {
        $hoy = now()->format('Y-m-d');

        //dd($hoy);



        return Note::with(['customer', 'comercial'])
            ->where('comercial_id', auth()->id())
            ->whereDate('assignment_date', today())
            ->where(function ($q) {
                $q->whereNull('estado_terminal')
                    ->orWhere('estado_terminal', '');
            })
            ->whereDoesntHave('venta')
            ->latest('assignment_date')
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

                    'lat_dentro' => $note->lat_dentro,
                    'lng_dentro' => $note->lng_dentro,
                ];
            });
    }

    public function render()
    {
        return view('livewire.commercial.notas-today');
    }
}
