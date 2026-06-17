<?php

namespace App\Filament\Admin\Resources\VentaResource\Pages;

use App\Filament\Admin\Resources\VentaResource;
use App\Filament\Concerns\SyncsCustomerIbanOnVentaForm;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Venta;
use App\Models\Reparto;
use App\Enums\EstadoEntrega;
use App\Services\VentaNotesCustomerSync;
use App\Services\VentaCustomerIdentityService;
use Filament\Actions\DeleteAction;

class EditVenta extends EditRecord
{
    use SyncsCustomerIbanOnVentaForm;

    protected static string $resource = VentaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getTitle(): string
    {
        $venta = $this->record;

        $esB = filled($venta?->nro_contr_adm)
            && str_ends_with((string) $venta->nro_contr_adm, '-B');

        return $esB
            ? 'Editar Contrato -B'
            : 'Editar contrato';
    }




    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->hydrateCustomerIban($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        VentaCustomerIdentityService::reassignCustomerIfIdentityChanged($this->record, $data);

        $this->persistCustomerIban($data);

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



        Action::make('save')
            ->label('Guardar cambios')
            ->icon('heroicon-o-check-circle')
            ->color('info')
            ->action('save') // <--- ESTA ES LA CLAVE: Llama al método save() de Livewire directamente
            ->keyBindings(['mod+s']), // Opcional: permite guardar con Ctrl+S o Cmd+S

            Action::make('preview')
                ->label('Vista previa')
                ->icon('heroicon-o-eye')
                ->action(function (Venta $record) {
                    $this->persistirFechaContratoB($record);

                    // Contrato -B: mostrar sólo las 5 hojas del -B
                    if (str_ends_with((string) $record->nro_contr_adm, '-B')) {
                        $url = route('ventas.preview-b', $record);
                    } else {
                        $url = route('ventas.preview', $record);
                    }

                    $this->js("window.open('" . $url . "', '_blank')");
                })
                ->color('gray'),

            Action::make('pdf')
                ->label(fn() => str_ends_with((string) $this->record->nro_contr_adm, '-B')
                    ? 'Descargar Contr-B'
                    : 'Contrato PDF')
                ->icon('heroicon-o-document-text')
                ->action(function (Venta $record) {
                    if (str_ends_with((string) $record->nro_contr_adm, '-B')) {
                        return $this->downloadPdfB($record);
                    }
                    return $this->downloadPdf($record);
                })
                ->requiresConfirmation(false)
                ->color('warning'),

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

            DeleteAction::make()
                ->label('Borrar')
                ->visible(
                    fn(Venta $record): bool =>
                    filled($record?->nro_contr_adm) && str_ends_with($record->nro_contr_adm, '-B')
                )
                ->requiresConfirmation()
                ->modalHeading('Eliminar contrato -B')
                ->modalDescription('Esta acción eliminará el contrato -B y sus datos relacionados. ¿Deseas continuar?')
                ->successNotificationTitle('Contrato -B eliminado'),
        ];
    }

    protected function persistirFechaContratoB(Venta $venta): void
    {
        if (str_ends_with((string) $venta->nro_contr_adm, '-B')) {
            return;
        }

        $fechaB = $this->data['fecha_contrato_b_virtual'] ?? null;
        if ($fechaB) {
            $b = $venta->contratoB();
            $b?->updateQuietly(['fecha_venta' => $fechaB]);
        }
    }

    protected function downloadPdf(Venta $venta)
    {
        $this->persistirFechaContratoB($venta);

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

    protected function downloadPdfB(Venta $venta)
    {
        $venta->load([
            'note',
            'repartidor',
            'comercial',
            'ventaOfertas.productos.producto',
        ]);

        $pdf = Pdf::setOptions([
            'isRemoteEnabled' => true,
            'dpi' => 96,
            'defaultFont' => 'Helvetica',
            'chroot' => public_path(),
        ])
            ->loadView('pdf_pos_b', compact('venta'))
            ->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn() => print ($pdf->output()),
            'contrato-' . $venta->nro_contr_adm . '.pdf'
        );
    }

    protected function afterSave(): void
    {
        $venta = $this->record->fresh(['customer', 'note']);

        VentaNotesCustomerSync::syncFromVenta($venta);

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
