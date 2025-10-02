<?php

namespace App\Filament\HeadOfRoom\Resources\NoteResource\Pages;

use App\Enums\EstadoTerminal;
use App\Filament\HeadOfRoom\Resources\NoteResource;
use App\Models\Note;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;


class ListNotes extends ListRecords
{
    protected static string $resource = NoteResource::class;



    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),

            \Filament\Actions\Action::make('pdfSalaSoloNoImpresas')
                ->label('Generar PDF (Oficina)')
                ->icon('heroicon-o-printer')
                ->color('pink')
                ->requiresConfirmation()
                ->action(function () {
                    // 1) IDs de notas en SALA y NO impresas
                    $ids = Note::query()
                        ->where('estado_terminal', EstadoTerminal::SALA->value) // columna string
                        ->where('printed', false)
                        ->pluck('id')
                        ->all();

                    if (empty($ids)) {
                        Notification::make()
                            ->title('No hay notas NO IMPRESAS en SALA')
                            ->warning()
                            ->send();
                        return;
                    }

                    // 2) Marcar como impresas ANTES de generar el PDF
                    DB::transaction(function () use ($ids) {
                        Note::whereIn('id', $ids)->update([
                            'printed' => 1,
                            'updated_at' => now(),
                        ]);
                    });

                    // 3) Cargar datos y generar PDF
                    $notes = Note::query()
                        ->whereIn('id', $ids)
                        ->with([
                            'customer.postalCode.city',
                            'user',
                            'comercial',
                            'observations.author',
                            'observacionesSala.author',
                        ])
                        ->orderBy('nro_nota')
                        ->get();

                    if ($notes->isEmpty()) {
                        Notification::make()
                            ->title('No hay notas para renderizar')
                            ->warning()
                            ->send();
                        return;
                    }

                    $pdf = Pdf::loadView('pdf.notas-sala', ['notes' => $notes])->setPaper('a4');

                    return response()->streamDownload(
                        fn() => print ($pdf->output()),
                        'notas-oficina-' . now()->format('Ymd-His') . '.pdf'
                    );
                }),


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
