<?php

namespace App\Filament\Repartidor\Resources;

use App\Models\Venta;
use Filament\Resources\Resource;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Actions;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\{Section, TextEntry, IconEntry, RepeatableEntry, Grid};
use Filament\Tables;
use App\Filament\Repartidor\Resources\VentaResource\Pages;

class VentaResource extends Resource
{
    protected static ?string $model = Venta::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([]); // opcionalmente puedes no mostrar tabla
    }

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form; // este recurso no usa formularios
    }

    /**
     * Define el Infolist oficial para ViewRecord.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            // ▓▓▓ Cliente
            Section::make('DATOS DEL CONTARTO')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('nro_contrato')
                            ->label('Cód. del Contrato')
                            ->state(fn($record) => $record->nro_contrato),

                        TextEntry::make('customer.id')
                            ->label('Cód. del Cliente'),

                        TextEntry::make('customer')
                            ->label('Nombre')
                            ->state(fn($record) => $record->customer?->first_names . ' ' . $record->customer?->last_names),

                        TextEntry::make('customer.primary_address')
                            ->label('Dirección principal'),

                        Actions::make([
                            Action::make('llamar')
                                ->label(fn($record) => 'telf ' . $record->customer?->phone)
                                ->icon('heroicon-m-phone')
                                ->color('success')
                                ->url(fn($record) => 'tel:' . $record->customer?->phone)
                                ->openUrlInNewTab(false),
                        ])
                    ]),
                ]),

            // ▓▓▓ Ofertas y productos
            Section::make('Ofertas incluidas')
                ->schema([
                    RepeatableEntry::make('ventaOfertas')
                        ->label('')
                        ->schema([
                            TextEntry::make('oferta.nombre')->label('Nombre Oferta'),
                            TextEntry::make('puntos')->label('Puntos Totales'),

                            RepeatableEntry::make('productos')
                                ->label('')
                                ->schema([
                                    TextEntry::make('producto.nombre')->label('Producto'),
                                    TextEntry::make('cantidad')->label('Cantidad'),
                                    TextEntry::make('puntos_linea')->label('Pts Art.'),
                                ])
                                ->columns(3),
                        ])
                        ->columns(1),
                ]),

            // ▓▓▓ Repartidor
            Section::make('Informe al repartidor')
                ->schema([
                    Grid::make(2)->schema([

                        TextEntry::make('motivo_venta')
                            ->label('Razon de Venta del Comercial'),

                        IconEntry::make('interes_art')
                            ->label('¿Interesado en más artículos?')
                            ->boolean()
                            ->trueIcon('heroicon-m-check-circle')
                            ->falseIcon('heroicon-m-x-circle')
                            ->color(fn($state) => $state ? 'success' : 'gray'),
                    ]),

                    TextEntry::make('interes_art_detalle')
                        ->label('Otros artículos de interés')
                        ->columnSpanFull()
                        ->hidden(fn($record) => !($record->interes_art)),

                    TextEntry::make('observaciones_repartidor')
                        ->label('Observaciones')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVentas::route('/'),
            'view' => Pages\ViewVenta::route('/{record}'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
