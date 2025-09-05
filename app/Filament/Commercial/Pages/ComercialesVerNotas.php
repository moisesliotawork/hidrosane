<?php

namespace App\Filament\Commercial\Pages;

use App\Models\User;
use App\Models\Team;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;

class ComercialesVerNotas extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Reasignar Visitas';
    protected static ?string $title = 'Reasignar Visitas';
    protected static ?string $slug = 'comerciales-ver-notas';
    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.commercial.pages.comerciales-ver-notas';

    /* ====== Visibilidad / Acceso ====== */

    // 1) Ocultar del menú para quienes NO sean team_leader
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('team_leader');
    }

    // 2) Bloquear acceso directo por URL
    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('team_leader');
    }

    // (extra defensivo si tu versión no soporta canAccess)
    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('team_leader'), 403);
    }

    /* ====== Tabla ====== */

    public function table(Table $table): Table
    {
        $user = auth()->user();

        // Equipos (no borrados) que lidera el usuario
        $teamIds = Team::query()
            ->where('deleted', false)
            ->where('team_leader_id', $user->id)
            ->pluck('id');

        // Usuarios que pertenecen a esos equipos (comerciales + el propio líder)
        $query = User::query()
            ->where(function ($q) use ($teamIds, $user) {
                $q->whereHas('teams', fn($t) => $t->whereIn('teams.id', $teamIds))
                    ->orWhere('id', $user->id); // incluir al líder en la lista
            })
            ->role(['commercial', 'team_leader']);

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('empleado_id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->formatStateUsing(fn($record) => trim($record->name . ' ' . ($record->last_name ?? '')))
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_notas')
                    ->label('Ver notas')
                    ->button()
                    ->outlined()
                    ->color('primary')
                    ->url(fn($record) => \App\Filament\Commercial\Pages\NotasDeComercial::getUrl(
                        ['comercial_id' => $record->id],
                        panel: 'comercial'
                    ))
                    ->openUrlInNewTab(false),
            ])
            ->striped()
            ->paginated(true)
            ->defaultPaginationPageOption(25)
            ->defaultSort('name');
    }
}
