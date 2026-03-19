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
            Actions\CreateAction::make()
                ->label('Crear Nota')
                ->icon('heroicon-o-plus')
                ->color('primary'),

            \Filament\Actions\Action::make('pdfSalaSoloNoImpresas')
                ->label('Generar PDF (Oficina)')
                ->icon('heroicon-o-printer')
                ->color('pink')
                ->requiresConfirmation()
                ->action(function () {
                    $ids = Note::query()
                        ->where('estado_terminal', EstadoTerminal::SALA->value)
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

                    DB::transaction(function () use ($ids) {
                        Note::whereIn('id', $ids)->update([
                            'printed' => 1,
                            'updated_at' => now(),
                        ]);
                    });

                    $notes = Note::query()
                        ->whereIn('id', $ids)
                        ->with([
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
        return [
            'sala' => Tab::make('Oficina')
                ->icon('heroicon-o-building-office')
                ->badge(
                    Note::query()
                        ->where('estado_terminal', EstadoTerminal::SALA->value)
                        ->count()
                )
                ->badgeColor('pink')
                ->modifyQueryUsing(
                    fn(Builder $query) => $query
                        ->where('estado_terminal', EstadoTerminal::SALA->value)
                ),

            'no_impresas' => Tab::make('NO IMPRESAS')
                ->icon('heroicon-o-document-text')
                ->badge(
                    Note::query()
                        ->where('printed', false)
                        ->where('estado_terminal', EstadoTerminal::SALA->value)
                        ->count()
                )
                ->badgeColor('warning')
                ->modifyQueryUsing(
                    fn(Builder $query) => $query
                        ->where('printed', false)
                        ->where('estado_terminal', EstadoTerminal::SALA->value)
                ),

            'impresas' => Tab::make('IMPRESAS')
                ->icon('heroicon-o-printer')
                ->badge(
                    Note::query()
                        ->where('printed', true)
                        ->where('estado_terminal', EstadoTerminal::SALA->value)
                        ->count()
                )
                ->badgeColor('success')
                ->modifyQueryUsing(
                    fn(Builder $query) => $query
                        ->where('printed', true)
                        ->where('estado_terminal', EstadoTerminal::SALA->value)
                ),

            'no_asignadas' => Tab::make('No Asignadas')
                ->icon('heroicon-o-user-minus')
                ->badge(
                    Note::query()
                        ->where(function (Builder $q) {
                            $q->where('estado_terminal', EstadoTerminal::SIN_ESTADO->value)
                                ->orWhereNull('estado_terminal')
                                ->orWhere('estado_terminal', '');
                        })
                        ->whereNull('comercial_id')
                        ->where('printed', false)
                        ->count()
                )
                ->badgeColor('gray')
                ->modifyQueryUsing(
                    fn(Builder $query) => $query
                        ->where(function (Builder $q) {
                            $q->where('estado_terminal', EstadoTerminal::SIN_ESTADO->value)
                                ->orWhereNull('estado_terminal')
                                ->orWhere('estado_terminal', '');
                        })
                        ->whereNull('comercial_id')
                        ->where('printed', false)
                ),

            'se' => Tab::make('SE')
                ->icon('heroicon-o-question-mark-circle')
                ->badge(
                    Note::query()
                        ->where(function (Builder $q) {
                            $q->whereNull('estado_terminal')
                                ->orWhere('estado_terminal', '')
                                ->orWhere('estado_terminal', EstadoTerminal::SIN_ESTADO->value);
                        })
                        ->count()
                )
                ->badgeColor('info')
                ->modifyQueryUsing(
                    fn(Builder $query) => $query
                        ->where(function (Builder $q) {
                            $q->whereNull('estado_terminal')
                                ->orWhere('estado_terminal', '')
                                ->orWhere('estado_terminal', EstadoTerminal::SIN_ESTADO->value);
                        })
                ),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'sala';
    }
}