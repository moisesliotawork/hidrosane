<?php

namespace App\Filament\Admin\Resources;

use App\Models\WorkSession;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Facades\Filament;

class WorkStatusResource extends Resource
{
    protected static ?string $model = WorkSession::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Fichajes';
    protected static ?string $pluralModelLabel = 'Fichajes';
    protected static ?string $slug = 'fichajes';

    public static function table(Table $table): Table
    {
        $panelId = Filament::getCurrentPanel()->getId();

        return $table
            ->query(
                WorkSession::query()
                    ->latestPerUser($panelId)
                    ->with('user')
            )
            ->defaultSort('start_time', 'desc')
            ->columns([
                // Código / ID de empleado (ajústalo a tu campo)
                TextColumn::make('user.empleado_id')
                    ->label('Cod.')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('user.name')
                    ->label('Empleado')
                    ->formatStateUsing(
                        fn($record) =>
                        trim(($record->user->name ?? '') . ' ' . ($record->user->last_name ?? ''))
                    )
                    ->searchable()
                    ->sortable(),

                // Estado con badge
                TextColumn::make('estado')
                    ->label('Estado de fichaje')
                    ->state(fn(WorkSession $r) => $r->end_time ? 'NO TRABAJANDO' : 'TRABAJANDO')
                    ->badge()
                    ->color(fn(string $s) => $s === 'TRABAJANDO' ? 'success' : 'danger')
                    ->sortable(
                        query: fn($q, $dir) =>
                        $q->orderByRaw('CASE WHEN end_time IS NULL THEN 0 ELSE 1 END ' . ($dir === 'asc' ? 'asc' : 'desc'))
                    ),

                TextColumn::make('ultimo_fichaje')
                    ->label('Último fichaje')
                    ->state(fn(WorkSession $r) => $r->end_time ?? $r->start_time)
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('end_time')
                    ->label('Fin')
                    ->dateTime('d/m/Y H:i:s')
                    ->toggleable(isToggledHiddenByDefault: true),



                TextColumn::make('maps_link')
                    ->label('Lugar de fichaje')
                    ->getStateUsing(function (WorkSession $r) {
                        return ($r->latitude && $r->longitude) ? 'Ver lugar' : '—';
                    })
                    ->url(function (WorkSession $r) {
                        return ($r->latitude && $r->longitude)
                            ? "https://www.google.com/maps/search/?api=1&query={$r->latitude},{$r->longitude}"
                            : null;
                    }, shouldOpenInNewTab: true)
                    ->badge()
                    ->color(fn(WorkSession $r) => ($r->latitude && $r->longitude) ? 'info' : 'gray')

            ])
            ->filters([
                //
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50);
    }

    public static function getPages(): array
    {
        return [
            'index' => WorkStatusResource\Pages\ListWorkStatuses::route('/'),
        ];
    }
}
