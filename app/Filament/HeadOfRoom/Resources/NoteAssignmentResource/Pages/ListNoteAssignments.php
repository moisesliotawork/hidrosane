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

    // Grupos abiertos por defecto para ver de un vistazo las asignaciones del día
    public bool $tableGroupingCollapsed = false;

    public function isTableGroupingCollapsedByDefault(): bool
    {
        return false;
    }

    public function getTabs(): array
    {
        $today = Note::businessToday();
        $tomorrow = $today->copy()->addDay();

        return [
            'hoy' => Tab::make('HOY')
                ->icon('heroicon-o-calendar')
                ->badge(
                    Note::query()
                        ->assignedToCommercial()
                        ->whereEffectiveAssignmentDate($today)
                        ->count()
                )
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->assignedToCommercial()
                    ->whereEffectiveAssignmentDate($today)
                ),

            'manana' => Tab::make('MAÑANA')
                ->icon('heroicon-o-chevron-double-right')
                ->badge(
                    Note::query()
                        ->assignedToCommercial()
                        ->whereEffectiveAssignmentDate($tomorrow)
                        ->count()
                )
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->assignedToCommercial()
                    ->whereEffectiveAssignmentDate($tomorrow)
                ),

            'buscar_fecha' => Tab::make('BUSCAR FECHA')
                ->icon('heroicon-o-magnifying-glass')
                ->badge(
                    Note::query()
                        ->assignedToCommercial()
                        ->count()
                )
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->assignedToCommercial()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'hoy';
    }
}
