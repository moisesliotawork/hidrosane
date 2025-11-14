<?php

namespace App\Filament\Commercial\Resources\VentaResource\Pages;

use App\Filament\Commercial\Resources\VentaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\{Venta, Note, User};
use App\Filament\Commercial\Pages\NotasHoy;
use App\Enums\{EstadoTerminal, MesesEnum};
use Carbon\Carbon;
use Filament\Notifications\Notification;

class CreateVenta extends CreateRecord
{
    protected static string $resource = VentaResource::class;

    /** id de la nota recibida en la URL */
    public int $noteId;

    /* ---------------------------------------------------------------------
     | 1. Cargar la nota y pre-rellenar el formulario
     * -------------------------------------------------------------------*/
    public function mount(): void
    {
        parent::mount();

        $this->noteId = (int) request()->route('note');
        abort_if(!$this->noteId, 404, 'Nota no especificada');

        $note = Note::with('customer')->findOrFail($this->noteId);
        $customer = $note->customer;

        $payload = array_merge(
            ['note_id' => $note->id],
            $customer->only($customer->getFillable()),
            [
                // opcional: precargar edad para mostrarla desde el inicio
                'age' => $customer->fecha_nac ? Carbon::parse($customer->fecha_nac)->age : null,
            ]
        );

        $this->form->fill($payload);
    }

    protected function handleRecordCreation(array $data): Venta
    {
        return DB::transaction(function () use ($data) {

            unset($data['age']);

            if (($data['companion_id'] ?? null) === '__NONE__') {
                $data['companion_id'] = null;
            }

            if (!blank($data['companion_id']) && !User::where('id', $data['companion_id'])->exists()) {
                $data['companion_id'] = null;
            }

            /* 🔒 VALIDAR QUE CADA OFERTA TENGA ≥ 1 PRODUCTO -------------------- */
            $state = $this->form->getRawState(); // <-- trae ventaOfertas y productos
            $errores = [];

            foreach (($state['ventaOfertas'] ?? []) as $i => $oferta) {
                $productos = collect($oferta['productos'] ?? [])
                    ->filter(fn($p) => !empty($p['producto_id']))
                    ->values();

                if ($productos->isEmpty()) {
                    $errores["ventaOfertas.$i.productos"] = 'Debes agregar al menos un producto a esta oferta.';
                }

                // recalcula puntos (evitar NULL)
                $state['ventaOfertas'][$i]['puntos'] = (int) $productos->sum(
                    fn($p) => (int) ($p['puntos_linea'] ?? 0)
                );
            }

            if (!empty($errores)) {
                $this->form->fill($state); // reflejar puntos=0 si corresponde
                Notification::make()
                    ->title('Faltan productos')
                    ->body('Cada oferta debe tener al menos un producto.')
                    ->danger()
                    ->persistent()
                    ->send();

                throw ValidationException::withMessages($errores);
            }

            /* 2.1.b Valida que venga el detalle si activaron el toggle */
            if (($data['interes_art'] ?? false) && blank($data['interes_art_detalle'] ?? null)) {
                throw ValidationException::withMessages([
                    'interes_art_detalle' => 'Especifica los artículos de interés.',
                ]);
            }

            /* 2.2 Cargar nota + cliente otra vez (por seguridad) */
            $note = Note::with('customer')->findOrFail($this->noteId);
            $customer = $note->customer;

            /* 2.3 Actualizar datos del cliente */
            $customer->update(array_intersect_key(
                $data,
                array_flip($customer->getFillable())
            ));

            /* ⚠️ Forzar número de cuotas si es contado */
            if (($data['modalidad_pago'] ?? 'Financiado') === 'Contado') {
                $data['num_cuotas'] = 1;
            }

            /* calcula cuota mensual si hay número de cuotas válido */
            $cuotas = (int) ($data['num_cuotas'] ?? 0);
            $cuotaMensual = $cuotas > 0 ? round($data['importe_total'] / $cuotas, 2) : null;

            if (!blank($data['companion_id']) && !User::where('id', $data['companion_id'])->exists()) {
                $data['companion_id'] = null;
            }

            /* 2.4 Crear venta -------------------------------------------------- */
            $venta = Venta::create([
                'note_id' => $this->noteId,
                'customer_id' => $customer->id,
                'comercial_id' => $note->comercial_id ?? auth()->id(),
                'companion_id' => $data['companion_id'],
                'fecha_venta' => now(),
                'importe_total' => $data['importe_total'],
                'importe_comercial' => $data['importe_total'],
                'modalidad_pago' => $data['modalidad_pago'] ?? 'Financiado',
                'forma_pago' => ($data['modalidad_pago'] ?? null) === 'Contado'
                    ? ($data['forma_pago'] ?? null)
                    : null,
                'num_cuotas' => $data['num_cuotas'] ?? 1,
                'cuota_mensual' => $cuotaMensual,
                'accesorio_entregado' => $data['accesorio_entregado'] ?? null,
                'motivo_venta' => $data['motivo_venta'] ?? null,
                'motivo_horario' => $data['motivo_horario'] ?? null,
                'interes_art' => $data['interes_art'] ?? false,
                'interes_art_detalle' => ($data['interes_art'] ?? false)
                    ? ($data['interes_art_detalle'] ?? null)
                    : null,
                'observaciones_repartidor' => $data['observaciones_repartidor'] ?? null,

                // ahora guardamos el array limpio (el modelo lo castea a array)
                'productos_externos' => collect($data['productos_externos'] ?? [])
                    ->filter()
                    ->values()
                    ->all(),

                'crema' => (bool) ($data['crema'] ?? false),

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

            /* 2.5 Guardar ofertas y productos relacionados */
            $this->form->model($venta)->saveRelationships();

            /* 2.6 Cálculos derivados (orden recomendado) ---------------------- */

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

            // j) Estado de la nota
            $note->update(['estado_terminal' => EstadoTerminal::VENTA, 'reten' => false]);

            return $venta;
        });
    }

    protected function getRedirectUrl(): string
    {
        return NotasHoy::getUrl();   // genera /commercial/notas-hoy
    }

    protected function getFormActions(): array
    {
        return [
            // Botón principal (antes llamado “Crear”)
            $this->getCreateFormAction()
                ->label('Declarar VENTA'),

            // Botón Cancelar → redirige a la página Notas Hoy
            $this->getCancelFormAction()
                ->label('Cancelar')
                ->url(NotasHoy::getUrl()),
        ];
    }
}
