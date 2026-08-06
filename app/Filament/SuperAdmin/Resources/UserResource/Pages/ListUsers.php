<?php

namespace App\Filament\SuperAdmin\Resources\UserResource\Pages;

use App\Filament\SuperAdmin\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'todos' => Tab::make('Todos')
                ->icon('heroicon-o-users')
                ->badge(User::count())
                ->badgeColor('gray'),

            'de_alta' => Tab::make('De Alta')
                ->icon('heroicon-o-check-circle')
                ->badge(User::whereNull('baja')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->whereNull('baja')
                ),

            'de_baja' => Tab::make('De Baja')
                ->icon('heroicon-o-x-circle')
                ->badge(User::whereNotNull('baja')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->whereNotNull('baja')
                ),

            'comerciales' => Tab::make('Comerciales')
                ->icon('heroicon-o-briefcase')
                ->badge(User::whereHas('roles', fn (Builder $q) => $q->where('name', 'commercial'))->whereNull('baja')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->whereHas('roles', fn (Builder $q) => $q->where('name', 'commercial'))
                        ->whereNull('baja')
                ),

            'jefes_equipo' => Tab::make('Jefes de Equipo')
                ->icon('heroicon-o-star')
                ->badge(User::whereHas('roles', fn (Builder $q) => $q->where('name', 'team_leader'))->whereNull('baja')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->whereHas('roles', fn (Builder $q) => $q->where('name', 'team_leader'))
                        ->whereNull('baja')
                ),

            'teleoperadoras' => Tab::make('Teleoperadoras')
                ->icon('heroicon-o-phone')
                ->badge(User::whereHas('roles', fn (Builder $q) => $q->where('name', 'teleoperator'))->whereNull('baja')->count())
                ->badgeColor('info')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->whereHas('roles', fn (Builder $q) => $q->where('name', 'teleoperator'))
                        ->whereNull('baja')
                ),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'de_alta';
    }
}
