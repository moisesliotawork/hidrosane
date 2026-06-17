<?php

namespace App\Filament\Support;

use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class SuperAdminVentaCustomerId
{
    public static function isSuperAdminPanel(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'superAdmin';
    }

    /** @return array<int, Placeholder> */
    public static function adminReassignGuidanceFields(): array
    {
        if (static::isSuperAdminPanel()) {
            return [];
        }

        return [
            Placeholder::make('customer_reassign_guidance')
                ->label('')
                ->content(new HtmlString(
                    '<p class="text-sm text-primary-600 dark:text-primary-400">'
                    . '<strong>¿El cliente correcto ya existe con otro ID, DNI o nombre?</strong> '
                    . 'No corrijas solo nombre y DNI en este formulario. '
                    . 'Solicita a <strong>SuperAdmin</strong> que reasigne el <strong>ID_Cliente</strong> '
                    . 'del contrato al cliente correcto en <em>SuperAdmin → Contratos</em>. '
                    . 'El <strong>Nº Cliente</strong> del contrato se corrige aparte aquí (sección Administración).'
                    . '</p>'
                )),
        ];
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
