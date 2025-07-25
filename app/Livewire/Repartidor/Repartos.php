<?php

namespace App\Livewire\Repartidor;

use Livewire\Component;
use App\Models\Venta;
use App\Models\AnotacionVisita;
use Filament\Notifications\Notification;
use App\Filament\Commercial\Resources\VentaResource;

class Repartos extends Component
{
    protected $listeners = ['ventaActualizada' => '$refresh', 'guardarUbicacion' => 'guardarUbicacion'];

    public function guardarUbicacion($ventaId, $lat, $lng)
    {
        $venta = Venta::find($ventaId);
        $venta->lat = $lat;
        $venta->lng = $lng;
        $venta->save();

        AnotacionVisita::create([
            'nota_id' => $venta->note_id,
            'author_id' => auth()->id(),
            'asunto' => 'GPS',
            'cuerpo' => "Ubicación repartidor: [$lat, $lng]",
        ]);

        Notification::make()
            ->title('Ubicación guardada')
            ->success()
            ->body("Ubicación registrada para la venta #{$venta->id}")
            ->send();
    }

    public function toggleDeCamino($ventaId)
    {
        $venta = Venta::find($ventaId);

        if (!$venta || $venta->repartidor_id !== auth()->id()) {
            Notification::make()
                ->title('No autorizado')
                ->danger()
                ->body('No puedes modificar esta venta.')
                ->send();
            return;
        }

        $venta->de_camino = !$venta->de_camino;
        $venta->save();

        AnotacionVisita::create([
            'nota_id' => $venta->note_id,
            'author_id' => auth()->id(),
            'asunto' => 'REPARTO',
            'cuerpo' => $venta->de_camino ? "Repartidor en camino" : "Repartidor NO en camino",
        ]);

        Notification::make()
            ->title('Estado actualizado')
            ->success()
            ->body($venta->de_camino ? 'Marcado como EN CAMINO' : 'Marcado como NO EN CAMINO')
            ->send();

        $this->dispatch('ventaActualizada');
    }

    public function redirigirAVenta($ventaId)
    {
        $url = VentaResource::getUrl('edit', ['record' => $ventaId], panel: 'comercial');
        return redirect()->to($url);
    }

    public function getVentasProperty()
    {
        $hoy = now()->toDateString();

        return Venta::with(['note.customer.postalCode.city', 'comercial'])
            ->where('repartidor_id', auth()->id())
            ->get()
            ->map(function ($venta) {
                $note = $venta->note;
                $postalCode = $note->customer->postalCode->code ?? null;
                $city = $note->customer->postalCode->city->title ?? null;
                $addressInfo = $postalCode && $city ? "$postalCode, $city" : ($postalCode ?? $city ?? 'Sin ubicación');

                return [
                    'venta_id' => $venta->id,
                    'note_id' => $note->id,
                    'nro_nota' => $note->nro_nota,
                    'nro_contrato' => $venta->nro_contrato,
                    'customer' => $note->customer->name ?? 'Sin cliente',
                    'primary_address' => $note->customer->primary_address ?? 'Sin dirección',
                    'address_info' => $addressInfo,
                    'comercial' => $venta->comercial->empleado_id ?? 'Sin comercial',
                    'visit_date' => optional($note->visit_date)->format('d/m/Y'),
                    'visit_schedule' => $note->visit_schedule ?? '--:--',
                    'observations' => $note->observations,
                    'fuente' => $note->fuente->value,
                    'fuente_label' => $note->fuente->getLabel(),
                    'fuente_puntaje' => $note->fuente->getPuntaje(),
                    'de_camino' => $venta->de_camino,
                    'show_phone' => $note->show_phone,
                    'phone' => $note->customer->phone,
                    'secondary_phone' => $note->customer->secondary_phone,
                ];
            });
    }

    public function render()
    {
        return view('livewire.repartidor.repartos-today');
    }
}
