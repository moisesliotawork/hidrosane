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
        return $table
            ->query(
                User::query()
                    ->role(['teleoperator', 'head_of_room'])
            )
            ->filters([
                Filter::make('rango')
                    ->label('Rango de fechas')
                    ->form([
                        DatePicker::make('from')
                            ->label('Desde'),
                        DatePicker::make('to')
                            ->label('Hasta'),
                    ])
                    // 🔸 Por defecto: solo HOY
                    ->default(function () {
                        $today = now()->toDateString();

                        return [
                            'from' => $today,
                            'to' => $today,
                        ];
                    })
                    ->query(function (Builder $query, array $data) {
                        // Si el usuario no coloca nada, usamos HOY
                        $today = now();

                        $from = !empty($data['from'])
                            ? Carbon::parse($data['from'])->startOfDay()
                            : $today->copy()->startOfDay();

                        $to = !empty($data['to'])
                            ? Carbon::parse($data['to'])->endOfDay()
                            : $today->copy()->endOfDay();

                        $query->withCount([
                            'notasTeleoperadora as confirmadas' => fn(Builder $q) =>
                                $q->whereBetween('fecha_declaracion', [$from, $to])
                                    ->where('estado_terminal', EstadoTerminal::CONFIRMADO->value),

                            'notasTeleoperadora as ventas' => fn(Builder $q) =>
                                $q->whereBetween('fecha_declaracion', [$from, $to])
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
