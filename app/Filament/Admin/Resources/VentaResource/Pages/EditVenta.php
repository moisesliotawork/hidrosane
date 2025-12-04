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

        if (in_array($modalidad, ['Contado', 'NS'], true)) {
            $data['num_cuotas'] = 1;
        }

        if ($modalidad !== 'Contado') {
            $data['forma_pago'] = null;
        }

        /* 2.b Normalizar montos para que NUNCA sean null */
        $data['monto_extra'] = isset($data['monto_extra']) && $data['monto_extra'] !== ''
            ? (float) $data['monto_extra']
            : 0;

        $data['entrada'] = isset($data['entrada']) && $data['entrada'] !== ''
            ? (float) $data['entrada']
            : 0;

        /* 3. Recalcular cuota mensual */
        $importe = (float) ($data['importe_total'] ?? 0);
        $cuotas = max((int) ($data['num_cuotas'] ?? 1), 1);

        $data['cuota_mensual'] = round($importe / $cuotas, 2);

        /* 4. Asegura que productos_externos sea array limpio */
        if (isset($data['productos_externos'])) {
            $data['productos_externos'] = collect($data['productos_externos'])
                ->filter()
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

            Action::make('crearContratoB')
                ->label('Crear Contrato -B')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->url(fn() => VentaResource::getUrl('create-b', ['record' => $this->record]))
                ->visible(function (): bool {
                    $venta = $this->record;

                    // si el propio contrato ya es un "-B"
                    $esB = str_ends_with((string) $venta->nro_contr_adm, '-B');

                    // si ya tiene algún "-B" asociado
                    $tieneBAsociado = $venta->asociadas()
                        ->where('nro_contr_adm', 'like', '%-B')
                        ->exists();

                    return !($esB || $tieneBAsociado);
                }),
        ];
    }

    protected function downloadPdf(Venta $venta)
    {
        $venta->load([
            'note',
            'repartidor',
            'comercial',
            'ventaOfertas.productos.producto',
        ]);

        // Rutas absolutas de las imágenes de fondo (normalizadas)
        $bg1 = str_replace('\\', '/', public_path('templates/contrato-ohana-vacio-1.png'));
        $bg2 = str_replace('\\', '/', public_path('templates/contrato-ohana-vacio-2.png'));

        $pdf = Pdf::setOptions([
            'isRemoteEnabled' => true,   // por si usas asset() en otros lados
            'dpi' => 96,
            'defaultFont' => 'Helvetica',
            'chroot' => public_path(), // permite leer archivos locales dentro de /public
        ])
            ->loadView('pdf_pos', compact('venta', 'bg1', 'bg2'))
            ->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn() => print ($pdf->output()),
            'contrato-' . ($venta->note?->nro_nota ?? $venta->id) . '.pdf'
        );
    }

    protected function afterSave(): void
    {
        $venta = $this->record;

        // Por si algo externo cambió el repeater, aunque el hook saved ya lo hace:
        $venta->recomputarImportesDesdeOfertas(false)
            ->calcularComisiones(false)
            ->recomputarVtasRepYEsp(false)
            ->recalcularVtasAcumuladas(false);

        $venta->saveQuietly();

        if (!Reparto::where('venta_id', $venta->id)->exists()) {
            Reparto::create(['venta_id' => $venta->id, 'estado_entrega' => EstadoEntrega::NO_ENTREGADO]);
        }
    }



}
