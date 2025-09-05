<?php

namespace App\Filament\Admin\Resources\NoteResource\Pages;

use App\Enums\EstadoTerminal;
use App\Filament\Admin\Resources\NoteResource;
use App\Models\Note;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListNotes extends ListRecords
{
    protected static string $resource = NoteResource::class;

    

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),

            \Filament\Actions\Action::make('pdfSala')
                ->label('Generar PDF (Oficina)')
                ->icon('heroicon-o-printer')
                ->color('pink')
                ->url(route('notas.sala.pdf'))     // ← abrir GET
                ->openUrlInNewTab(),               // ← nueva pestaña, sin perder el tab
        ];
    }

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
