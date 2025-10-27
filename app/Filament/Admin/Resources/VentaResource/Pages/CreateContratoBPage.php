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

    public Venta $origen;
    public ?array $data = [];

    public function mount(int|string $record): void
    {
        $this->origen = \App\Models\Venta::with(['ventaOfertas.productos', 'customer', 'note'])
            ->findOrFail((int) $record);

        // ⬅️ CLAVE: el form "apunta" al contrato origen para LEER las relaciones
        $this->form->model($this->origen);

        $state = $this->origen->only([
            'note_id',
            'customer_id',
            'comercial_id',
            'companion_id',
            'fecha_venta',
            'fecha_entrega',
            'horario_entrega',
            'importe_total',
            'modalidad_pago',
            'forma_pago',
            'cuota_mensual',
            'num_cuotas',
            'accesorio_entregado',
            'motivo_venta',
            'motivo_horario',
            'interes_art',
            'productos_externos',
            'precontractual',
            'interes_art_detalle',
            'observaciones_repartidor',
            'estado_venta',
            'financiera',
            'importe_comercial',
            'importe_repartidor',
            'vta_rep',
            'vta_esp',
            'vta_ac',
            'com_venta',
            'com_entrega',
            'com_conpago',
            'pas_comercial',
            'pas_repartidor',
            'repartidor_id',
            'repartidor_2',
            'crema',
            'monto_extra',
            'total_final',
            'cuota_final',
            'entrada',
            'mostrar_ingresos',
            'mostrar_tipo_vivienda',
            'mostrar_situacion_lab',
            'mes_contr',
            'nro_cliente_adm',
        ]);

        $state['nro_contr_adm'] = trim((string) $this->origen->nro_contr_adm) . '-B';

        $this->form->fill($state ?? []);
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

        unset($data['customer'], $data['ventaOfertas']);

        $data['nro_contr_adm'] = trim((string) $this->origen->nro_contr_adm) . '-B';
        $data['nro_cliente_adm'] = $this->origen->nro_cliente_adm;

        $data['customer_id'] = $this->origen->customer_id;
        $data['note_id'] = $this->origen->note_id;
        $data['comercial_id'] = $this->origen->comercial_id;
        $data['companion_id'] = $this->origen->companion_id;

        if (empty($data['fecha_venta'])) {
            $data['fecha_venta'] = now();
        }

        /** @var \App\Models\Venta $nueva */
        $nueva = \DB::transaction(function () use ($data) {
            $nueva = \App\Models\Venta::create($data);

            $this->origen->loadMissing('ventaOfertas.productos');

            foreach ($this->origen->ventaOfertas as $vo) {
                $nuevoVo = $nueva->ventaOfertas()->create([
                    'oferta_id' => $vo->oferta_id,
                    'puntos' => $vo->puntos,
                ]);

                foreach ($vo->productos as $prod) {
                    $nuevoVo->productos()->create([
                        'producto_id' => $prod->producto_id,
                        'cantidad' => $prod->cantidad,
                        'puntos_linea' => $prod->puntos_linea,
                        'vendido_por' => $prod->vendido_por,
                    ]);
                }
            }

            \App\Models\TransactionVenta::create([
                'id_contrato' => $this->origen->id,
                'id_contrato_asoc' => $nueva->id,
            ]);

            return $nueva;
        });

        \Filament\Notifications\Notification::make()
            ->title('Contrato -B creado y asociado correctamente')
            ->success()
            ->send();

        // ✅ Usar helper global redirect() — compatible con Filament 3
        return redirect(
            \App\Filament\Admin\Resources\VentaResource::getUrl('edit', ['record' => $nueva])
        );
    }

}
