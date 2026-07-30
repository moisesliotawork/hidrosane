<?php

namespace App\Support\Filament;

use App\Models\Venta;
use App\Support\VentaSoftRestore;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

final class ContratosBorradosTable
{
    /**
     * @return array<int, TextColumn>
     */
    public static function columns(): array
    {
        return [
            BorradosRestoreColumn::make(
                modalHeading: 'Restaurar contrato',
                modalDescription: 'El contrato volverá a aparecer en Contratos.',
                successNotificationTitle: 'Contrato restaurado',
                using: fn (Venta $record) => VentaSoftRestore::restore($record),
            ),

            TextColumn::make('deleted_at')
                ->label('Fecha borrado')
                ->date('d/m/Y')
                ->sortable(),

            TextColumn::make('deleted_at_time')
                ->label('Hora')
                ->state(fn (Venta $record): string => optional($record->deleted_at)?->format('H:i') ?? '—'),

            TextColumn::make('deletedBy.empleado_id')
                ->label('Cód. usuario')
                ->state(fn (Venta $record): string => $record->deletedBy?->empleado_id ?? '—')
                ->badge()
                ->color('warning')
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->whereHas('deletedBy', function (Builder $userQuery) use ($search) {
                        $userQuery->where('empleado_id', 'like', "%{$search}%");
                    });
                }),

            TextColumn::make('deletedBy.display_name')
                ->label('Usuario que borró')
                ->state(fn (Venta $record): string => $record->deletedBy?->display_name ?? '—')
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->whereHas('deletedBy', function (Builder $userQuery) use ($search) {
                        $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('empleado_id', 'like', "%{$search}%");
                    });
                }),

            TextColumn::make('nro_contr_adm')
                ->label('Nº Contrato')
                ->badge()
                ->color('danger')
                ->searchable()
                ->sortable(),

            TextColumn::make('nro_cliente_adm')
                ->label('Nº Cliente')
                ->badge()
                ->color('gray')
                ->searchable()
                ->sortable()
                ->placeholder('—'),

            TextColumn::make('customer.name')
                ->label('Cliente')
                ->searchable(['first_names', 'last_names'])
                ->sortable()
                ->weight('bold'),

            TextColumn::make('customer.dni')
                ->label('DNI')
                ->searchable()
                ->sortable()
                ->placeholder('—'),

            TextColumn::make('customer.phone')
                ->label('Teléfono')
                ->searchable()
                ->placeholder('—'),

            TextColumn::make('id')
                ->label('ID venta')
                ->badge()
                ->sortable(),
        ];
    }
}
