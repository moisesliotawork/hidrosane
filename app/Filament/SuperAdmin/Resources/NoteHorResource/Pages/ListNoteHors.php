<?php

namespace App\Filament\SuperAdmin\Resources\NoteHorResource\Pages;

use App\Enums\EstadoTerminal;
use App\Filament\SuperAdmin\Resources\NoteHorResource;
use App\Models\Note;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListNoteHors extends ListRecords
{
    protected static string $resource = NoteHorResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'todas' => Tab::make('Todas')
                ->icon('heroicon-o-list-bullet')
                ->badge(Note::count())
                ->badgeColor('gray'),

            'se' => Tab::make('S/E')
                ->icon('heroicon-o-question-mark-circle')
                ->badge(Note::query()->where(fn(Builder $q) =>
                    $q->whereNull('estado_terminal')->orWhere('estado_terminal', EstadoTerminal::SIN_ESTADO)
                )->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn(Builder $q) =>
                    $q->where(fn(Builder $q) =>
                        $q->whereNull('estado_terminal')->orWhere('estado_terminal', EstadoTerminal::SIN_ESTADO)
                    )
                ),

            'sala' => Tab::make('Oficina')
                ->icon('heroicon-o-building-office')
                ->badge(Note::where('estado_terminal', EstadoTerminal::SALA)->count())
                ->badgeColor('pink')
                ->modifyQueryUsing(fn(Builder $q) => $q->where('estado_terminal', EstadoTerminal::SALA)),

            'venta' => Tab::make('Ventas')
                ->icon('heroicon-o-banknotes')
                ->badge(Note::where('estado_terminal', EstadoTerminal::VENTA)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $q) => $q->where('estado_terminal', EstadoTerminal::VENTA)),

            'confirmado' => Tab::make('Confirmadas')
                ->icon('heroicon-o-check-circle')
                ->badge(Note::where('estado_terminal', EstadoTerminal::CONFIRMADO)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $q) => $q->where('estado_terminal', EstadoTerminal::CONFIRMADO)),

            'nulo' => Tab::make('Nulas')
                ->icon('heroicon-o-x-circle')
                ->badge(Note::where('estado_terminal', EstadoTerminal::NUL)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $q) => $q->where('estado_terminal', EstadoTerminal::NUL)),

            'ausente' => Tab::make('Ausentes')
                ->icon('heroicon-o-user-minus')
                ->badge(Note::where('estado_terminal', EstadoTerminal::AUSENTE)->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn(Builder $q) => $q->where('estado_terminal', EstadoTerminal::AUSENTE)),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'todas';
    }
}
