<?php

namespace App\Filament\Commercial\Resources\VentaDesdeCeroResource\Pages;

use App\Filament\Commercial\Resources\VentaDesdeCeroResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\{Venta, Note, Customer, User};
use App\Enums\{NoteStatus};
use App\Filament\Commercial\Pages\NotasHoy;

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

            unset($data['age']);
            
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
            $manual = $data['manual_created_at'] ?? null;
            $fechaVenta = $manual
                ? \Carbon\Carbon::parse($manual, config('app.timezone', 'Europe/Madrid'))
                    ->setTime(12, 0) // mediodía local
                    ->utc()
                : now();

            /** @var Venta $venta */
            $venta = Venta::create([
                'note_id' => $note->id,
                'customer_id' => $customer->id,
                'comercial_id' => $notaPayload['comercial_id'] ?? auth()->id(),
                'companion_id' => blank($data['companion_id']) ? null : $data['companion_id'],
                'fecha_venta' => $fechaVenta,
                'importe_comercial' => $data['importe_total'],
                'importe_repartidor' => 0,
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

            // Si el usuario especial 911 indicó una fecha manual, forzamos timestamps:
            $esUsuarioEspecial = auth()->user()?->empleado_id === '911';
            if ($esUsuarioEspecial && $manual) {
                $ts = \Carbon\Carbon::parse($manual, config('app.timezone', 'Europe/Madrid'))
                    ->setTime(12, 0)
                    ->utc();

                $venta->timestamps = false;
                $venta->created_at = $ts;
                $venta->updated_at = $ts;
                $venta->save();
            }
            $venta->save();

            // 6) Relaciones (ofertas/productos)
            $this->form->model($venta)->saveRelationships();

            // a) Importes por origen + recalcular cuota_mensual según ofertas reales
            $venta->recomputarImportesDesdeOfertas();

            // b) Comisiones (venta repartidor + entrega)
            $venta->calcularComisiones(true);

            // c) Ventas del repartidor (rep/esp) y acumuladas
            $venta->recomputarVtasRepYEsp()
                ->recalcularVtasAcumuladas(true);

            // d) PAS (puntos adicionales) para rep y com
            $venta->calcularPas(true);

            // f) Totales finales según entrada / monto_extra
            $entrada = (float) ($venta->entrada ?? 0);
            $montoExtra = (float) ($venta->monto_extra ?? 0);
            $venta->total_final = round(((float) $venta->importe_total - $entrada) + $montoExtra, 2);

            if ((int) $venta->num_cuotas > 0) {
                $venta->cuota_final = round($venta->total_final / (int) $venta->num_cuotas, 2);
            } else {
                $venta->cuota_final = null;
            }

            // g) Espejos administrativos en la venta (si los manejas)
            if (empty($venta->nro_contr_adm) && !empty($venta->nro_contrato)) {
                $venta->nro_contr_adm = $venta->nro_contrato;
            }
            if (empty($venta->nro_cliente_adm) && !empty($customer->nro_cliente)) {
                $venta->nro_cliente_adm = $customer->nro_cliente;
            }

            // h) Estado de entrega del reparto (si hay detalle suficiente)
            $venta->refreshEstadoEntrega();

            // i) Guardar todo lo anterior
            $venta->save();

            return $venta;
        });
    }

    protected function getRedirectUrl(): string
    {
        // Ajusta si prefieres otra página:
        return NotasHoy::getUrl();
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()->label('Crear Venta'),
            $this->getCancelFormAction()->label('Cancelar')->url(NotasHoy::getUrl()),
        ];
    }
}
