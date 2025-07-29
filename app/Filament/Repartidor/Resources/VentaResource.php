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


            Section::make('Declaraciones especiales')
                ->schema([
                    Grid::make(4)->schema([

                        // 🔴 NULO REPARTO
                        Section::make()->schema([
                            Actions::make([
                                Action::make('toggle_nulo_reparto')
                                    ->label('NULO REPARTO')
                                    ->color('gray_light')
                                    ->icon(fn($livewire) => $livewire->showNuloReparto ? 'heroicon-m-chevron-up' : 'heroicon-m-chevron-down')
                                    ->action(fn($livewire) => $livewire->showNuloReparto = !$livewire->showNuloReparto),
                            ]),

                            TextEntry::make('nulo_reparto_explicacion')
                                ->label(null)
                                ->state(fn() => collect([
                                    '1. No se entrega NINGÚN ARTÍCULO del contrato',
                                    '2. El CLIENTE RECHAZA la venta y la entrega',
                                ])->implode('<br>'))
                                ->html()
                                ->visible(fn($livewire) => $livewire->showNuloReparto)
                                ->extraAttributes(['class' => 'text-sm text-gray-700']),

                            Actions::make([
                                Action::make('declarar_nulo_reparto')
                                    ->label('Declarar NULO en REPARTO')
                                    ->color('gray_light')
                                    ->icon('heroicon-m-x-circle')
                                    ->requiresConfirmation()
                                    ->modalHeading('¿Confirmas que el reparto es NULO?')
                                    ->modalDescription('No se entregó nada y el cliente rechazó la entrega.')
                                    ->visible(fn($livewire) => $livewire->showNuloReparto)
                                    ->action(fn($record) => $record->reparto?->update(['estado' => 'nulo_reparto'])),
                            ]),
                        ]),

                        // 🔴 NULO FINANCIERO
                        Section::make()->schema([
                            Actions::make([
                                Action::make('toggle_nulo_financiero')
                                    ->label('NULO FINANCIERO')
                                    ->color('danger')
                                    ->icon(fn($livewire) => $livewire->showNuloFinanciero ? 'heroicon-m-chevron-up' : 'heroicon-m-chevron-down')
                                    ->action(fn($livewire) => $livewire->showNuloFinanciero = !$livewire->showNuloFinanciero),
                            ]),

                            TextEntry::make('nulo_financiero_explicacion')
                                ->label(null)
                                ->state(fn() => collect([
                                    '1. No se entrega <strong>NINGÚN ARTÍCULO</strong> del contrato.',
                                    '2. El <u>CLIENTE NO DISPONE DE FINANCIACIÓN</u>',
                                ])->implode('<br>'))
                                ->html()
                                ->visible(fn($livewire) => $livewire->showNuloFinanciero)
                                ->extraAttributes(['class' => 'text-sm text-gray-700']),

                            Actions::make([
                                Action::make('declarar_nulo_financiero')
                                    ->label('Declarar NULO FINANCIERO')
                                    ->color('gray_light')
                                    ->icon('heroicon-m-x-circle')
                                    ->requiresConfirmation()
                                    ->modalHeading('¿Confirmas que es NULO por motivos financieros?')
                                    ->modalDescription('No se entregó nada y el cliente no dispone de financiación.')
                                    ->visible(fn($livewire) => $livewire->showNuloFinanciero)
                                    ->action(fn($record) => $record->reparto?->update(['estado' => 'nulo_financiero'])),
                            ]),
                        ]),

                        // 🟦 NULO POR AUSENTE
                        Section::make()->schema([
                            Actions::make([
                                Action::make('toggle_nulo_ausente')
                                    ->label('NULO POR AUSENTE')
                                    ->color('info')
                                    ->icon(fn($livewire) => $livewire->showNuloAusente ? 'heroicon-m-chevron-up' : 'heroicon-m-chevron-down')
                                    ->action(fn($livewire) => $livewire->showNuloAusente = !$livewire->showNuloAusente),
                            ]),

                            TextEntry::make('nulo_ausente_explicacion')
                                ->label(null)
                                ->state(fn() => collect([
                                    '1. No se entrega <strong>NINGÚN ARTÍCULO</strong> del contrato.',
                                    '2. El <u>CLIENTE NO ESTÁ EN EL DOMICILIO.</u>',
                                ])->implode('<br>'))
                                ->html()
                                ->visible(fn($livewire) => $livewire->showNuloAusente)
                                ->extraAttributes(['class' => 'text-sm text-gray-700']),

                            Actions::make([
                                Action::make('declarar_nulo_ausente')
                                    ->label('Declarar NULO POR AUSENTE')
                                    ->color('gray_light')
                                    ->icon('heroicon-m-user-minus')
                                    ->requiresConfirmation()
                                    ->modalHeading('¿Confirmas que el cliente no se encontraba en el domicilio?')
                                    ->modalDescription('No se entregó nada y el cliente no estaba presente.')
                                    ->visible(fn($livewire) => $livewire->showNuloAusente)
                                    ->action(fn($record) => $record->reparto?->update(['estado' => 'nulo_ausente'])),
                            ]),
                        ]),

                        // ✅ ENTREGA SIMPLE
                        Section::make()->schema([
                            Actions::make([
                                Action::make('toggle_entrega_simple')
                                    ->label('ENTREGA SIMPLE')
                                    ->color('gray_light')
                                    ->icon(fn($livewire) => $livewire->showEntregaSimple ? 'heroicon-m-chevron-up' : 'heroicon-m-chevron-down')
                                    ->action(fn($livewire) => $livewire->showEntregaSimple = !$livewire->showEntregaSimple),
                            ]),

                            TextEntry::make('entrega_simple_explicacion')
                                ->label(null)
                                ->state(fn() => collect([
                                    '1. Se ha firmado al menos <span class="text-green-600 font-semibold underline">1 CONTRATO Y 1 PÓLIZA</span>.',
                                    '2. Has entregado todos o parte de los artículos vendidos.',
                                    '3. No has realizado <span class="text-red-600 font-semibold underline">NINGUNA</span> venta como repartidor.',
                                ])->implode('<br>'))
                                ->html()
                                ->visible(fn($livewire) => $livewire->showEntregaSimple)
                                ->extraAttributes(['class' => 'text-sm text-gray-700']),

                            Actions::make([
                                Action::make('declarar_entrega_simple')
                                    ->label('✔ Declarar ENTREGA SIMPLE ✔')
                                    ->color('success')
                                    ->icon('heroicon-m-check-badge')
                                    ->requiresConfirmation()
                                    ->modalHeading('Declarar ENTREGA SIMPLE')
                                    ->modalContent(fn($record) => view('filament.modals.entrega-simple', [
                                        'contrato' => $record->nro_contrato,
                                    ]))

                                    ->visible(fn($livewire) => $livewire->showEntregaSimple)
                                    ->action(fn($record) => $record->reparto?->update([
                                        'estado' => 'entrega_simple',
                                    ])),
                            ]),

                        ]),

                        // ✅ ENTREGA CON VENTA
                        Section::make()->schema([
                            Actions::make([
                                Action::make('toggle_entrega_venta')
                                    ->label('ENTREGA CON VENTA')
                                    ->color('success')
                                    ->icon(fn($livewire) => $livewire->showEntregaVenta ? 'heroicon-m-chevron-up' : 'heroicon-m-chevron-down')
                                    ->action(fn($livewire) => $livewire->showEntregaVenta = !$livewire->showEntregaVenta),
                            ]),

                            TextEntry::make('entrega_venta_explicacion')
                                ->label(null)
                                ->state(fn($record) => collect([
                                    '1. <span class="text-green-600 font-semibold">El cliente ha firmado algún contrato.</span>',
                                    '2. Has entregado todos o parte de los artículos vendidos.',
                                    '3. <span class="text-red-600 font-semibold">Has realizado alguna venta como repartidor.</span>',
                                ])->implode('<br>'))
                                ->html()
                                ->visible(fn($livewire) => $livewire->showEntregaVenta)
                                ->extraAttributes(['class' => 'text-sm text-gray-700']),

                            Actions::make([
                                Action::make('declarar_entrega_venta')
                                    ->label('✔ Declarar ENTREGA CON VENTA')
                                    ->color('success')
                                    ->icon('heroicon-m-check-circle')
                                    ->requiresConfirmation()
                                    ->modalHeading('Declarar ENTREGA CON VENTA')
                                    ->modalContent(fn($record) => view('filament.modals.entrega-venta', [
                                        'contrato' => $record->nro_contrato,
                                    ]))
                                    ->visible(fn($livewire) => $livewire->showEntregaVenta)
                                    ->action(fn($record) => $record->reparto?->update([
                                        'estado' => 'entrega_venta',
                                    ])),
                            ]),
                        ])

                    ]),
                ])
                ->columnSpanFull()



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
