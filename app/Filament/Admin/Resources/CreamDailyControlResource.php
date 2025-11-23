<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CreamDailyControlResource\Pages;
use App\Filament\Admin\Resources\CreamDailyControlResource\RelationManagers;
use App\Models\CreamDailyControl;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;

class CreamDailyControlResource extends Resource
{
    protected static ?string $model = CreamDailyControl::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';
    protected static ?string $navigationLabel = 'Control de Cremas';
    protected static ?string $pluralModelLabel = 'Control diario de cremas';
    protected static ?string $navigationGroup = 'Comerciales';

    public static function form(Form $form): Form
    {
        // No necesitamos formulario (no vamos a crear/editar desde aquí)
        return $form;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('comercial.empleado_id')
                    ->label('ID Comercial')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('comercial.full_name')
                    ->label('Comercial')
                    ->searchable(['comercial.name', 'comercial.last_name', 'comercial.empleado_id'])
                    ->sortable(),


                Tables\Columns\TextColumn::make('assigned')
                    ->label('Asignadas hoy')
                    ->color('success')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_disponible')
                    ->label('Total disponible')
                    ->getStateUsing(fn() => 5)
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
                            ->default(now()->toDateString())
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date'] ?? null,
                                fn(Builder $q, $date) => $q->whereDate('date', $date),
                            );
                    }),
            ])

            ->actions([

            ])
            ->bulkActions([

            ])

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
