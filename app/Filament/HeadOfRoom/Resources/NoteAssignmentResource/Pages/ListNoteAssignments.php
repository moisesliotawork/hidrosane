<?php

namespace App\Filament\HeadOfRoom\Resources\NoteAssignmentResource\Pages;

use App\Filament\HeadOfRoom\Resources\NoteAssignmentResource;
use App\Models\Note;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListNoteAssignments extends ListRecords
{
    protected static string $resource = NoteAssignmentResource::class;

    // Esta propiedad fuerza a que todos los grupos nazcan colapsados
    public bool $tableGroupingCollapsed = true;

    public function isTableGroupingCollapsedByDefault(): bool
    {
        return true;
    }

    public function getTabs(): array
    {
        return [
            'hoy' => Tab::make('HOY')
                ->icon('heroicon-o-calendar')
                ->badge(
                    Note::query()
                        ->whereNotNull('comercial_id')
                        ->whereDate('assignment_date', now('Europe/Madrid')->toDateString())
                        ->count()
                )
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNotNull('comercial_id')
                    ->whereDate('assignment_date', now('Europe/Madrid')->toDateString())
                ),

            'manana' => Tab::make('MAÑANA')
                ->icon('heroicon-o-chevron-double-right')
                ->badge(
                    Note::query()
                        ->whereNotNull('comercial_id')
                        ->whereDate('assignment_date', now('Europe/Madrid')->addDay()->toDateString())
                        ->count()
                )
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNotNull('comercial_id')
                    ->whereDate('assignment_date', now('Europe/Madrid')->addDay()->toDateString())
                ),

            // Pestaña para facilitar la búsqueda por fecha usando el filtro de arriba
            'buscar_fecha' => Tab::make('BUSCAR FECHA')
                ->icon('heroicon-o-magnifying-glass')
                ->badge(
                    Note::query()
                        ->whereNotNull('comercial_id')
                        ->count()
                )
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('comercial_id')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'hoy';
    }
}
