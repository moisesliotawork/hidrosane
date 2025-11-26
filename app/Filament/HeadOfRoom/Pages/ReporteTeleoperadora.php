<?php

namespace App\Filament\HeadOfRoom\Pages;

use App\Enums\EstadoTerminal;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ReporteTeleoperadora extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-phone-arrow-up-right';
    protected static ?string $navigationLabel = 'Reporte Teleoperadora';
    protected static ?string $title = 'Reporte Teleoperadora';
    protected static ?string $navigationGroup = 'Reportes';

    protected static string $view = 'filament.jefe-sala.pages.reporte-teleoperadora';

    public function table(Table $table): Table
    {
        $now = now();
        $start = $now->copy()->subMonth()->startOfMonth();  // mes pasado (por defecto)
        $end = $now->copy()->subMonth()->endOfMonth();

        return $table
            ->query(
                User::query()
                    ->role(['teleoperator', 'head_of_room'])
                    ->withCount([
                        'notasTeleoperadora as confirmadas' => fn(Builder $q) =>
                            $q->whereBetween('fecha_declaracion', [$start, $end])
                                ->where('estado_terminal', EstadoTerminal::CONFIRMADO->value),

                        'notasTeleoperadora as ventas' => fn(Builder $q) =>
                            $q->whereBetween('fecha_declaracion', [$start, $end])
                                ->where('estado_terminal', EstadoTerminal::VENTA->value),
                    ])
            )
            ->filters([
                Filter::make('periodo')
                    ->label('Periodo')
                    ->form([
                        Select::make('tipo_periodo')
                            ->label('Tipo de periodo')
                            ->options([
                                'mes_actual' => 'Mes actual',
                                'mes_pasado' => 'Mes pasado',
                                'hace_dos_meses' => 'Hace dos meses',
                                'rango' => 'Rango personalizado',
                            ])
                            ->default('mes_pasado')
                            ->reactive(),

                        DatePicker::make('from')
                            ->label('Desde'),

                        DatePicker::make('to')
                            ->label('Hasta'),
                    ])
                    ->default(function () use ($now) {
                        return [
                            'tipo_periodo' => 'mes_pasado',
                            // valores por defecto para cuando elijas "rango"
                            'from' => $now->copy()->startOfMonth(),
                            'to' => $now,
                        ];
                    })
                    ->query(function (Builder $query, array $data) {
                        $now = now();
                        $tipo = $data['tipo_periodo'] ?? 'mes_pasado';

                        // 🔹 Determinar rango según el tipo seleccionado
                        switch ($tipo) {
                            case 'mes_actual':
                                $start = $now->copy()->startOfMonth();
                                $end = $now->copy()->endOfDay();
                                break;

                            case 'mes_pasado':
                                $start = $now->copy()->subMonth()->startOfMonth();
                                $end = $now->copy()->subMonth()->endOfMonth();
                                break;

                            case 'hace_dos_meses':
                                $start = $now->copy()->subMonths(2)->startOfMonth();
                                $end = $now->copy()->subMonths(2)->endOfMonth();
                                break;

                            case 'rango':
                                $start = !empty($data['from'])
                                    ? Carbon::parse($data['from'])->startOfDay()
                                    : $now->copy()->startOfMonth();

                                $end = !empty($data['to'])
                                    ? Carbon::parse($data['to'])->endOfDay()
                                    : $now->copy()->endOfDay();
                                break;

                            default:
                                $start = $now->copy()->subMonth()->startOfMonth();
                                $end = $now->copy()->subMonth()->endOfMonth();
                                break;
                        }

                        // 🔁 Recalculamos los contadores según el rango elegido
                        $query->withCount([
                            'notasTeleoperadora as confirmadas' => fn(Builder $q) =>
                                $q->whereBetween('fecha_declaracion', [$start, $end])
                                    ->where('estado_terminal', EstadoTerminal::CONFIRMADO->value),

                            'notasTeleoperadora as ventas' => fn(Builder $q) =>
                                $q->whereBetween('fecha_declaracion', [$start, $end])
                                    ->where('estado_terminal', EstadoTerminal::VENTA->value),
                        ]);
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('empleado_id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Teleoperadora')
                    ->getStateUsing(fn(User $record) => trim($record->name . ' ' . $record->last_name))
                    ->searchable(['name', 'last_name']),

                Tables\Columns\TextColumn::make('confirmadas')
                    ->label('Confirmadas')
                    ->badge()
                    ->color('warning')
                    ->alignCenter()
                    ->sortable()
                    // ✅ Fuerza mostrar 0 si viene null
                    ->getStateUsing(fn(User $record) => $record->confirmadas ?? 0),

                Tables\Columns\TextColumn::make('ventas')
                    ->label('Ventas')
                    ->badge()
                    ->color('success')
                    ->alignCenter()
                    ->sortable()
                    ->getStateUsing(fn(User $record) => $record->ventas ?? 0),
            ])
            ->defaultSort('empleado_id');
    }
}
