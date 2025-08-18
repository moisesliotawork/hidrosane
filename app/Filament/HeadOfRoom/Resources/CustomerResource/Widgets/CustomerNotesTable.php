<?php

namespace App\Filament\HeadOfRoom\Resources\CustomerResource\Widgets;

use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use App\Models\{Customer, Note};
use App\Enums\{NoteStatus, EstadoTerminal};
use App\Filament\HeadOfRoom\Resources\NoteDescResource; // para ver la nota en su recurso

class CustomerNotesTable extends BaseWidget
{
    protected static ?string $heading = 'HISTORICO DE CONTRATOS';
    protected int|string|array $columnSpan = 'full';

    /** Filament inyecta el registro actual del ViewRecord */
    public ?Customer $record = null;

    protected function getTableQuery(): Builder
    {
        return Note::query()
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
                ->formatStateUsing(fn ($state) =>
                    $state instanceof NoteStatus
                        ? $state->label()
                        : (NoteStatus::tryFrom($state)?->label() ?? (string) $state)
                )
                ->color(fn ($state) =>
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
                ->formatStateUsing(fn ($state) =>
                    $state instanceof EstadoTerminal
                        ? $state->label()
                        : (EstadoTerminal::tryFrom($state)?->label() ?? (string) $state)
                )
                ->color(fn ($state) => match ($state instanceof EstadoTerminal ? $state : EstadoTerminal::tryFrom($state)) {
                    EstadoTerminal::NUL => 'danger',
                    EstadoTerminal::VENTA => 'success',
                    EstadoTerminal::CONFIRMADO => 'orange',
                    EstadoTerminal::SALA => 'pink',
                    default => 'gray',
                }),
        ];
    }

    protected function isTablePaginationEnabled(): bool
    {
        return true;
    }
}
