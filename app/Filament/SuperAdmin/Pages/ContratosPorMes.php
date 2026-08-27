<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Livewire\SuperAdmin\VerDatosContratoSearch;
use App\Models\ContratoMesVariacionItem;
use App\Models\ContratoRecuperado;
use App\Models\Venta;
use App\Support\ContratosPorMesStats;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ContratosPorMes extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Contratos/MES';

    protected static ?string $navigationGroup = 'RECUPERACION CONTRATOS';

    protected static ?string $title = 'Contratos/MES';

    protected static ?string $slug = 'contratos-por-mes';

    protected static string $view = 'filament.superAdmin.pages.contratos-por-mes';

    protected static ?int $navigationSort = -11;

    public static function getNavigationBadge(): ?string
    {
        return (string) Venta::query()->count();
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'info';
    }

    public bool $variacionesOpen = false;

    public bool $resumenOpen = false;

    public bool $numerosOpen = false;

    public bool $soloNumerosOpen = true;

    public bool $recuperadosOpen = true;

    public string $nuevoContratoRecuperado = '';

    public string $buscarNroContratoAdmin = '';

    /** Filtro sección Variaciones */
    public ?string $varSelectedYearMonth = null;

    public bool $varShowAllMonths = false;

    public int $varSelectedYear;

    /** Filtro sección Resumen */
    public ?string $resSelectedYearMonth = null;

    public bool $resShowAllMonths = false;

    public int $resSelectedYear;

    /** Filtro sección Nº contratos admin */
    public ?string $numSelectedYearMonth = null;

    public bool $numShowAllMonths = false;

    public int $numSelectedYear;

    /** Filtro sección Solo números */
    public ?string $soloSelectedYearMonth = null;

    public bool $soloShowAllMonths = false;

    public int $soloSelectedYear;

    public function mount(): void
    {
        $today = Carbon::today();
        $this->varSelectedYear = (int) $today->year;
        $this->varSelectedYearMonth = null;
        $this->varShowAllMonths = true;

        $this->resSelectedYear = (int) $today->year;
        $this->resSelectedYearMonth = null;
        $this->resShowAllMonths = true;

        $this->numSelectedYear = (int) $today->year;
        $this->numSelectedYearMonth = null;
        $this->numShowAllMonths = true;

        $this->soloSelectedYear = (int) $today->year;
        $this->soloSelectedYearMonth = null;
        $this->soloShowAllMonths = true;

        $this->buscarNroContratoAdmin = (string) session(VerDatosContratoSearch::SESSION_NRO, '');
    }

    /**
     * @return Collection<int, ContratoMesVariacionItem>
     */
    public function getVariacionItemsProperty(): Collection
    {
        $items = ContratosPorMesStats::variationDetailItems();

        if ($this->varShowAllMonths || blank($this->varSelectedYearMonth)) {
            return $items;
        }

        return $items
            ->filter(fn (ContratoMesVariacionItem $item) => $item->mes_key === $this->varSelectedYearMonth)
            ->values();
    }

    /**
     * Soft-delete / borrado → menos contratos.
     *
     * @return Collection<int, ContratoMesVariacionItem>
     */
    public function getContratosQuitadosProperty(): Collection
    {
        return $this->variacionItems
            ->filter(fn (ContratoMesVariacionItem $item) => in_array($item->estado, [
                ContratoMesVariacionItem::ESTADO_SOFT_DELETE,
                ContratoMesVariacionItem::ESTADO_BORRADO,
            ], true))
            ->values();
    }

    /**
     * Nuevo / restaurado → más contratos.
     *
     * @return Collection<int, ContratoMesVariacionItem>
     */
    public function getContratosAgregadosProperty(): Collection
    {
        return $this->variacionItems
            ->filter(fn (ContratoMesVariacionItem $item) => in_array($item->estado, [
                ContratoMesVariacionItem::ESTADO_NUEVO,
                ContratoMesVariacionItem::ESTADO_RESTAURADO,
            ], true))
            ->values();
    }

    /**
     * @return Collection<int, object{mes_key: string, contratos: list<object{id: int, nro_contr_adm: string}>, total: int}>
     */
    public function getNumerosAdminPorMesProperty(): Collection
    {
        $mes = (! $this->numShowAllMonths && filled($this->numSelectedYearMonth))
            ? $this->numSelectedYearMonth
            : null;

        return ContratosPorMesStats::adminContractNumbersByMonth($mes);
    }

    /**
     * @return list<string>
     */
    public function getSoloNumerosContratosProperty(): array
    {
        $mes = (! $this->soloShowAllMonths && filled($this->soloSelectedYearMonth))
            ? $this->soloSelectedYearMonth
            : null;

        return ContratosPorMesStats::adminContractNumbersOnly($mes);
    }

    /**
     * @return list<string>
     */
    public function getContratosRecuperadosNumerosProperty(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('contratos_recuperados')) {
            return [];
        }

        return ContratoRecuperado::query()
            ->orderBy('nro_contr_adm')
            ->pluck('nro_contr_adm')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->values()
            ->all();
    }

    public function addContratoRecuperado(): void
    {
        $nro = trim($this->nuevoContratoRecuperado);
        if ($nro === '') {
            Notification::make()
                ->title('Indica un nº de contrato admin')
                ->warning()
                ->send();

            return;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('contratos_recuperados')) {
            Notification::make()
                ->title('Tabla no disponible')
                ->body('Ejecuta las migraciones pendientes.')
                ->danger()
                ->send();

            return;
        }

        $exists = ContratoRecuperado::query()
            ->where('nro_contr_adm', $nro)
            ->exists();

        if ($exists) {
            Notification::make()
                ->title('Ya está registrado')
                ->body("El contrato {$nro} ya figura en recuperados.")
                ->warning()
                ->send();

            return;
        }

        ContratoRecuperado::query()->create([
            'nro_contr_adm' => $nro,
            'created_by_user_id' => auth()->id(),
        ]);

        $this->nuevoContratoRecuperado = '';
        $this->recuperadosOpen = true;

        Notification::make()
            ->title('Contrato recuperado añadido')
            ->body($nro)
            ->success()
            ->send();
    }

    public function updatedBuscarNroContratoAdmin(mixed $value): void
    {
        $this->buscarNroContratoAdmin = trim((string) $value);
        session([VerDatosContratoSearch::SESSION_NRO => $this->buscarNroContratoAdmin]);
    }

    public function buscarDatosContrato(): void
    {
        $nro = trim($this->buscarNroContratoAdmin);
        session([VerDatosContratoSearch::SESSION_NRO => $nro]);

        if ($nro === '') {
            Notification::make()
                ->title('Indica un nº de contrato admin')
                ->warning()
                ->send();

            return;
        }

        $venta = \App\Models\Venta::query()
            ->withTrashed()
            ->where('nro_contr_adm', $nro)
            ->orderByDesc('id')
            ->first();

        if (! $venta) {
            Notification::make()
                ->title('Contrato no encontrado')
                ->body("No hay un contrato con nº admin «{$nro}».")
                ->danger()
                ->send();

            return;
        }

        $this->redirect(\App\Filament\SuperAdmin\Resources\VentaResource::getUrl('edit', ['record' => $venta]));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->resumenQuery())
            ->columns([
                TextColumn::make('mes_key')
                    ->label('Mes')
                    ->formatStateUsing(fn ($state): string => ContratosPorMesStats::labelForMonthKey((string) $state))
                    ->sortable()
                    ->weight(FontWeight::Bold),

                TextColumn::make('total')
                    ->label('Nº de contratos')
                    ->numeric()
                    ->alignCenter()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->extraAttributes(['class' => 'contratos-mes-total-cell'])
                    ->summarize(
                        Sum::make()
                            ->label('')
                            ->formatStateUsing(
                                fn ($state): string => 'TOTAL ' . number_format((int) $state, 0, ',', '.')
                            )
                    ),

                TextColumn::make('hay_cambio')
                    ->label('¿HAY CAMBIO?')
                    ->alignCenter()
                    ->getStateUsing(fn ($record): int => (int) data_get($record, 'variacion', 0))
                    ->formatStateUsing(fn (int $state) => ContratosPorMesStats::hayCambioHtml($state))
                    ->html(),

                TextColumn::make('variacion')
                    ->label('VARIACIÓN')
                    ->alignCenter()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => ContratosPorMesStats::variacionHtml((int) $state))
                    ->html(),
            ])
            ->defaultSort('mes_key', 'desc')
            ->paginated(false)
            ->striped();
    }

    protected function resumenQuery(): Builder
    {
        $query = ContratosPorMesStats::query();

        if ($this->resShowAllMonths || blank($this->resSelectedYearMonth)) {
            return $query;
        }

        return $query->where('contratos_mes_baselines.mes_key', $this->resSelectedYearMonth);
    }

    public function getTableRecordKey(mixed $record): string
    {
        $mesKey = is_array($record)
            ? ($record['mes_key'] ?? null)
            : data_get($record, 'mes_key');

        return 'mes-' . ($mesKey ?? 'unknown');
    }

    public function selectVarMonth(int $month): void
    {
        $month = max(1, min(12, $month));
        $this->varSelectedYearMonth = sprintf('%04d-%02d', $this->varSelectedYear, $month);
        $this->varShowAllMonths = false;
        $this->variacionesOpen = true;
    }

    public function showAllVarMonths(): void
    {
        $this->varShowAllMonths = true;
        $this->varSelectedYearMonth = null;
        $this->variacionesOpen = true;
    }

    public function updatedVarSelectedYear(mixed $value): void
    {
        $this->varSelectedYear = (int) $value;

        if (! $this->varShowAllMonths && filled($this->varSelectedYearMonth)) {
            try {
                $month = (int) Carbon::createFromFormat('Y-m', $this->varSelectedYearMonth)->month;
            } catch (\Throwable) {
                $month = (int) Carbon::today()->month;
            }

            $this->varSelectedYearMonth = sprintf('%04d-%02d', $this->varSelectedYear, $month);
        }

        $this->variacionesOpen = true;
    }

    public function selectResMonth(int $month): void
    {
        $month = max(1, min(12, $month));
        $this->resSelectedYearMonth = sprintf('%04d-%02d', $this->resSelectedYear, $month);
        $this->resShowAllMonths = false;
        $this->resetTable();
    }

    public function showAllResMonths(): void
    {
        $this->resShowAllMonths = true;
        $this->resSelectedYearMonth = null;
        $this->resetTable();
    }

    public function updatedResSelectedYear(mixed $value): void
    {
        $this->resSelectedYear = (int) $value;

        if (! $this->resShowAllMonths && filled($this->resSelectedYearMonth)) {
            try {
                $month = (int) Carbon::createFromFormat('Y-m', $this->resSelectedYearMonth)->month;
            } catch (\Throwable) {
                $month = (int) Carbon::today()->month;
            }

            $this->resSelectedYearMonth = sprintf('%04d-%02d', $this->resSelectedYear, $month);
        }

        $this->resetTable();
    }

    public function selectNumMonth(int $month): void
    {
        $month = max(1, min(12, $month));
        $this->numSelectedYearMonth = sprintf('%04d-%02d', $this->numSelectedYear, $month);
        $this->numShowAllMonths = false;
        $this->numerosOpen = true;
    }

    public function showAllNumMonths(): void
    {
        $this->numShowAllMonths = true;
        $this->numSelectedYearMonth = null;
        $this->numerosOpen = true;
    }

    public function updatedNumSelectedYear(mixed $value): void
    {
        $this->numSelectedYear = (int) $value;

        if (! $this->numShowAllMonths && filled($this->numSelectedYearMonth)) {
            try {
                $month = (int) Carbon::createFromFormat('Y-m', $this->numSelectedYearMonth)->month;
            } catch (\Throwable) {
                $month = (int) Carbon::today()->month;
            }

            $this->numSelectedYearMonth = sprintf('%04d-%02d', $this->numSelectedYear, $month);
        }

        $this->numerosOpen = true;
    }

    public function selectSoloMonth(int $month): void
    {
        $month = max(1, min(12, $month));
        $this->soloSelectedYearMonth = sprintf('%04d-%02d', $this->soloSelectedYear, $month);
        $this->soloShowAllMonths = false;
        $this->soloNumerosOpen = true;
    }

    public function showAllSoloMonths(): void
    {
        $this->soloShowAllMonths = true;
        $this->soloSelectedYearMonth = null;
        $this->soloNumerosOpen = true;
    }

    public function updatedSoloSelectedYear(mixed $value): void
    {
        $this->soloSelectedYear = (int) $value;

        if (! $this->soloShowAllMonths && filled($this->soloSelectedYearMonth)) {
            try {
                $month = (int) Carbon::createFromFormat('Y-m', $this->soloSelectedYearMonth)->month;
            } catch (\Throwable) {
                $month = (int) Carbon::today()->month;
            }

            $this->soloSelectedYearMonth = sprintf('%04d-%02d', $this->soloSelectedYear, $month);
        }

        $this->soloNumerosOpen = true;
    }

    public function soloNumerosPdfUrl(): string
    {
        $params = [];
        if (! $this->soloShowAllMonths && filled($this->soloSelectedYearMonth)) {
            $params['mes'] = $this->soloSelectedYearMonth;
        } else {
            $params['todos'] = 1;
        }

        return route('contratos-por-mes.solo-numeros.pdf', $params);
    }

    public function numerosPdfUrl(): string
    {
        $params = [];
        if (! $this->numShowAllMonths && filled($this->numSelectedYearMonth)) {
            $params['mes'] = $this->numSelectedYearMonth;
        } else {
            $params['todos'] = 1;
        }

        return route('contratos-por-mes.numeros.pdf', $params);
    }

    public static function contratosDelMes(string $mesKey): int
    {
        return Venta::query()
            ->withoutTrashed()
            ->whereNotNull('fecha_venta')
            ->whereRaw("DATE_FORMAT(fecha_venta, '%Y-%m') = ?", [$mesKey])
            ->count();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('previewPdf')
                ->label('Previsualizar PDF')
                ->icon('heroicon-o-eye')
                ->color('warning')
                ->url(function (): string {
                    $params = [];
                    if (! $this->resShowAllMonths && filled($this->resSelectedYearMonth)) {
                        $params['mes'] = $this->resSelectedYearMonth;
                    } else {
                        $params['todos'] = 1;
                    }

                    return route('contratos-por-mes.pdf', $params);
                })
                ->openUrlInNewTab(),

            Actions\Action::make('freezeBaselines')
                ->label('Fijar base actual')
                ->icon('heroicon-o-lock-closed')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Fijar totales actuales como base')
                ->modalDescription('La VARIACIÓN de todos los meses pasará a 0 y se limpiará el detalle de variaciones. Úsalo tras correcciones intencionadas.')
                ->action(function (): void {
                    $n = ContratosPorMesStats::freezeBaselinesToCurrent();

                    Notification::make()
                        ->title('Base actualizada')
                        ->body("Se fijaron {$n} mes(es) al total actual.")
                        ->success()
                        ->send();

                    $this->resetTable();
                }),
        ];
    }
}
