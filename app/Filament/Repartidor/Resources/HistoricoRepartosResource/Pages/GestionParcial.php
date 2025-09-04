<?php

namespace App\Filament\Repartidor\Resources\HistoricoRepartosResource\Pages;

use App\Filament\Repartidor\Resources\HistoricoRepartosResource;
use Filament\Resources\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Components\{Section, Grid, Repeater, Select, TextInput, Placeholder};
use Filament\Notifications\Notification;
use App\Models\{Reparto, Venta, Oferta, Producto};
use Filament\Forms\Get;

class GestionParcial extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = HistoricoRepartosResource::class;
    protected static string $view = 'filament.repartidor.historico-repartos.parcial';

    /** Reparto actual (viene en la ruta /{record}/parcial) */
    public Reparto $record;

    /** Venta asociada al reparto */
    public ?Venta $venta = null;

    /** Estado del form */
    public ?array $data = [];

    public function getTitle(): string
    {
        return 'Gestión de entrega parcial';
    }

    public function mount(Reparto $record): void
    {
        $this->record = $record;
        $this->venta = $record->venta;

        abort_unless($this->venta, 404, 'El reparto no tiene una venta asociada.');

        // precargar (no hace falta pasar nada: relaciones se cargan al render)
        $this->form->fill([]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->venta)     // ¡importante para que funcionen relationship()!
            ->statePath('data')
            ->schema([
                Section::make('Ofertas incluidas')
                    ->schema([
                        Repeater::make('ventaOfertas')
                            ->relationship()
                            ->disableItemCreation()
                            ->disableItemDeletion()
                            ->disableItemMovement()
                            ->columns(1)
                            ->label(false)
                            ->itemLabel(
                                fn($state) =>
                                blank($state['oferta_id'] ?? null)
                                ? 'Oferta'
                                : Oferta::query()->whereKey($state['oferta_id'])->value('nombre')
                            )
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('oferta_id')
                                        ->label('Oferta')
                                        ->relationship('oferta', 'nombre')
                                        ->searchable()->preload()->disabled(),

                                    TextInput::make('puntos')
                                        ->numeric()->disabled()->dehydrated()
                                        ->helperText(function (Get $get) {
                                            $base = Oferta::find($get('oferta_id'))?->puntos_base ?? 0;
                                            $total = (int) $get('puntos');
                                            $diff = $total - $base;
                                            return $diff === 0 ? 'Igual a los puntos base'
                                                : ($diff > 0 ? "+$diff sobre el límite" : "$diff por debajo");
                                        }),
                                ]),

                                Section::make('Productos de la oferta')
                                    ->collapsed(false)
                                    ->schema([
                                        Repeater::make('productos')
                                            ->relationship()
                                            ->minItems(1)
                                            ->defaultItems(1)
                                            ->columns(1)
                                            ->schema([
                                                Grid::make(5)->schema([

                                                    Select::make('producto_id')
                                                        ->label('Producto')
                                                        ->options(
                                                            fn() => Producto::query()
                                                                ->where('delete', false)           // ← oculta los borrados lógicos
                                                                ->orderBy('nombre')
                                                                ->pluck('nombre', 'id')
                                                                ->all()
                                                        )
                                                        ->getOptionLabelUsing(
                                                            fn($value) =>
                                                            Producto::find($value)?->nombre
                                                            ?? 'Producto eliminado (no disponible)'  // si el registro apunta a uno eliminado
                                                        )
                                                        ->searchable()
                                                        ->preload()
                                                        ->disabled(),

                                                    TextInput::make('cantidad')
                                                        ->numeric()->label('Cant. vendida')->disabled(),

                                                    TextInput::make('puntos_linea')
                                                        ->label('Pts Art.')->numeric()->disabled(),

                                                    Placeholder::make('vendido_por_badge')
                                                        ->label('Vendido por')
                                                        ->content(function (Get $get) {
                                                            $v = (string) ($get('vendido_por') ?? '');
                                                            return $v === 'comercial' ? 'COMERCIAL'
                                                                : ($v === 'repartidor' ? 'REPARTIDOR' : '—');
                                                        })
                                                        ->extraAttributes(function (Get $get) {
                                                            $v = (string) ($get('vendido_por') ?? '');
                                                            $base = 'inline-block px-2 py-1 rounded-md text-xs font-bold border';
                                                            return [
                                                                'class' =>
                                                                    $v === 'comercial'
                                                                    ? "$base text-green-600 bg-green-50 border-green-200"
                                                                    : "$base text-gray-600  bg-gray-50  border-gray-200",
                                                            ];
                                                        }),

                                                    // ÚNICO CAMPO EDITABLE
                                                    TextInput::make('cantidad_entregada')
                                                        ->numeric()
                                                        ->minValue(0)
                                                        ->label('Cant. entregada')
                                                        ->required()
                                                        ->helperText('Unidades realmente entregadas'),
                                                ]),
                                            ]),
                                    ]),
                            ])->columns(1)->collapsible(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    /** Guardar cambios y recalcular estado de entrega */
    public function save(): void
    {
        // Guardar relaciones del form
        $data = $this->form->getState();
        $this->venta->save();                 // por si hay atributos del modelo base
        $this->form->saveRelationships();     // guarda Repeater + nested relationship

        // Recalcular estado_entrega del reparto
        $this->venta->refresh();
        $this->venta->refreshEstadoEntrega();

        Notification::make()
            ->title('Cantidades entregadas actualizadas')
            ->success()
            ->send();

        redirect()->to(
            HistoricoRepartosResource::getUrl('index', panel: 'repartidor')
        );
    }
}
