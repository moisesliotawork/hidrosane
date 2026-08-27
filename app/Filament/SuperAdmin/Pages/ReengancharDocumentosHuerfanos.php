<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Exports\RecoveredContractsExport;
use App\Models\Venta;
use App\Services\ContractRecovery\OrphanDocumentMatcher;
use App\Support\Filament\VentaDocumentUpload;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Paso 2: re-enganchar documentos huérfanos a contratos ya recuperados.
 * No crea ventas; solo propone/aplica enlaces a slots vacíos.
 */
class ReengancharDocumentosHuerfanos extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = '2. Docs huérfanos';

    protected static ?string $navigationGroup = 'RECUPERACION CONTRATOS';

    protected static ?string $title = 'Paso 2 · Re-enganchar documentos huérfanos';

    protected static ?string $slug = 'reenganchar-documentos-huerfanos';

    protected static string $view = 'filament.superAdmin.pages.reenganchar-documentos-huerfanos';

    /** Justo debajo de "1. Recuperar contrato" en el menú */
    protected static ?int $navigationSort = -6;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    /** @var array<string, mixed>|null */
    public ?array $filterData = [];

    /** @var list<array<string, mixed>> */
    public array $proposals = [];

    public int $orphanCount = 0;

    public int $targetVentaCount = 0;

    public bool $searched = false;

    public ?string $lastError = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('goToRecoverContract')
                ->label('Paso 1 · Recuperar contrato')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('gray')
                ->url(fn (): string => RecuperarContratoImagen::getUrl()),
            Action::make('exportRecoveredExcel')
                ->label('Excel + candidatos')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->tooltip('Excel de recuperados con inventario de huérfanos (este paso)')
                ->action(function (): BinaryFileResponse {
                    $filename = 'docs-huerfanos-'.now()->format('Ymd-His').'.xlsx';

                    return Excel::download(new RecoveredContractsExport(includeOrphanHints: true), $filename);
                }),
        ];
    }

    public function mount(): void
    {
        $this->filterForm->fill([
            'scope' => 'from_recovered',
            'nro' => null,
            'venta_id' => null,
            'month' => null,
            'with_ocr' => false,
        ]);
    }

    protected function getForms(): array
    {
        return [
            'filterForm',
        ];
    }

    public function filterForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Activar búsqueda de huérfanos')
                    ->description('Primero debe existir el contrato (paso 1). Aquí solo se inventarian ficheros sueltos en storage y se proponen enlaces a slots vacíos.')
                    ->schema([
                        Forms\Components\Radio::make('scope')
                            ->label('Alcance')
                            ->options([
                                'from_recovered' => 'Todos los contratos recuperados',
                                'nro' => 'Por nº contrato admin',
                                'venta' => 'Por ID de venta',
                            ])
                            ->default('from_recovered')
                            ->inline()
                            ->live()
                            ->required(),
                        Forms\Components\TextInput::make('nro')
                            ->label('Nº contrato admin')
                            ->visible(fn (Forms\Get $get): bool => $get('scope') === 'nro')
                            ->required(fn (Forms\Get $get): bool => $get('scope') === 'nro'),
                        Forms\Components\TextInput::make('venta_id')
                            ->label('ID venta')
                            ->numeric()
                            ->visible(fn (Forms\Get $get): bool => $get('scope') === 'venta')
                            ->required(fn (Forms\Get $get): bool => $get('scope') === 'venta'),
                        Forms\Components\TextInput::make('month')
                            ->label('Mes de carga (opcional)')
                            ->placeholder('YYYYMM · ej. 202601')
                            ->helperText('Filtra huérfanos por el prefijo de fecha del nombre de archivo.'),
                        Forms\Components\Toggle::make('with_ocr')
                            ->label('Usar OCR (DNI + Fec.Promo)')
                            ->helperText('Necesario para marcar matches “auto”. Sin OCR solo verás candidatos por ventana de carga.')
                            ->default(false),
                        Forms\Components\Toggle::make('use_packs')
                            ->label('Modo packs (mismo minuto)')
                            ->helperText('Ventana fecha_venta −5/+4 días. Agrupa ficheros subidos el mismo minuto; OCR solo del precontractual y rellena DNI/otros slots vacíos.')
                            ->default(true),
                    ])
                    ->columns(2),
            ])
            ->statePath('filterData');
    }

    public function buscarPropuestas(OrphanDocumentMatcher $matcher): void
    {
        $this->searched = false;
        $this->proposals = [];
        $this->orphanCount = 0;
        $this->targetVentaCount = 0;
        $this->lastError = null;

        $data = $this->filterForm->getState();
        $scope = (string) ($data['scope'] ?? 'from_recovered');
        $withOcr = (bool) ($data['with_ocr'] ?? false);
        $usePacks = (bool) ($data['use_packs'] ?? true);
        $month = filled($data['month'] ?? null) ? (string) $data['month'] : null;

        if ($withOcr && ! filled(config('services.openai.api_key'))) {
            Notification::make()
                ->title('Falta OPENAI_API_KEY')
                ->body('Desactiva OCR o configura la clave para auto-match.')
                ->danger()
                ->send();

            return;
        }

        try {
            $ventas = match ($scope) {
                'nro' => $matcher->resolveTargetVentas(null, (string) $data['nro'], false),
                'venta' => $matcher->resolveTargetVentas((int) $data['venta_id'], null, false),
                default => $matcher->resolveTargetVentas(null, null, true),
            };

            if ($ventas === []) {
                Notification::make()
                    ->title('Sin contratos objetivo')
                    ->body('No hay ventas recuperadas (o el nº/ID no existe). Completa primero el paso 1.')
                    ->warning()
                    ->send();

                return;
            }

            $this->targetVentaCount = count($ventas);
            $orphans = $matcher->listOrphans($month);
            $this->orphanCount = count($orphans);
            if ($usePacks) {
                $proposals = [];
                foreach ($ventas as $venta) {
                    $proposals = array_merge($proposals, $matcher->proposePacks($venta, $orphans, $withOcr));
                }
                $this->proposals = $proposals;
            } else {
                $this->proposals = $matcher->propose($ventas, $orphans, $withOcr);
            }
            $this->searched = true;

            $auto = collect($this->proposals)->where('action', 'auto')->count();
            $review = collect($this->proposals)->where('action', 'review')->count();

            Notification::make()
                ->title('Búsqueda completada')
                ->body("Ventas: {$this->targetVentaCount} · Huérfanos: {$this->orphanCount} · Auto: {$auto} · Revisar: {$review}")
                ->success()
                ->send();
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            Notification::make()
                ->title('Error al buscar huérfanos')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function aplicarMatchesClaros(OrphanDocumentMatcher $matcher): void
    {
        if (! $this->searched) {
            Notification::make()
                ->title('Primero busca propuestas')
                ->warning()
                ->send();

            return;
        }

        $auto = array_values(array_filter(
            $this->proposals,
            fn (array $p): bool => ($p['action'] ?? '') === 'auto',
        ));

        if ($auto === []) {
            Notification::make()
                ->title('No hay matches “auto”')
                ->body('Activa OCR y vuelve a buscar, o revisa candidatos manualmente en el Excel.')
                ->warning()
                ->send();

            return;
        }

        $result = $matcher->apply($auto);

        Notification::make()
            ->title('Re-enganche aplicado')
            ->body("Enlazados: {$result['applied']} · Omitidos: {$result['skipped']}")
            ->success()
            ->send();

        // Refrescar propuestas tras aplicar
        $this->buscarPropuestas($matcher);
    }

    /**
     * @return list<array{field: string, label: string}>
     */
    public function documentSlotLabels(): array
    {
        $labels = VentaDocumentUpload::documentFieldLabels();

        return array_map(
            fn (string $field): array => [
                'field' => $field,
                'label' => $labels[$field] ?? $field,
            ],
            OrphanDocumentMatcher::documentFields(),
        );
    }

    /**
     * Resumen de slots vacíos en contratos recuperados (sin inventariar disco).
     *
     * @return list<array{venta_id: int, nro: string, cliente: string, pendientes: string}>
     */
    public function recoveredPendingPreview(): array
    {
        $matcher = app(OrphanDocumentMatcher::class);
        $ventas = $matcher->resolveTargetVentas(null, null, true);
        $rows = [];

        foreach (array_slice($ventas, 0, 40) as $venta) {
            /** @var Venta $venta */
            $venta->loadMissing('customer');
            $empty = $matcher->emptySlots($venta);
            if ($empty === []) {
                continue;
            }
            $customer = $venta->customer;
            $rows[] = [
                'venta_id' => $venta->id,
                'nro' => (string) ($venta->nro_contr_adm ?: '—'),
                'cliente' => $customer
                    ? trim(($customer->first_names ?? '').' '.($customer->last_names ?? ''))
                    : '—',
                'pendientes' => implode(', ', $empty),
            ];
        }

        return $rows;
    }
}
