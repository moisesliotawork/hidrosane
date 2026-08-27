<?php

namespace App\Filament\SuperAdmin\Resources\InfoUserResource\Pages;

use App\Filament\SuperAdmin\Resources\InfoUserResource;
use App\Models\User;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListInfoUsers extends ListRecords
{
    protected static string $resource = InfoUserResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'todos' => Tab::make('Todos')
                ->icon('heroicon-o-users')
                ->badge(User::count())
                ->badgeColor('gray'),

            'comerciales' => Tab::make('Comerciales')
                ->icon('heroicon-o-briefcase')
                ->badge(User::whereHas('roles', fn(Builder $q) => $q->where('name', 'commercial'))->count())
                ->badgeColor('success')
                ->modifyQueryUsing(
                    fn(Builder $query) => $query->whereHas('roles', fn(Builder $q) => $q->where('name', 'commercial'))
                        ->orderBy('empleado_id', 'asc')
                ),

            'teleoperadoras' => Tab::make('Teleoperadoras')
                ->icon('heroicon-o-phone')
                ->badge(User::whereHas('roles', fn(Builder $q) => $q->where('name', 'teleoperator'))->count())
                ->badgeColor('info')
                ->modifyQueryUsing(
                    fn(Builder $query) => $query->whereHas('roles', fn(Builder $q) => $q->where('name', 'teleoperator'))
                        ->orderBy('empleado_id', 'asc')
                ),

            'jefes_equipo' => Tab::make('Jefes de Equipo')
                ->icon('heroicon-o-star')
                ->badge(User::whereHas('roles', fn(Builder $q) => $q->where('name', 'team_leader'))->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(
                    fn(Builder $query) => $query->whereHas('roles', fn(Builder $q) => $q->where('name', 'team_leader'))
                        ->orderBy('empleado_id', 'asc')
                ),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'todos';
    }
}
