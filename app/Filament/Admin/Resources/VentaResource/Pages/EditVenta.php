<?php

namespace App\Filament\Admin\Resources\VentaResource\Pages;

use App\Filament\Admin\Resources\VentaResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Venta;
use App\Models\Reparto;
use App\Enums\EstadoEntrega;

class EditVenta extends EditRecord
{
    protected static string $resource = VentaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

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


    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Vista previa')
                ->icon('heroicon-o-eye')
                ->url(fn(Venta $record) => route('ventas.preview', $record))
                ->openUrlInNewTab()               // ⇢ abre la URL en otra pestaña
                ->color('gray'),

            Action::make('pdf')
                ->label('Contrato PDF')
                ->icon('heroicon-o-document-text')
                ->action(fn(Venta $record) => $this->downloadPdf($record))
                ->requiresConfirmation(false)   // dispara directo
                ->color('primary'),             // conserva estilo Filament
        ];
    }

    /** Genera y envía el PDF sin alterar el front-end */
    protected function downloadPdf(Venta $venta)
    {
        // Carga relaciones necesarias
        $venta->load([
            'note',
            'customer.postalCode.city',
            'comercial',
            'ventaOfertas.productos.producto',
        ]);

        // Renderiza la vista Blade que ya tienes
        $pdf = Pdf::loadView('pdf', ['venta' => $venta])
            ->setPaper('letter');   // o 'a4', 'legal', etc.

        // Descarga directa
        return response()->streamDownload(
            fn() => print ($pdf->output()),
            'contrato-' . ($venta->note?->nro_nota ?? $venta->id) . '.pdf'
        );
    }

    protected function afterSave(): void
    {
        $venta = $this->record;

        if (!Reparto::where('venta_id', $venta->id)->exists()) {
            Reparto::create([
                'venta_id' => $venta->id,
                'estado_entrega' => EstadoEntrega::NO_ENTREGADO,
            ]);
        }
    }

}
