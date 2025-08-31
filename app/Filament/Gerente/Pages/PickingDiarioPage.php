<?php

namespace App\Filament\Gerente\Pages;

use App\Models\PickingDiario;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Actions;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use DateTimeInterface;

class PickingDiarioPage extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationLabel = 'HOJA CARGA REPARTO';
    protected static ?string $title = 'HOJA CARGA REPARTO';
    protected static ?string $slug = 'picking-diario';
    protected static string $view = 'filament.pages.picking-diario';
    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => PickingDiario::query())
            ->columns([
                TextColumn::make('producto.nombre')
                    ->label('Producto')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('cantidad_total')
                    ->label('Cantidad')
                    ->sortable()
                    ->alignRight(),

                ToggleColumn::make('entregado')
                    ->label('Entregado')
                    ->afterStateUpdated(function (PickingDiario $record, bool $state) {
                        // cuando se marca/desmarca, actualizamos metadatos
                        $record->entregado_at = $state ? now() : null;
                        $record->entregado_by = $state ? (auth()->id() ?: null) : null;
                        $record->save();
                    }),
            ])
            ->defaultSort('producto_id')
            ->filters([
                Filter::make('fecha')
                    ->label('Fecha')
                    ->form([
                        DatePicker::make('fecha')
                            ->label('Fecha')
                            ->default(today()->toDateString()) // 'YYYY-MM-DD'
                            ->required()
                            ->closeOnDateSelection()
                            ->live(), // refresca tabla al cambiar
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $date = $data['fecha'] ?? today()->toDateString();
                        return $query->where('fecha', $date); // columna DATE
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $d = $data['fecha'] ?? today()->toDateString();
                        return 'Fecha: ' . \Illuminate\Support\Carbon::parse($d)->format('d-m-Y');
                    })
                    ->default(true),
            ])
            ->striped()
            ->paginated([25, 50, 100])
            ->persistFiltersInSession(); // recuerda la fecha mientras navegas
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportPdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->action(function () {
                    // 1) Leer estado del filtro y normalizar
                    $state = $this->getTableFiltersForm()?->getState() ?? [];
                    $date = $this->normalizeDate($state['fecha'] ?? null);  // <-- string Y-m-d
        
                    // 2) Dataset
                    $rows = PickingDiario::with('producto')
                        ->where('fecha', $date)
                        ->get()
                        ->sortBy(fn($r) => mb_strtolower($r->producto->nombre ?? ''));

                    // 3) PDF
                    $pdf = Pdf::loadView('pdf.picking-diario', [
                        'fecha' => $date,   // string
                        'rows' => $rows,
                    ])->setPaper('a4', 'portrait');

                    $filename = 'hoja-carga-' . $date . '.pdf'; // ahora es string
        
                    return response()->streamDownload(
                        fn() => print ($pdf->output()),
                        $filename
                    );
                }),
        ];
    }

    protected function normalizeDate(mixed $val): string
    {
        if ($val instanceof DateTimeInterface) {
            return Carbon::instance($val)->toDateString();
        }

        if (is_string($val) && trim($val) !== '') {
            return Carbon::parse($val)->toDateString(); // acepta '31/08/2025', etc.
        }

        if (is_array($val)) {
            // Soporta estructuras: ['fecha'=>...], ['start'=>...], [0=>...], etc.
            foreach (['fecha', 'date', 'value', 'start', 0] as $key) {
                if (!empty($val[$key])) {
                    return $this->normalizeDate($val[$key]);
                }
            }
            $first = reset($val);
            if ($first) {
                return $this->normalizeDate($first);
            }
        }

        return today()->toDateString();
    }

}
