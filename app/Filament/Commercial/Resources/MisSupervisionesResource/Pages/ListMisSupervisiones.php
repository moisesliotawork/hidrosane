<?php

namespace App\Filament\Commercial\Resources\MisSupervisionesResource\Pages;

use App\Filament\Commercial\Resources\MisSupervisionesResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Note;

class ListMisSupervisiones extends ListRecords
{
    protected static string $resource = MisSupervisionesResource::class;

    public function getTitle(): string
    {
        return 'Mis Supervisiones';
    }

    public function getTabs(): array
    {
        // Supervisados vigentes del usuario en sesión
        $supervisados = MisSupervisionesResource::getSupervisadosVigentes();

        // Tab "TODOS"
        $tabs = [
            'todos' => Tab::make('TODOS')
                ->icon('heroicon-o-list-bullet')
                ->modifyQueryUsing(function (Builder $query) use ($supervisados) {
                    $ids = $supervisados->pluck('id')->all();
                    $query->whereIn('comercial_id', $ids);
                })
                ->badge(function () use ($supervisados) {
                    $ids = $supervisados->pluck('id')->all();
                    return Note::query()->whereIn('comercial_id', $ids)
                        ->where(function ($q) {
                            $q->whereNull('estado_terminal')
                                ->orWhere('estado_terminal', '') // vacío exacto
                                ->orWhereRaw("LOWER(TRIM(estado_terminal)) = 'ausente'");
                        })
                        ->whereDoesntHave('venta')
                        ->count();
                }),
        ];

        // Un tab por cada supervisado
        foreach ($supervisados as $u) {
            $key = "sup_{$u->id}";
            $label = "{$u->empleado_id} - {$u->name}";

            $tabs[$key] = Tab::make($label)
                ->icon('heroicon-o-user')
                ->modifyQueryUsing(function (Builder $query) use ($u) {
                    $query->where('comercial_id', $u->id);
                })
                ->badge(fn() => Note::where('comercial_id', $u->id)
                    ->where(function ($q) {
                        $q->whereNull('estado_terminal')
                            ->orWhere('estado_terminal', '') // vacío exacto
                            ->orWhereRaw("LOWER(TRIM(estado_terminal)) = 'ausente'");
                    })
                    ->whereDoesntHave('venta')
                    ->count());
        }

        return $tabs;
    }
}
