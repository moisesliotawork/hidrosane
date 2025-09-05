<?php

namespace App\Filament\Gerente\Resources;

use App\Filament\Gerente\Resources\BusquedaCpResource\Pages;
use App\Models\PostalCode;
use Filament\Resources\Resource;

class BusquedaCpResource extends Resource
{
    protected static ?string $model = PostalCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';
    protected static ?string $navigationLabel = 'Búsqueda CP';
    protected static ?string $modelLabel = 'Búsqueda CP';
    protected static ?string $pluralModelLabel = 'Búsqueda CP';

    protected static ?int $navigationSort = 13;

    public static function getPages(): array
    {
        // Página única tipo buscador
        return [
            'index' => Pages\BuscarCp::route('/'),
        ];
    }
}
