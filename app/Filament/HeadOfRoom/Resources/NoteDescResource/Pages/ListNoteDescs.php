<?php

namespace App\Filament\HeadOfRoom\Resources\NoteDescResource\Pages;

use App\Filament\HeadOfRoom\Resources\NoteDescResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\EstadoTerminal;
use App\Models\Note;

class ListNoteDescs extends ListRecords
{
    protected static string $resource = NoteDescResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            /** Todas (respeta el whereIn() que ya tienes en el Resource) */
            'todas' => Tab::make('Todas')
                ->icon('heroicon-o-list-bullet'),

            /** Solo ventas */
            'ventas' => Tab::make('Ventas')
                ->icon('heroicon-o-currency-dollar')
                ->badge(
                    Note::query()
                        ->where('estado_terminal', EstadoTerminal::VENTA)
                        ->count()
                )
                ->badgeColor('success')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->where('estado_terminal', EstadoTerminal::VENTA)
                ),

            /** Solo confirmadas */
            'confirmadas' => Tab::make('Confirmadas')
                ->icon('heroicon-o-check-circle')
                ->badge(
                    Note::query()
                        ->where('estado_terminal', EstadoTerminal::CONFIRMADO)
                        ->count()
                )
                ->badgeColor('warning')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->where('estado_terminal', EstadoTerminal::CONFIRMADO)
                ),

            /** Solo nulo */
            'nulo' => Tab::make('Nulo')
                ->icon('heroicon-o-x-circle')
                ->badge(
                    Note::query()
                        ->where('estado_terminal', EstadoTerminal::NUL)
                        ->count()
                )
                ->badgeColor('danger')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->where('estado_terminal', EstadoTerminal::NUL)
                ),
        ];
    }
}
