<?php

namespace App\Filament\Repartidor\Resources\VentaDesdeCeroResource\Pages;

use App\Filament\Repartidor\Resources\VentaDesdeCeroResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\{Venta, PostalCode, Note, Customer, User};
use App\Enums\{NoteStatus};
use App\Enums\VendidoPor;


class CreateVentaDesdeCero extends CreateRecord
{
    protected static string $resource = VentaDesdeCeroResource::class;

    public function getTitle(): string
    {
        return 'Puerta Fria';
    }

    protected function handleRecordCreation(array $data): Venta
    {
        return DB::transaction(function () use ($data) {

            // 1) Validaciones
            if (!PostalCode::find($data['postal_code_id'] ?? 0)) {
                throw ValidationException::withMessages([
                    'postal_code_id' => 'El código postal seleccionado no existe.',
                ]);
            }
            if (($data['interes_art'] ?? false) && blank($data['interes_art_detalle'] ?? null)) {
                throw ValidationException::withMessages([
                    'interes_art_detalle' => 'Especifica los artículos de interés.',
                ]);
            }

            // 2) Customer
            $customerFillable = (new Customer)->getFillable();
            $customerPayload = array_intersect_key($data, array_flip($customerFillable));
            /** @var Customer $customer */
            $customer = Customer::create($customerPayload);

            // 3) Note (solo fillables permitidos)
            $noteFillable = (new Note)->getFillable();

            $notaBase = [
                'user_id' => auth()->id(), // si prefieres otro, me dices
                'customer_id' => $customer->id,
                'comercial_id' => $data['nota_comercial_id'] ?? auth()->id(),
                'status' => $data['nota_status'] ?? NoteStatus::CONTACTED->value,
                'visit_date' => $data['nota_visit_date'] ?? null,
                'visit_schedule' => $data['nota_visit_schedule'] ?? null,
                'assignment_date' => ($data['nota_comercial_id'] ?? null) ? now() : null,
                'show_phone' => $data['nota_show_phone'] ?? true,
                'de_camino' => $data['nota_de_camino'] ?? false,
                'ayuntamiento' => $data['nota_ayuntamiento'] ?? null,
                // fuente (default), nro_nota (auto), estado_terminal (vacío), observations/productos_externos -> fuera
            ];

            $notaPayload = array_intersect_key($notaBase, array_flip($noteFillable));
            /** @var Note $note */
            $note = Note::create($notaPayload);

            // 4) Normalizaciones Venta
            if (($data['modalidad_pago'] ?? 'Financiado') === 'Contado') {
                $data['num_cuotas'] = 1;
            }
            $cuotas = (int) ($data['num_cuotas'] ?? 0);
            $cuotaMensual = $cuotas > 0
                ? round(((float) ($data['importe_total'] ?? 0)) / $cuotas, 2)
                : null;

            if (!blank($data['companion_id']) && !User::where('id', $data['companion_id'])->exists()) {
                $data['companion_id'] = null;
            }

            // 5) Venta
            /** @var Venta $venta */
            $venta = Venta::create([
                'note_id' => $note->id,
                'customer_id' => $customer->id,
                'comercial_id' => $notaPayload['comercial_id'] ?? auth()->id(),
                'companion_id' => blank($data['companion_id']) ? null : $data['companion_id'],
                'fecha_venta' => now(),
                'importe_repartidor' => $data['importe_total'],
                'importe_comercial' => 0,
                'importe_total' => $data['importe_total'] ?? 0,
                'modalidad_pago' => $data['modalidad_pago'] ?? 'Financiado',
                'forma_pago' => ($data['modalidad_pago'] ?? null) === 'Contado'
                    ? ($data['forma_pago'] ?? null)
                    : null,
                'num_cuotas' => $data['num_cuotas'] ?? null,
                'cuota_mensual' => $cuotaMensual,
                'accesorio_entregado' => $data['accesorio_entregado'] ?? null,
                "crema" => $data['crema'] ?? null,
                'motivo_venta' => $data['motivo_venta'] ?? null,
                'motivo_horario' => $data['motivo_horario'] ?? null,
                'interes_art' => $data['interes_art'] ?? false,
                'interes_art_detalle' => ($data['interes_art'] ?? false) ? ($data['interes_art_detalle'] ?? null) : null,
                'observaciones_repartidor' => $data['observaciones_repartidor'] ?? null,
                'productos_externos' => collect($data['productos_externos'] ?? [])->filter()->values()->all(),
                'fecha_entrega' => $data['fecha_entrega'] ?? null,
                'horario_entrega' => $data['horario_entrega'] ?? null,
                'precontractual' => $data['precontractual'] ?? null,
                'dni_anverso' => $data['dni_anverso'] ?? null,
                'dni_reverso' => $data['dni_reverso'] ?? null,
                'documento_titularidad' => $data['documento_titularidad'] ?? null,
                'nomina' => $data['nomina'] ?? null,
                'pension' => $data['pension'] ?? null,
                'contrato_firmado' => $data['contrato_firmado'] ?? null,
            ]);

            // 6) Relaciones (ofertas/productos)
            $this->form->model($venta)->saveRelationships();

            // Marcar todas las líneas como vendidas por Repartidor
            $venta->loadMissing(['ventaOfertas.productos']);
            foreach ($venta->ventaOfertas as $vo) {
                foreach ($vo->productos as $linea) {
                    $linea->vendido_por = VendidoPor::Repartidor;
                    $linea->save();
                }
            }

            // Recalcular importes y comisiones derivadas de que vende el Repartidor
            $venta->recomputarImportesDesdeOfertas();
            $venta->calcularComisiones(true);
            $venta->recomputarVtasRepYEsp(true)
                ->recalcularVtasAcumuladas(true);


            $venta->calcularPas(true);

            return $venta;
        });
    }

    protected function getRedirectUrl(): string
    {
        // Redirige al dashboard del panel repartidor
        return route('filament.repartidor.pages.dashboard');
    }


    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()->label('Crear Venta'),
            $this->getCancelFormAction()
                ->label('Cancelar')
                ->url(route('filament.repartidor.pages.dashboard')),
        ];
    }

}
