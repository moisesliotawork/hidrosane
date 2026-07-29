<?php

namespace App\Support\Filament;

use App\Enums\EstadoTerminal;
use App\Enums\NoteStatus;
use App\Models\Note;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

final class NotasBorradasTable
{
    /**
     * @return array<int, TextColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('deleted_at')
                ->label('Fecha borrado')
                ->date('d/m/Y')
                ->sortable(),

            TextColumn::make('deleted_at_time')
                ->label('Hora')
                ->state(fn (Note $record): string => optional($record->deleted_at)?->format('H:i') ?? '—'),

            TextColumn::make('deletedBy.empleado_id')
                ->label('Cód. usuario')
                ->state(fn (Note $record): string => $record->deletedBy?->empleado_id ?? '—')
                ->badge()
                ->color('warning')
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->whereHas('deletedBy', function (Builder $userQuery) use ($search) {
                        $userQuery->where('empleado_id', 'like', "%{$search}%");
                    });
                }),

            TextColumn::make('deletedBy.display_name')
                ->label('Usuario que borró')
                ->state(fn (Note $record): string => $record->deletedBy?->display_name ?? '—')
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->whereHas('deletedBy', function (Builder $userQuery) use ($search) {
                        $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('empleado_id', 'like', "%{$search}%");
                    });
                }),

            TextColumn::make('nro_nota')
                ->label('# Nota')
                ->badge()
                ->color('danger')
                ->searchable()
                ->sortable()
                ->formatStateUsing(function (?string $state): string {
                    if (! $state) {
                        return '—';
                    }
                    if (strlen($state) === 5) {
                        return substr($state, 0, 3).' '.substr($state, 3, 2);
                    }

                    return $state;
                }),

            TextColumn::make('user.empleado_id')
                ->label('T. Op.')
                ->badge()
                ->color(Color::Pink)
                ->searchable()
                ->placeholder('—'),

            TextColumn::make('customer.name')
                ->label('Cliente')
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->whereHas('customer', function (Builder $q) use ($search) {
                        $q->where(function (Builder $qq) use ($search) {
                            $qq->where('customers.first_names', 'like', "%{$search}%")
                                ->orWhere('customers.last_names', 'like', "%{$search}%")
                                ->orWhereRaw(
                                    "CONCAT(COALESCE(customers.first_names,''),' ',COALESCE(customers.last_names,'')) LIKE ?",
                                    ["%{$search}%"]
                                );
                        });
                    });
                })
                ->weight('bold'),

            TextColumn::make('customer.phone')
                ->label('Teléfono')
                ->searchable()
                ->placeholder('—'),

            TextColumn::make('customer.postal_code')
                ->label('CP')
                ->searchable()
                ->placeholder('—'),

            TextColumn::make('customer.dni')
                ->label('DNI')
                ->searchable()
                ->placeholder('—'),

            TextColumn::make('status')
                ->label('Estado')
                ->badge()
                ->color(fn (?NoteStatus $state): string => $state?->getColor() ?? 'gray')
                ->formatStateUsing(fn (?NoteStatus $state): string => $state?->label() ?? '—')
                ->sortable(),

            TextColumn::make('comercial_empleado')
                ->label('Com.')
                ->badge()
                ->color(function ($state) {
                    if ($state === 'Sin Com.' || $state === null) {
                        return 'gray';
                    }
                    if ($state === 'Comercial no encontrado') {
                        return 'danger';
                    }

                    return 'success';
                }),

            TextColumn::make('assignment_date')
                ->label('Asig.')
                ->date('d/m/Y')
                ->sortable()
                ->placeholder('—'),

            TextColumn::make('visit_schedule')
                ->label('Horario')
                ->badge()
                ->color(Color::Gray)
                ->placeholder('—'),

            TextColumn::make('estado_terminal')
                ->label('TN')
                ->badge()
                ->formatStateUsing(fn (Note $record): string => $record->estado_terminal?->label() ?? '—')
                ->color(fn (Note $record): string => match ($record->estado_terminal) {
                    EstadoTerminal::NUL => 'danger',
                    EstadoTerminal::VENTA => 'success',
                    EstadoTerminal::CONFIRMADO => 'orange',
                    EstadoTerminal::SALA => 'pink',
                    EstadoTerminal::SIN_ESTADO => 'gray',
                    default => 'gray',
                })
                ->sortable(),

            TextColumn::make('fuente')
                ->label('Fuente')
                ->badge()
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('created_at')
                ->label('Fech/Nota')
                ->date('d/m/Y')
                ->sortable()
                ->toggleable(),

            TextColumn::make('id')
                ->label('ID nota')
                ->badge()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }
}
