<?php

namespace App\Filament\HeadOfRoom\Pages;

use App\Models\TeleoperatorMonthlyStat;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class TeleoperadorasEstadisticasMensuales extends Page implements HasTable
{
    use InteractsWithTable;

    //JEFE DE SALA CARMEN SOLICITA OCULTAR 
    // ESTE RESOURCE DE MANERA TEMPORAL, con esta funcion ocultaremos: //
public static function shouldRegisterNavigation(): bool
{
    return false;
}
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Estad.TeleOp';
    protected static ?string $slug = 'teleoperadoras-estadisticas-mensuales';

    protected static string $view = 'filament.jefe-sala.pages.teleoperadoras-estadisticas-mensuales';

    public function getTitle(): string
    {
        return 'Estadísticas mensuales de teleoperadoras';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TeleoperatorMonthlyStat::query()
                    ->with('teleoperator')
            )
            ->defaultSort('year', 'desc')
            ->defaultSort('month', 'desc')
            ->columns([
                // Nombre teleoperadora (búsqueda por nombre / apellido / empleado_id)
                Tables\Columns\TextColumn::make('teleoperator.display_name')
                    ->label('Teleoperadora')
                    ->sortable()
                    ->searchable([
                        'teleoperator.name',
                        'teleoperator.last_name',
                        'teleoperator.empleado_id',
                    ]),

                Tables\Columns\TextColumn::make('periodo')
                    ->label('Periodo')
                    ->sortable(
                        query: fn(Builder $query, string $direction) =>
                        $query->orderBy('year', $direction)
                            ->orderBy('month', $direction)
                    ),

                Tables\Columns\TextColumn::make('confirmadas')
                    ->label('CONFIRMADAS')
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ventas')
                    ->label('VENTAS')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('nulas')
                    ->label('NULAS')
                    ->badge()
                    ->color('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('producidas')
                    ->label('Produccion')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('vta_conf')
                    ->label('Vtas/Conf.')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pct_conf')
                    ->label('% CONF')
                    ->sortable()
                    ->badge()
                    ->color(fn($state) => match (true) {
                        $state === null => 'gray',
                        $state < 40 => 'danger',  // rojo
                        $state < 70 => 'warning', // amarillo
                        default => 'success', // verde
                    })
                    ->formatStateUsing(
                        fn($state) =>
                        $state === null
                        ? '-'
                        : number_format($state, 2) . ' %'
                    ),

            ])
            ->filters([
                // Filtro por año / mes
                Filter::make('periodo')
                    ->form([
                        Select::make('year')
                            ->label('Año')
                            ->options(
                                TeleoperatorMonthlyStat::query()
                                    ->select('year')
                                    ->distinct()
                                    ->orderByDesc('year')
                                    ->pluck('year', 'year')
                            )
                            ->native(false),

                        Select::make('month')
                            ->label('Mes')
                            ->options([
                                1 => '01',
                                2 => '02',
                                3 => '03',
                                4 => '04',
                                5 => '05',
                                6 => '06',
                                7 => '07',
                                8 => '08',
                                9 => '09',
                                10 => '10',
                                11 => '11',
                                12 => '12',
                            ])
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['year'] ?? null,
                                fn(Builder $q, $year) => $q->where('year', $year)
                            )
                            ->when(
                                $data['month'] ?? null,
                                fn(Builder $q, $month) => $q->where('month', $month)
                            );
                    }),
            ])
            ->searchPlaceholder('Buscar teleoperadora...')
            ->striped(); // opcional: filas rayadas
    }
}
