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
                    ->latestPerUser($panelId)              // 1 fila por usuario (último start_time en el panel)
                    ->with(['user', 'user.lastClosedWorkSession']) // eager load para evitar N+1
            )
            ->defaultSort('start_time', 'desc')
            ->columns([
                // Código / ID de empleado
                TextColumn::make('user.empleado_id')
                    ->label('Cod.')
                    ->sortable()
                    ->toggleable(),

                // Nombre y Apellido
                TextColumn::make('user.name')
                    ->label('Empleado')
                    ->formatStateUsing(
                        fn($record) => trim(($record->user->name ?? '') . ' ' . ($record->user->last_name ?? ''))
                    )
                    ->searchable()
                    ->sortable(),

                // Estado leyendo desde user.is_active
                TextColumn::make('estado')
                    ->label('Estado de fichaje')
                    ->state(fn(WorkSession $r) => ($r->user?->is_active ?? false) ? 'TRABAJANDO' : 'NO TRABAJANDO')
                    ->badge()
                    ->color(fn(WorkSession $r) => ($r->user?->is_active ?? false) ? 'success' : 'danger')
                    ->sortable(
                        query: fn($q, $dir) =>
                        // Ordena TRABAJANDO (true) arriba cuando desc
                        $q->orderByRaw('CASE WHEN EXISTS (SELECT 1 FROM users u WHERE u.id = work_sessions.user_id AND u.is_active = 1) THEN 0 ELSE 1 END ' . ($dir === 'asc' ? 'asc' : 'desc'))
                    ),

                // Fin (end_time) de la última sesión cerrada (no de la fila).
                TextColumn::make('fin_ultima_sesion')
                    ->label('Fin (última sesión)')
                    ->state(fn(WorkSession $r) => $r->user?->lastClosedWorkSession?->end_time)
                    ->dateTime('d/m/Y H:i:s')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->sortable(),

                // Ubicación del fichaje de ESTA fila (la sesión "última por panel" del join latestPerUser)
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
                    ->color(fn(WorkSession $r) => ($r->latitude && $r->longitude) ? 'info' : 'gray'),
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
