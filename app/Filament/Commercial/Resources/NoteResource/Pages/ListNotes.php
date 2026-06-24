<?php

namespace App\Filament\Commercial\Resources\NoteResource\Pages;

use App\Filament\Commercial\Resources\NoteResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Filament\Actions;
use Illuminate\Database\Eloquent\Builder;
use App\Models\{Team, Note};
use Livewire\Attributes\On;

class ListNotes extends ListRecords
{
    protected static string $resource = NoteResource::class;

    #[On('gpsCapturadoParaAccionNota')]
    public function setGpsParaAccion(string $lat, string $lng): void
    {
        if (isset($this->mountedActionsData[0])) {
            $this->mountedActionsData[0]['gps_lat'] = $lat;
            $this->mountedActionsData[0]['gps_lng'] = $lng;
        }
    }

    protected function getRedirectUrl(): string
    {
        return NoteResource::getUrl('index');
    }

    public function getTitle(): string
    {
        $user = auth()->user();

        if ($user->hasRole('team_leader')) {
            return 'NOTAS JE';
        }

        return 'Notas (listado)';
    }

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    public function getTabs(): array
    {
        $user = auth()->user();

        // Filtro de estado_terminal
        $estadoFiltro = function ($q) {
            $q->whereNull('estado_terminal')
                ->orWhere('estado_terminal', '')
                ->orWhereRaw("LOWER(TRIM(estado_terminal)) = 'ausente'");
        };

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
                        ->where($estadoFiltro)
                        ->whereDoesntHave('venta')
                        ->visibleInCommercialNotas()
                        ->where('reten', false)
                        ->count()
                )
                ->modifyQueryUsing(function (Builder $query) use ($visibleIds, $estadoFiltro) {
                    $query->whereIn('comercial_id', $visibleIds)
                        ->where($estadoFiltro)
                        ->visibleInCommercialNotas()
                        ->where('reten', false);
                }),
        ];

        // Si no es jefe de equipo, devolvemos solo “Todas”
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
                        ->where($estadoFiltro)
                        ->whereDoesntHave('venta')
                        ->visibleInCommercialNotas()
                        ->where('reten', false)
                        ->count()
                )
                ->modifyQueryUsing(function (Builder $query) use ($c, $estadoFiltro) {
                    $query->where('comercial_id', $c->id)
                        ->where($estadoFiltro)
                        ->visibleInCommercialNotas()
                        ->where('reten', false);
                });
        }

        return $tabs;
    }
}
