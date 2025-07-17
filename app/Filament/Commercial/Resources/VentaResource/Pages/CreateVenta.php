<?php

namespace App\Filament\Commercial\Resources\VentaResource\Pages;

use App\Filament\Commercial\Resources\VentaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\{Venta, PostalCode, Note, User};
use App\Filament\Commercial\Pages\NotasHoy;


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

        $this->form->fill(array_merge(
            ['note_id' => $note->id],
            $customer->only($customer->getFillable())
        ));
    }

    /* ---------------------------------------------------------------------
     | 2. Crear venta + relaciones
     * -------------------------------------------------------------------*/
    protected function handleRecordCreation(array $data): Venta
    {
        return DB::transaction(function () use ($data) {

            /* 2.1 Validar que exista el código postal */
            if (!PostalCode::find($data['postal_code_id'])) {
                throw ValidationException::withMessages([
                    'postal_code_id' => 'El código postal seleccionado no existe.',
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

            /* calcula cuota mensual si hay número de cuotas válido */
            $cuotas = (int) ($data['num_cuotas'] ?? 0);
            $cuotaMensual = $cuotas > 0 ? round($data['importe_total'] / $cuotas, 2) : null;

            if (!blank($data['companion_id']) && !User::where('id', $data['companion_id'])->exists()) {
                $data['companion_id'] = null;
            }


            /* 2.4 Crear venta */
            $venta = Venta::create([
                'note_id' => $this->noteId,
                'customer_id' => $customer->id,
                'comercial_id' => $note->comercial_id ?? auth()->id(),
                'companion_id' => blank($data['companion_id']) ? null : $data['companion_id'],
                'fecha_venta' => now(),
                'importe_total' => $data['importe_total'],
                'num_cuotas' => $data['num_cuotas'] ?? null,
                'cuota_mensual' => $cuotaMensual,
                'accesorio_entregado' => $data['accesorio_entregado'] ?? null,
                'motivo_venta' => $data['motivo_venta'] ?? null,
                'motivo_horario' => $data['motivo_horario'] ?? null,
                'interes_art' => $data['interes_art'] ?? false,
                'observaciones_repartidor' => $data['observaciones_repartidor'] ?? null,
                'productos_externos' => collect($data['productos_externos'] ?? [])
                    ->filter()                       // quita vacíos
                    ->implode(', '),
                'fecha_entrega' => $data['fecha_entrega'],
                'horario_entrega' => $data['horario_entrega']
            ]);

            /* 2.5 Guardar ofertas y productos relacionados */
            $this->form->model($venta)->saveRelationships();

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
