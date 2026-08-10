<?php

namespace App\Filament\Admin\Resources\VentaResource\Pages;

use App\Filament\Admin\Resources\VentaResource;
use App\Filament\Concerns\SyncsCustomerIbanOnVentaForm;
use App\Filament\Concerns\PersistsVentaCustomerOnSave;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Venta;
use App\Models\Reparto;
use App\Models\VentaPdfDownload;
use App\Enums\EstadoEntrega;
use App\Services\VentaNotesCustomerSync;
use App\Support\VentaFechaVenta;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditVenta extends EditRecord
{
    use SyncsCustomerIbanOnVentaForm;
    use PersistsVentaCustomerOnSave;

    protected static string $resource = VentaResource::class;

    protected ?int $pendingCustomerId = null;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Contrato guardado')
            ->body('Los cambios se han guardado correctamente.');
    }

    /**
     * Garantiza alerta visible si falla validación/guardado, y redirect al índice si OK
     * (vía getRedirectUrl + getSavedNotification del flujo Filament).
     */
    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        try {
            parent::save($shouldRedirect, $shouldSendSavedNotification);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('No se pudo guardar')
                ->body($this->formatValidationFailureBody($exception))
                ->persistent()
                ->send();

            throw $exception;
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Error al guardar el contrato')
                ->body(str($exception->getMessage())->limit(240)->toString())
                ->persistent()
                ->send();

            throw $exception;
        }
    }

    protected function formatValidationFailureBody(ValidationException $exception): string
    {
        $lines = $this->humanValidationErrorLines($exception);

        if ($lines === []) {
            return 'Revisa los campos obligatorios. Algunas secciones pueden estar plegadas '
                .'(Gestión Documentos / Informe al repartidor).';
        }

        $list = collect($lines)
            ->take(8)
            ->map(fn (string $line) => '• '.$line)
            ->implode("\n");

        return "Completa estos campos para poder guardar:\n".$list;
    }

    /**
     * @return list<string>
     */
    protected function humanValidationErrorLines(ValidationException $exception): array
    {
        $labels = $this->ventaFormFieldLabels();
        $lines = [];

        foreach ($exception->errors() as $field => $messages) {
            $key = str_replace(['data.', 'data/'], '', (string) $field);
            $key = trim(str_replace(['/', '.'], '.', $key), '.');
            // "note id" accidental por espacios
            $normalized = str_replace(' ', '_', $key);
            $base = explode('.', $normalized)[0] ?? $normalized;

            $label = $labels[$normalized]
                ?? $labels[$base]
                ?? $labels[str_replace(' ', '_', $base)]
                ?? null;

            if ($label === null) {
                $label = str($base)->replace('_', ' ')->title()->toString();
            }

            foreach ((array) $messages as $message) {
                $msg = (string) $message;
                if ($msg === 'validation.required' || str_contains($msg, 'validation.required')) {
                    $msg = 'es obligatorio';
                } elseif (str_starts_with($msg, 'validation.')) {
                    $msg = trans($msg);
                }

                $lines[] = "{$label}: {$msg}";
            }
        }

        return array_values(array_unique($lines));
    }

    /**
     * @return array<string, string>
     */
    protected function ventaFormFieldLabels(): array
    {
        return [
            'note_id' => 'Nota asociada (note_id)',
            'estado_venta' => 'Estado de la venta',
            'precontractual' => 'Precontractual',
            'foto_sorteo' => 'Foto sorteo',
            'dni_anverso' => 'DNI anverso',
            'dni_reverso' => 'DNI reverso',
            'documento_titularidad' => 'Documento de titularidad',
            'nomina' => 'Nómina',
            'pension' => 'Pensión',
            'contrato_firmado' => 'Contrato firmado',
            'otros_documentos' => 'Otros documentos',
            'fecha_entrega' => 'Fecha de entrega (Informe al repartidor)',
            'horario_entrega' => 'Horario de entrega (Informe al repartidor)',
            'horario_entrega_2' => 'Horario de entrega 2 (Informe al repartidor)',
            'motivo_venta' => 'Motivo de venta (Informe al repartidor)',
            'motivo_horario' => 'Motivo del horario (Informe al repartidor)',
            'interes_art_detalle' => 'Detalle de otros artículos (Informe al repartidor)',
            'fecha_venta' => 'Fecha de la venta',
            'modalidad_pago' => 'Modalidad de pago',
            'num_cuotas' => 'Nº de cuotas',
            'forma_pago' => 'Forma de pago',
            'oferta_id' => 'Oferta',
            'comercial_id' => 'Comercial',
            'repartidor_id' => 'Repartidor',
            'customer_id' => 'Cliente',
            'importe_total' => 'Importe total',
            'importe_comercial' => 'Importe comercial',
        ];
    }

    protected function summarizeValidationErrors(ValidationException $exception): string
    {
        $lines = $this->humanValidationErrorLines($exception);

        if ($lines === []) {
            return '';
        }

        return 'Detalle: '.implode(' · ', array_slice($lines, 0, 8));
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
        return $this->hydrateCustomerFormData($data);
    }

    protected function beforeSave(): void
    {
        $this->persistVentaCustomerInBeforeSave();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->stripVentaCustomerFromSavePayload($data);

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

        if (array_key_exists('fecha_venta', $data)) {
            $data['fecha_venta'] = VentaFechaVenta::normalizeOnSave(
                $data['fecha_venta'],
                $this->record,
            );
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
                ->url(fn() => static::getResource()::getUrl('create-b', ['record' => $this->record]))
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
                ->modalDescription('El contrato -B se archivará (soft-delete) y aparecerá en Contratos borrados. ¿Deseas continuar?')
                ->successNotificationTitle('Contrato -B archivado')
                ->action(fn (Venta $record) => \App\Support\VentaSoftDelete::delete($record)),
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

        $bytes = $pdf->output();

        $this->archivePdfDownload($venta, $bytes, 'normal');

        return response()->streamDownload(
            fn() => print ($bytes),
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

        $bytes = $pdf->output();

        $this->archivePdfDownload($venta, $bytes, 'B');

        return response()->streamDownload(
            fn() => print ($bytes),
            'contrato-' . $venta->nro_contr_adm . '.pdf'
        );
    }

    /**
     * Guarda en segundo plano una copia del PDF generado al descargar, para que
     * SuperAdmin pueda auditar quién y cuándo descargó cada contrato (tabla
     * "PDF DESCARGADOS", solo visible en ese panel). No debe alterar en nada la
     * descarga que recibe el usuario actual.
     */
    protected function archivePdfDownload(Venta $venta, string $pdfBytes, string $tipo): void
    {
        try {
            $path = 'pdf-descargas/' . $venta->id . '/' . now()->format('YmdHis') . '_' . Str::random(6) . '.pdf';

            Storage::disk('local')->put($path, $pdfBytes);

            VentaPdfDownload::create([
                'venta_id' => $venta->id,
                'user_id' => auth()->id(),
                'tipo' => $tipo,
                'file_path' => $path,
            ]);
        } catch (Throwable $exception) {
            Log::warning('No se pudo archivar la copia del PDF descargado.', [
                'venta_id' => $venta->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function afterSave(): void
    {
        try {
            $venta = $this->record->fresh(['customer', 'note']);

            VentaNotesCustomerSync::syncFromVenta($venta);

            // Por si algo externo cambió el repeater, aunque el hook saved ya lo hace:
            $venta->recomputarImportesDesdeOfertas(false)
                ->calcularComisiones(false)
                ->recomputarVtasRepYEsp(false)
                ->recalcularVtasAcumuladas(false);

            $venta->saveQuietly();

            if (! Reparto::where('venta_id', $venta->id)->exists()) {
                Reparto::create(['venta_id' => $venta->id, 'estado_entrega' => EstadoEntrega::NO_ENTREGADO]);
            }
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Guardado parcial')
                ->body(
                    'El contrato se actualizó, pero falló un paso posterior: '
                    .str($exception->getMessage())->limit(200)->toString()
                )
                ->persistent()
                ->send();

            throw $exception;
        }
    }
}
