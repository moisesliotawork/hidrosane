<?php

namespace App\Filament\Commercial\Resources\NoteJVResource\Pages;

use App\Filament\Commercial\Resources\NoteJVResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Models\Note;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use App\Enums\EstadoTerminal;

class ListNoteJVS extends ListRecords
{
    protected static string $resource = NoteJVResource::class;

    public function getTabs(): array
    {
        $baseScope = fn(Builder $q) => $q->where(function (Builder $qq) {
            $qq->whereNull('estado_terminal')
                ->orWhereIn('estado_terminal', [
                    EstadoTerminal::SIN_ESTADO->value,
                    EstadoTerminal::SALA->value,
                ]);
        });

        return [
            'sala' => Tab::make('Oficina')
                ->icon('heroicon-o-building-office')
                ->badge(
                    $baseScope(Note::query())
                        ->where('estado_terminal', EstadoTerminal::SALA)
                        ->count()
                )
                ->badgeColor('pink')
                ->modifyQueryUsing(
                    fn(Builder $query) => $baseScope($query)->where('estado_terminal', EstadoTerminal::SALA)
                ),

            'todas' => Tab::make('Todas')
                ->icon('heroicon-o-list-bullet')
                ->badge($baseScope(Note::query())->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn(Builder $query) => $baseScope($query)),

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
        return 'todas';
    }
}
