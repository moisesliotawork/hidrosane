<?php

namespace App\Filament\Admin\Resources\VentaResource\Pages;

use App\Filament\Admin\Resources\VentaResource;
use App\Models\Venta;
use App\Models\TransactionVenta;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportRedirects\Redirector;

class CreateContratoBPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = VentaResource::class;
    protected static string $view = 'filament.admin.ventas.create-contrato-b';

    public function getTitle(): string
    {
        return "CREAR CONTRATO -B ";
    }

    public Venta $origen;
    public ?array $data = [];

    public function mount(int|string $record): void
    {
        $this->origen = Venta::with(['customer', 'note'])
            ->findOrFail((int) $record);

        $state = [
            // Base
            'note_id' => $this->origen->note_id,
            'customer_id' => $this->origen->customer_id,
            'comercial_id' => $this->origen->comercial_id,
            'companion_id' => $this->origen->companion_id,
            'nro_cliente_adm' => $this->origen->nro_cliente_adm,
            'mes_contr' => $this->origen->mes_contr,
            'nro_contr_adm' => trim((string) $this->origen->nro_contr_adm) . '-B',
            'fecha_venta' => now(),

            // ✅ Informe al repartidor (precargar)
            'repartidor_id' => $this->origen->repartidor_id,
            'fecha_entrega' => $this->origen->fecha_entrega,
            'horario_entrega' => $this->origen->horario_entrega,
            'motivo_venta' => $this->origen->motivo_venta,
            'motivo_horario' => $this->origen->motivo_horario,
            'interes_art' => (bool) $this->origen->interes_art,
            'interes_art_detalle' => $this->origen->interes_art_detalle,
            'observaciones_repartidor' => $this->origen->observaciones_repartidor,
        ];

        // ✅ Precargar cliente (state anidado)
        $state['customer'] = $this->origen->customer?->only([
            'first_names',
            'last_names',
            'dni',
            'phone',
            'secondary_phone',
            'third_phone',
            'email',
            'fecha_nac',
            'nro_piso',
            'postal_code',
            'ciudad',
            'provincia',
            'primary_address',
            'secondary_address',
            'ayuntamiento',
            'tipo_vivienda',
            'estado_civil',
            'situacion_laboral',
            'num_hab_casa',
            'iban',
            'ingresos_rango',
        ]) ?? [];

        // ✅ No precargar ventaOfertas ni totales
        $state['ventaOfertas'] = [];

        // ✅ (opcional) deja “datos de la venta” totalmente limpios si algo los setea
        $state['importe_total'] = 0;
        $state['monto_extra'] = 0;
        $state['entrada'] = 0;
        $state['total_final'] = 0;
        $state['cuota_final'] = 0;

        $this->form->fill($state);
    }



    public function form(Form $form): Form
    {
        return VentaResource::form($form)
            ->model(Venta::class)    // ⬅️ evita "->customer() on null" al resolver relationship('customer')
            ->statePath('data');
    }

    public function create()
    {
        $data = $this->form->getState();

        unset($data['customer']); // NO borres ventaOfertas

        $data['nro_contr_adm'] = trim((string) $this->origen->nro_contr_adm) . '-B';
        $data['nro_cliente_adm'] = $this->origen->nro_cliente_adm;

        $data['customer_id'] = $this->origen->customer_id;
        $data['note_id'] = $this->origen->note_id;
        $data['comercial_id'] = $this->origen->comercial_id;
        $data['companion_id'] = $this->origen->companion_id;

        $data['fecha_venta'] ??= now();

        $nueva = DB::transaction(function () use ($data) {

            // 1) Crear venta SIN depender de importe_total del state
            $ventaData = $data;
            unset($ventaData['ventaOfertas']); // relaciones aparte

            // 👇 crea en 0 temporal
            $ventaData['importe_total'] = 0;

            /** @var \App\Models\Venta $nueva */
            $nueva = Venta::create($ventaData);

            // 2) Guardar ofertas/productos que se pusieron en el formulario
            $this->form->model($nueva)->saveRelationships();

            // 3) Recalcular importe_total DESDE BD (lo más fiable)
            $nueva->load('ventaOfertas.oferta');

            $importe = $nueva->ventaOfertas
                ->sum(fn($vo) => $vo->oferta?->precio_base ?? 0);

            $nueva->update([
                'importe_total' => $importe,
            ]);

            $extra = (float) ($nueva->monto_extra ?? 0);
            $entrada = (float) ($nueva->entrada ?? 0);
            $cuotas = max((int) ($nueva->num_cuotas ?? 1), 1);

            $totalFinal = max(0, round(($importe + $extra) - $entrada, 2));
            $cuotaFinal = round($totalFinal / $cuotas, 2);

            $nueva->update([
                'total_final' => $totalFinal,
                'cuota_final' => $cuotaFinal,
                'cuota_mensual' => round($importe / $cuotas, 2),
            ]);

            // 4) Asociar origen -> nueva
            TransactionVenta::create([
                'id_contrato' => $this->origen->id,
                'id_contrato_asoc' => $nueva->id,
            ]);

            return $nueva;
        });

        Notification::make()
            ->title('Contrato -B creado y asociado correctamente')
            ->success()
            ->send();

        return redirect(VentaResource::getUrl('edit', ['record' => $nueva]));
    }


}
