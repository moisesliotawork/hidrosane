<?php

namespace App\Livewire\Repartidor;

use Livewire\Component;
use App\Models\Reparto;
use App\Models\Venta;
use App\Models\AnotacionVisita;
use Filament\Notifications\Notification;
use App\Filament\Commercial\Resources\VentaResource;
use App\Enums\EstadoEntrega;

class Repartos extends Component
{
    protected $listeners = [
        'ventaActualizada' => '$refresh',
        'guardarUbicacion' => 'guardarUbicacion',
        'guardarUbicacionDentro' => 'guardarUbicacionDentro',
        'avisarSinGPS' => 'avisarSinGPS',
    ];

    public function avisarSinGPS($payload): void
    {
        $ventaId = is_array($payload) && isset($payload['ventaId']) ? $payload['ventaId'] : $payload;

        $venta = Venta::find($ventaId);

        $mostrar = $venta?->nro_contr_adm
            ?: ($venta?->nro_contrato ?? '—');

        Notification::make()
            ->title('Sin ubicación en GPS')
            ->body("La venta {$mostrar} no tiene coordenadas de GPS guardadas.")
            ->danger()
            ->send();
    }

    public function guardarUbicacion($repartoId, $lat, $lng)
    {
        $reparto = Reparto::with('venta')->find($repartoId);
        if (!$reparto || $reparto->venta?->repartidor_id !== auth()->id()) {
            Notification::make()->title('No autorizado')->danger()->body('No puedes modificar este reparto.')->send();
            return;
        }

        $reparto->update([
            'lat' => $lat,
            'lng' => $lng,
        ]);

        AnotacionVisita::create([
            'nota_id' => $reparto->venta->note_id,
            'author_id' => auth()->id(),
            'asunto' => 'GPS',
            'cuerpo' => "Ubicación repartidor: [$lat, $lng]",
        ]);

        Notification::make()
            ->title('Ubicación guardada')
            ->success()
            ->body("Ubicación registrada para el reparto #{$reparto->id}")
            ->send();
    }

    public function guardarUbicacionDentro($repartoId, $lat, $lng)
    {
        $reparto = Reparto::with('venta')->find($repartoId);
        if (!$reparto || $reparto->venta?->repartidor_id !== auth()->id()) {
            Notification::make()->title('No autorizado')->danger()->body('No puedes modificar este reparto.')->send();
            return;
        }

        $reparto->update([
            'dentro_lat' => $lat,
            'dentro_lng' => $lng,
        ]);

        AnotacionVisita::create([
            'nota_id' => $reparto->venta->note_id,
            'author_id' => auth()->id(),
            'asunto' => 'GPS-DENTRO',
            'cuerpo' => "Ubicación DENTRO: [$lat, $lng]",
        ]);

        Notification::make()
            ->title('Ubicación DENTRO guardada')
            ->success()
            ->body("Ubicación DENTRO registrada para el reparto #{$reparto->id}")
            ->send();
    }

    public function toggleDeCamino($repartoId)
    {
        $reparto = Reparto::with('venta')->find($repartoId);
        if (!$reparto || $reparto->venta?->repartidor_id !== auth()->id()) {
            Notification::make()->title('No autorizado')->danger()->body('No puedes modificar este reparto.')->send();
            return;
        }

        $reparto->de_camino = !$reparto->de_camino;
        $reparto->save();

        AnotacionVisita::create([
            'nota_id' => $reparto->venta->note_id,
            'author_id' => auth()->id(),
            'asunto' => 'REPARTO',
            'cuerpo' => $reparto->de_camino ? "Repartidor en camino" : "Repartidor NO en camino",
        ]);

        Notification::make()
            ->title('Estado actualizado')
            ->success()
            ->body($reparto->de_camino ? 'Marcado como EN CAMINO' : 'Marcado como NO EN CAMINO')
            ->send();

        $this->dispatch('ventaActualizada');
    }

    public function redirigirAVenta($ventaId)
    {
        $url = VentaResource::getUrl('view', ['record' => $ventaId], panel: 'repartidor');
        return redirect()->to($url);
    }

    public function getRepartosProperty()
    {
        $hoy = now()->toDateString();

        return Reparto::with('venta.note.customer.postalCode.city', 'venta.comercial')
            ->whereHas('venta', fn($q) => $q->where('repartidor_id', auth()->id()))
            ->where(function ($q) {
                $q->where('estado', 'pendiente');
            })
            ->get()
            ->map(function ($reparto) {
                $venta = $reparto->venta;
                $note = $venta->note;
                $customer = $note->customer;

                $postalCode = $customer->postalCode->code ?? null;
                $city = $customer->postalCode->city->title ?? null;
                $addressInfo = $postalCode && $city ? "$postalCode, $city" : ($postalCode ?? $city ?? 'Sin ubicación');

                return [
                    'reparto_id' => $reparto->id,
                    'venta_id' => $venta->id,
                    'note_id' => $note->id,
                    'nro_nota' => $note->nro_nota,
                    'nro_contrato' => $venta->nro_contrato,
                    'customer' => $customer->name ?? 'Sin cliente',
                    'primary_address' => $customer->primary_address ?? 'Sin dirección',
                    'address_info' => $addressInfo,
                    'comercial' => $venta->comercial->empleado_id ?? 'Sin comercial',
                    'visit_date' => optional($note->visit_date)->format('d/m/Y'),
                    'visit_schedule' => $note->visit_schedule ?? '--:--',
                    'observations' => $note->observations,
                    'fuente' => $note->fuente->value,
                    'fuente_label' => $note->fuente->getLabel(),
                    'fuente_puntaje' => $note->fuente->getPuntaje(),
                    'de_camino' => $reparto->de_camino,
                    'lat' => $reparto->lat,
                    'lng' => $reparto->lng,
                    'show_phone' => $note->show_phone,
                    'phone' => $customer->phone,
                    'secondary_phone' => $customer->secondary_phone,
                    'lat_dentro' => $note->lat_dentro,
                    'lng_dentro' => $note->lng_dentro,
                ];
            });
    }

    public function render()
    {
        return view('livewire.repartidor.repartos', [
            'repartos' => $this->repartos,
        ]);
    }
}
