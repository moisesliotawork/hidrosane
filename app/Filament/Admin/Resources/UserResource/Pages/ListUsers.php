<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected static string $view = 'filament.admin.resources.user-resource.list-users';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->whereNull('baja');
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsuariosDeBaja(): Collection
    {
        return User::query()
            ->whereNotNull('baja')
            ->orderByDesc('baja')
            ->orderBy('empleado_id')
            ->get();
    }

    public function roleLabel(?string $role): string
    {
        if ($role === null || $role === '') {
            return '—';
        }

        $enum = UserRole::tryFrom($role);

        return $enum?->label() ?? ucfirst(str_replace('_', ' ', $role));
    }

    public function getTabs(): array
    {
        return [
            'todos' => Tab::make('Todos')
                ->icon('heroicon-o-users')
                ->badge(User::whereNull('baja')->count())
                ->badgeColor('gray'),

            'comerciales' => Tab::make('Comerciales')
                ->icon('heroicon-o-briefcase')
                ->badge(User::whereHas('roles', fn (Builder $q) => $q->where('name', 'commercial'))->whereNull('baja')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->whereHas('roles', fn (Builder $q) => $q->where('name', 'commercial'))
                        ->orderBy('empleado_id', 'asc')
                ),

            'jefes_equipo' => Tab::make('Jefes de Equipo')
                ->icon('heroicon-o-star')
                ->badge(User::whereHas('roles', fn (Builder $q) => $q->where('name', 'team_leader'))->whereNull('baja')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->whereHas('roles', fn (Builder $q) => $q->where('name', 'team_leader'))
                        ->orderBy('empleado_id', 'asc')
                ),

            'teleoperadoras' => Tab::make('Teleoperadoras')
                ->icon('heroicon-o-phone')
                ->badge(User::whereHas('roles', fn (Builder $q) => $q->where('name', 'teleoperator'))->whereNull('baja')->count())
                ->badgeColor('info')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->whereHas('roles', fn (Builder $q) => $q->where('name', 'teleoperator'))
                        ->orderBy('empleado_id', 'asc')
                ),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'todos';
    }
}
