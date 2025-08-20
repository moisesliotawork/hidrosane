<?php

namespace App\Filament\Repartidor\Resources;

use App\Filament\Repartidor\Resources\EntregaConVentaResource\Pages;

class EntregaConVentaResource extends EntregaSimpleResource
{
    protected static ?string $navigationLabel   = 'Entregas con Venta';
    protected static ?string $modelLabel        = 'Entrega con Venta';
    protected static ?string $pluralModelLabel  = 'Entregas con Venta';
    protected static ?string $navigationIcon    = 'heroicon-o-document-check';

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEntregaConVentas::route('/'),
            'edit'  => Pages\EditEntregaConVenta::route('/{record}/edit'),
            'add-offers' => Pages\AgregarOfertasRepartidor::route('/{record}/ofertas-repartidor'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false; // cámbialo a true si quieres que aparezca en el menú
    }
}

