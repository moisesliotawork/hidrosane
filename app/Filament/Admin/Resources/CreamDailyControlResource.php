<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CreamDailyControlResource\Pages;
use App\Models\CreamDailyControl;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Carbon\Carbon;

class CreamDailyControlResource extends Resource
{
    protected static ?string $model = CreamDailyControl::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';
    protected static ?string $navigationLabel = 'Control de Cremas';
    protected static ?string $pluralModelLabel = 'Control diario de cremas';
    protected static ?string $navigationGroup = 'Comerciales';

    public static function form(Form $form): Form
    {
        return $form;
    }

    /**
     * Por si acaso alguna otra parte añade scopes raros,
     * dejamos la query base lo más limpia posible.
     */
    public static function getEloquentQuery(): Builder
    {
        return static::getModel()::query();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                // ───── Comercial ID protegido ─────
                Tables\Columns\TextColumn::make('comercial_id')
                    ->label('ID Comercial')
                    ->formatStateUsing(
                        fn($state, CreamDailyControl $record) =>
                        $record->comercial?->empleado_id ?? $record->comercial_id
                    )
                    ->alignCenter()
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                // ───── Nombre del comercial protegido ─────
                Tables\Columns\TextColumn::make('comercial_name')
                    ->label('Comercial')
                    ->getStateUsing(function (CreamDailyControl $record) {
                        $com = $record->comercial;

                        if (!$com) {
                            return 'Sin comercial';
                        }

                        // Usa accessor full_name si lo tienes; si no, nombre + apellido
                        return method_exists($com, 'getFullNameAttribute')
                            ? $com->full_name
                            : trim(($com->name ?? '') . ' ' . ($com->last_name ?? ''));
                    })
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('assigned')
                    ->label('Asignadas hoy')
                    ->color('success')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_disponible')
                    ->label('Total disponible')
                    ->getStateUsing(fn() => 8)
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('delivered')
                    ->label('Entregadas hoy')
                    ->color('warning')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining')
                    ->label('Restantes hoy')
                    ->color('orange')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('next_day_to_assign')
                    ->label('Para mañana')
                    ->color('info')
                    ->badge()
                    ->sortable(),
            ])

            ->defaultSort('date', 'desc')

            ->filters([
                Filter::make('date')
                    ->label('Fecha')
                    ->form([
                        DatePicker::make('date')
                            ->label('Fecha')
                            // valor por defecto: HOY (solo fecha)
                            ->default(now()->toDateString())
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['date'] ?? null,
                            function (Builder $q, $date) {
                                // Normalizamos a solo fecha, por si viene con hora
                                $dateOnly = Carbon::parse($date)->toDateString();

                                return $q->whereDate('date', $dateOnly);
                            }
                        );
                    })
                    ->default([
                        'date' => now()->toDateString(), // filtro aplicado por defecto a HOY
                    ])
            ])

            ->actions([])
            ->bulkActions([])
            ->searchDebounce(500);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreamDailyControls::route('/'),
            'create' => Pages\CreateCreamDailyControl::route('/create'),
            'edit' => Pages\EditCreamDailyControl::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDeleted(): bool
    {
        return false;
    }

    public static function canEdited(): bool
    {
        return false;
    }
}
