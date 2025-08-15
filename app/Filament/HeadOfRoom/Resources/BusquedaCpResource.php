<?php

namespace App\Filament\HeadOfRoom\Resources;

use App\Filament\HeadOfRoom\Resources\BusquedaCpResource\Pages;
use App\Models\PostalCode;
use Filament\Resources\Resource;

class BusquedaCpResource extends Resource
{
    protected static ?string $model = PostalCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';
    protected static ?string $navigationLabel = 'Búsqueda CP';
    protected static ?string $modelLabel = 'Búsqueda CP';
    protected static ?string $pluralModelLabel = 'Búsqueda CP';

    public static function getPages(): array
    {
        // Página única tipo buscador
        return [
            'index' => Pages\BuscarCp::route('/'),
        ];
    }
}
