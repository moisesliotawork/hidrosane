<?php

namespace App\Filament\HeadOfRoom\Resources\NoteResource\Pages;

use App\Enums\EstadoTerminal;
use App\Filament\HeadOfRoom\Resources\NoteResource;
use App\Models\Note;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListNotes extends ListRecords
{
    protected static string $resource = NoteResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }

    public function getTabs(): array
    {
        // IMPORTANTE: retornar el $q para poder encadenar
        $baseScope = fn(Builder $q) => $q->where(function (Builder $qq) {
            $qq->whereNull('estado_terminal')
                ->orWhereIn('estado_terminal', [
                    EstadoTerminal::SIN_ESTADO->value,
                    EstadoTerminal::SALA->value,
                ]);
        });

        return [
            // 1) SALA
            'sala' => Tab::make('SALA')
                ->icon('heroicon-o-building-office')
                ->badge(
                    $baseScope(Note::query())
                        ->where('estado_terminal', EstadoTerminal::SALA)
                        ->count()
                )
                ->badgeColor('pink')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $baseScope($query)->where('estado_terminal', EstadoTerminal::SALA)
                ),

            // 2) Todas (default)
            'todas' => Tab::make('Todas')
                ->icon('heroicon-o-list-bullet')
                ->badge($baseScope(Note::query())->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn(Builder $query) => $baseScope($query)),

            // 3) S/E
            'se' => Tab::make('S/E')
                ->icon('heroicon-o-question-mark-circle')
                ->badge(
                    $baseScope(Note::query())
                        ->where(function (Builder $q) {
                            $q->whereNull('estado_terminal')
                                ->orWhere('estado_terminal', EstadoTerminal::SIN_ESTADO);
                        })
                        ->count()
                )
                ->badgeColor('gray')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $baseScope($query)->where(function (Builder $q) {
                        $q->whereNull('estado_terminal')
                            ->orWhere('estado_terminal', EstadoTerminal::SIN_ESTADO);
                    })
                ),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'todas'; // clave del tab
    }

}
