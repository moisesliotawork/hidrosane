<?php

namespace App\Filament\Gerente\Resources\CustomerResource\Widgets;

use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Builder;
use App\Models\{Customer, Note, NoteSalaObservation};
use App\Enums\{NoteStatus, EstadoTerminal};
use App\Filament\HeadOfRoom\Resources\NoteDescResource;

class CustomerNotesTable extends BaseWidget
{
    protected static ?string $heading = 'HISTORIAL DE VISITA DE ESTE CLIENTE';
    protected int|string|array $columnSpan = 'full';

    public ?Customer $record = null;

    protected function getTableQuery(): Builder
    {
        return Note::query()
            ->with(['salaObservations.author', 'observations.author', 'venta'])
            ->where('customer_id', $this->record?->id)
            ->latest('created_at');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('nro_nota')
                ->label('# Nota')
                ->sortable()
                ->searchable()
                ->formatStateUsing(function (string $state) {
                    return strlen($state) === 5
                        ? substr($state, 0, 3) . ' ' . substr($state, 3, 2)
                        : $state;
                }),

            TextColumn::make('status')
                ->label('Estado')
                ->badge()
                ->formatStateUsing(fn($state) =>
                    $state instanceof NoteStatus
                        ? $state->label()
                        : (NoteStatus::tryFrom($state)?->label() ?? (string) $state)
                )
                ->color(fn($state) =>
                    $state instanceof NoteStatus
                        ? $state->getColor()
                        : (NoteStatus::tryFrom($state)?->getColor() ?? 'gray')
                )
                ->sortable(),

            TextColumn::make('assignment_date')
                ->label('Asig.')
                ->date('d/m/Y')
                ->sortable(),

            TextColumn::make('visit_date')
                ->label('Visita')
                ->date('d/m/Y')
                ->sortable(),

            TextColumn::make('comercial_empleado')
                ->label('Com.')
                ->badge()
                ->color(function ($state) {
                    if ($state === 'Sin Com.') return 'gray';
                    if ($state === 'Comercial no encontrado') return 'danger';
                    return 'success';
                }),

            TextColumn::make('estado_terminal')
                ->label('TN')
                ->badge()
                ->formatStateUsing(fn($state) =>
                    $state instanceof EstadoTerminal
                        ? $state->label()
                        : (EstadoTerminal::tryFrom($state)?->label() ?? (string) $state)
                )
                ->color(fn($state) => match ($state instanceof EstadoTerminal ? $state : EstadoTerminal::tryFrom($state)) {
                    EstadoTerminal::NUL       => 'danger',
                    EstadoTerminal::VENTA     => 'success',
                    EstadoTerminal::CONFIRMADO => 'orange',
                    EstadoTerminal::SALA      => 'pink',
                    default => 'gray',
                }),

            TextColumn::make('observaciones_sala')
                ->label('Observaciones')
                ->html()
                ->state(function (Note $record): string {
                    $lines = [];

                    foreach (($record->getRelation('observations') ?? collect())->sortBy('created_at') as $o) {
                        if (empty($o->observation)) continue;
                        $lines[] =
                            '<div style="font-size:0.72rem;padding:1px 0;line-height:1.4">' .
                            '<span style="color:#9ca3af">' . e($o->created_at->format('d/m/Y H:i')) . '</span> ' .
                            '<strong>' . e($o->author->name ?? '-') . '</strong>: ' .
                            e($o->observation) .
                            '</div>';
                    }

                    foreach (($record->getRelation('salaObservations') ?? collect())->sortBy('created_at') as $o) {
                        $lines[] =
                            '<div style="font-size:0.72rem;padding:1px 0;line-height:1.4">' .
                            '<span style="color:#9ca3af">' . e($o->created_at->format('d/m/Y H:i')) . '</span> ' .
                            '<strong>' . e($o->author->name ?? '-') . '</strong>: ' .
                            e($o->observation) .
                            '</div>';
                    }

                    $obsRep = trim($record->venta?->observaciones_repartidor ?? '');
                    if ($obsRep !== '') {
                        $lines[] =
                            '<div style="font-size:0.72rem;padding:1px 0;line-height:1.4;color:#92400e">' .
                            '<strong>Obs. Comercial:</strong> ' .
                            e($obsRep) .
                            '</div>';
                    }

                    if (empty($lines)) {
                        return '<span style="color:#9ca3af;font-size:0.7rem;font-style:italic">—</span>';
                    }
                    return implode('', $lines);
                })
                ->wrap(),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('añadir_observacion')
                ->label('+ Obs.')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('warning')
                ->modalHeading('Añadir observación a esta nota')
                ->form([
                    Textarea::make('observation')
                        ->label('Observación')
                        ->required()
                        ->rows(3)
                        ->placeholder('Escribe una observación...'),
                ])
                ->action(function (Note $record, array $data): void {
                    NoteSalaObservation::create([
                        'note_id'     => $record->id,
                        'author_id'   => auth()->id(),
                        'observation' => $data['observation'],
                    ]);
                }),
        ];
    }

    protected function isTablePaginationEnabled(): bool
    {
        return true;
    }
}
