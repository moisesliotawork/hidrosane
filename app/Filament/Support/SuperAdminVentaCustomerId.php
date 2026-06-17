<?php

namespace App\Filament\Support;

use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class SuperAdminVentaCustomerId
{
    public static function isSuperAdminPanel(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'superAdmin';
    }

    /** @return array<int, TextInput> */
    public static function formFields(): array
    {
        if (! static::isSuperAdminPanel()) {
            return [];
        }

        return [
            TextInput::make('customer_id')
                ->label('ID_Cliente')
                ->numeric()
                ->minValue(1)
                ->required()
                ->helperText('Reasigna el contrato y su nota a otro cliente existente en la base de datos.'),
        ];
    }

    public static function tableColumn(): TextColumn
    {
        return TextColumn::make('customer_id')
            ->label('ID-Cliente')
            ->badge()
            ->color('gray')
            ->sortable()
            ->searchable(
                isIndividual: true,
                query: function (Builder $query, string $search): Builder {
                    return $query->whereRaw('CAST(ventas.customer_id AS CHAR) LIKE ?', ["%{$search}%"]);
                },
            );
    }
}
