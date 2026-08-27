<?php

namespace App\Filament\SuperAdmin\Resources\ReasignadoOkResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\NoteReassignmentLog;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Notas Reasignadas';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('id', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('note.nro_nota')
                    ->label('Nro. Nota')
                    ->badge()
                    ->color(Color::Gray)
                    ->sortable(),

                Tables\Columns\TextColumn::make('cliente')
                    ->label('Cliente')
                    ->weight(FontWeight::Bold)
                    ->getStateUsing(fn(NoteReassignmentLog $record) =>
                        mb_strtoupper($record->note?->customer?->name ?? '—', 'UTF-8')
                    ),

                Tables\Columns\TextColumn::make('note.fuente')
                    ->label('Fuente')
                    ->badge()
                    ->color(Color::Orange)
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '—';
                        if ($state instanceof \App\Enums\FuenteNotas) {
                            return $state->getLabel();
                        }
                        $enum = \App\Enums\FuenteNotas::tryFrom((string) $state);
                        return $enum ? $enum->getLabel() : $state;
                    }),

                Tables\Columns\TextColumn::make('note.status')
                    ->label('Estado')
                    ->badge()
                    ->color(function ($state) {
                        if (!$state) return Color::Gray;
                        if ($state instanceof \App\Enums\NoteStatus) {
                            return $state->getColor();
                        }
                        $enum = \App\Enums\NoteStatus::tryFrom((string) $state);
                        return $enum ? $enum->getColor() : 'gray';
                    })
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '—';
                        if ($state instanceof \App\Enums\NoteStatus) {
                            return $state->label();
                        }
                        $enum = \App\Enums\NoteStatus::tryFrom((string) $state);
                        return $enum ? $enum->label() : $state;
                    }),

                Tables\Columns\TextColumn::make('comercial_actual')
                    ->label('Comercial actual')
                    ->badge()
                    ->color(Color::Blue)
                    ->getStateUsing(fn(NoteReassignmentLog $record) => $record->note?->comercial?->empleado_id ?? '—'),

                Tables\Columns\TextColumn::make('fromComercial.empleado_id')
                    ->label('Comercial anterior')
                    ->badge()
                    ->color(Color::Red)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('nro_cliente')
                    ->label('Nro Cliente')
                    ->badge()
                    ->color(Color::Indigo)
                    ->getStateUsing(function (NoteReassignmentLog $record) {
                        return $record->note?->customer?->latestVenta?->nro_cliente_adm ?? '—';
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
