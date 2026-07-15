<?php

namespace App\Support\Filament;

use App\Models\Customer;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

final class ClientesBorradosTable
{
    /**
     * @return array<int, TextColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('deleted_at')
                ->label('Fecha')
                ->date('d/m/Y')
                ->sortable(),

            TextColumn::make('deleted_at_time')
                ->label('Hora')
                ->state(fn (Customer $record): string => optional($record->deleted_at)?->format('H:i') ?? '—'),

            TextColumn::make('deletedBy.empleado_id')
                ->label('Cód. usuario')
                ->state(fn (Customer $record): string => $record->deletedBy?->empleado_id ?? '—')
                ->badge()
                ->color('warning')
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->whereHas('deletedBy', function (Builder $userQuery) use ($search) {
                        $userQuery->where('empleado_id', 'like', "%{$search}%");
                    });
                }),

            TextColumn::make('deletedBy.display_name')
                ->label('Usuario que borró')
                ->state(fn (Customer $record): string => $record->deletedBy?->display_name ?? '—')
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->whereHas('deletedBy', function (Builder $userQuery) use ($search) {
                        $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('empleado_id', 'like', "%{$search}%");
                    });
                }),

            TextColumn::make('name')
                ->label('Cliente')
                ->searchable(['first_names', 'last_names'])
                ->sortable()
                ->weight('bold'),

            TextColumn::make('dni')
                ->label('DNI')
                ->searchable()
                ->sortable(),

            TextColumn::make('phone')
                ->label('Teléfono')
                ->searchable(),

            TextColumn::make('nro_cliente')
                ->label('Nº Cliente')
                ->badge()
                ->color('gray')
                ->searchable()
                ->placeholder('—'),

            TextColumn::make('id')
                ->label('ID')
                ->badge()
                ->sortable(),
        ];
    }
}
