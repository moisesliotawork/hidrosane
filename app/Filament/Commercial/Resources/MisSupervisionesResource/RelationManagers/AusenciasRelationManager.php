<?php

namespace App\Filament\Commercial\Resources\MisSupervisionesResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Colors\Color;

class AusenciasRelationManager extends RelationManager
{
    // Debe coincidir con el nombre de la relación en tu modelo Note
    protected static string $relationship = 'ausencias';

    protected static ?string $title = 'Historial de Ausentes';
    protected static ?string $recordTitleAttribute = 'fecha';

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Sin registros de ausencias')
            ->emptyStateDescription('Cuando marques la nota como AUSENTE, se guardará aquí el historial.')
            ->paginated([10, 25, 50, 'all'])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(function ($query) {
                $userId = auth()->id();
                $query->where(function ($q) use ($userId) {
                    $q->whereNull('autor_id')
                        ->orWhere('autor_id', $userId);
                });
            })
            ->columns([
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->badge()
                    ->color(Color::Gray)
                    ->sortable(),

                Tables\Columns\TextColumn::make('hora')
                    ->label('Hora')
                    ->badge()
                    ->color(Color::Blue)
                    ->sortable(),

                Tables\Columns\TextColumn::make('latitud')
                    ->label('Latitud')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('longitud')
                    ->label('Longitud')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('mapa')
                    ->label('Mapa')
                    ->state(fn($record) => $record->latitud && $record->longitud
                        ? "{$record->latitud}, {$record->longitud}"
                        : '—')
                    ->url(fn($record) => ($record->latitud && $record->longitud)
                        ? "https://www.google.com/maps?q={$record->latitud},{$record->longitud}"
                        : null, shouldOpenInNewTab: true)
                    ->badge()
                    ->color(fn($state) => $state === '—' ? Color::Gray : Color::Green),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([])   // solo lectura
            ->actions([])         // sin acciones por fila
            ->bulkActions([]);    // sin acciones masivas
    }
}
