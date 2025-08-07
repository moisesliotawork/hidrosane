<?php
// app/Filament/Commercial/Resources/NoteResource/Pages/ListNotes.php

namespace App\Filament\Commercial\Resources\NoteResource\Pages;

use App\Filament\Commercial\Resources\NoteResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Filament\Actions;
use Illuminate\Database\Eloquent\Builder;
use App\Models\{Team, Note};

class ListNotes extends ListRecords
{
    protected static string $resource = NoteResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }

    public function getTabs(): array
    {
        $user = auth()->user();

        // CALCULAR IDs visibles: propio + equipo (si es líder)
        $visibleIds = [$user->id];
        if ($user->hasRole('team_leader')) {
            $team = Team::with('members:id')->where('team_leader_id', $user->id)->first();
            if ($team) {
                $visibleIds = array_merge($visibleIds, $team->members->pluck('id')->all());
            }
        }

        // Pestaña “Todas”
        $tabs = [
            'todas' => Tab::make('Todas')
                ->icon('heroicon-o-list-bullet')
                ->badge(
                    Note::query()
                        ->whereIn('comercial_id', $visibleIds)
                        ->where(function ($q) {
                            $q->whereNull('estado_terminal')
                                ->orWhere('estado_terminal', '');
                        })
                        ->count()
                )
                ->modifyQueryUsing(function (Builder $query) use ($visibleIds) {
                    // 1) Filtrar IDs visibles
                    $query->whereIn('comercial_id', $visibleIds);
                    // 2) Filtrar estado_terminal vacío
                    $query->where(function ($q) {
                        $q->whereNull('estado_terminal')
                            ->orWhere('estado_terminal', '');
                    });
                }),
        ];

        // Si no es jefe de equipo, devolvemos solo “Todas”
        // Si no es jefe, devolvemos solo “Todas”
        if (!$user->hasRole('team_leader')) {
            return $tabs;
        }

        // Equipo y comerciales
        $team = Team::with('members:id,empleado_id,name')
            ->where('team_leader_id', $user->id)
            ->first();

        if (!$team) {
            return $tabs;
        }

        $comerciales = collect([$user])->merge($team->members);

        foreach ($comerciales as $c) {
            // Etiqueta que quieres ver
            $label = "{$c->empleado_id} {$c->name}";

            // Clave única que siempre empiece con letras
            $key = "com_{$c->id}";

            $tabs[$key] = Tab::make($label)
                ->icon('heroicon-o-user')
                ->badge(
                    Note::query()
                        ->where('comercial_id', $c->id)
                        ->where(function ($q) {
                            $q->whereNull('estado_terminal')
                                ->orWhere('estado_terminal', '');
                        })
                        ->count()
                )
                ->modifyQueryUsing(function (Builder $query) use ($c) {
                    $query->where('comercial_id', $c->id)
                        ->where(function ($q) {
                            $q->whereNull('estado_terminal')
                                ->orWhere('estado_terminal', '');
                        });
                });
        }

        return $tabs;
    }
}
