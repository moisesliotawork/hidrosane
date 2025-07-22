<?php

namespace App\Filament\Admin\Resources\VentaResource\Pages;

use App\Filament\Admin\Resources\VentaResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;

class EditVenta extends EditRecord
{
    protected static string $resource = VentaResource::class;

    /**
     * Antes de guardar:
     *  • Impide modificar el nro_nota
     *  • Sincroniza num_cuotas / forma_pago / cuota_mensual según la modalidad
     *  • Limpia arrays vacíos de productos_externos
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /* 1. Nunca tocar el Nº de nota */
        Arr::forget($data, 'note.nro_nota');

        /* 2. Reglas de modalidad de pago */
        $modalidad = $data['modalidad_pago'] ?? 'Financiado';

        // a) Si es Contado o NS → siempre 1 cuota
        if (in_array($modalidad, ['Contado', 'NS'], true)) {
            $data['num_cuotas'] = 1;
        }

        // b) Forma de pago solo aplica en Contado; si no, nuléala
        if ($modalidad !== 'Contado') {
            $data['forma_pago'] = null;
        }

        /* 3. Recalcular cuota mensual */
        $importe = (float) ($data['importe_total'] ?? 0);
        $cuotas = max((int) ($data['num_cuotas'] ?? 1), 1);

        $data['cuota_mensual'] = round($importe / $cuotas, 2);

        /* 4. Asegura que productos_externos sea array limpio */
        if (isset($data['productos_externos'])) {
            $data['productos_externos'] = collect($data['productos_externos'])
                ->filter()      // quita strings vacíos
                ->values()
                ->all();
        }

        return $data;
    }
}
