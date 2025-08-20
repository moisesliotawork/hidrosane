<?php

namespace App\Filament\Repartidor\Resources\EntregaConVentaResource\Pages;

use App\Enums\VendidoPor;
use App\Filament\Repartidor\Resources\EntregaConVentaResource;
use App\Models\Oferta;
use App\Models\Producto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\{Section, Repeater, Grid, Select, TextInput, Hidden};
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Filament\Actions\Action;

class AgregarOfertasRepartidor extends EditRecord
{
    protected static string $resource = EntregaConVentaResource::class;

    /* ---------- Encabezados ---------- */
    public function getTitle(): string
    {
        return 'Agregar Oferta Como REPARTIDOR';
    }

    public function getHeading(): string
    {
        return 'Agregar Oferta Como REPARTIDOR';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Agregar ofertas (Repartidor)')
                ->description('Aquí sólo agregas nuevas ofertas; no se muestran las del comercial.')
                ->schema([
                    // No deshidratar: no existe en ventas.
                    Repeater::make('ofertas_repartidor')
                        ->dehydrated(false)
                        ->label(false)
                        ->columns(1)
                        ->defaultItems(1)
                        ->createItemButtonLabel('Añadir oferta')
                        ->collapsible()         // ← permite colapsar
                        ->collapsed()           // ← inician colapsadas
                        ->itemLabel(
                            fn($state) =>
                            blank($state['oferta_id'] ?? null)
                            ? 'Nueva oferta'
                            : (Oferta::query()->whereKey($state['oferta_id'])->value('nombre') ?? 'Oferta')
                        )
                        ->schema([
                            /* Cabecera de la oferta */
                            Grid::make(3)->schema([
                                Select::make('oferta_id')
                                    ->label('Oferta')
                                    ->options(fn() => Oferta::query()
                                        ->orderBy('nombre')
                                        ->pluck('nombre', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->reactive(),

                                TextInput::make('puntos')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText(function (Get $get) {
                                        $base = Oferta::find($get('oferta_id'))?->puntos_base ?? 0;
                                        $total = (int) $get('puntos');
                                        $diff = $total - $base;
                                        return $diff === 0
                                            ? 'Igual a puntos base'
                                            : ($diff > 0 ? "+$diff sobre el límite" : "$diff por debajo");
                                    }),
                            ]),

                            /* Productos de esa oferta */
                            Section::make('Productos de la oferta')
                                ->collapsed()
                                ->schema([
                                    Repeater::make('productos')
                                        ->minItems(1)
                                        ->defaultItems(1)
                                        ->columns(1)
                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            $total = collect($get('productos') ?? [])
                                                ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0));
                                            $set('puntos', $total);
                                        })
                                        ->schema([
                                            Grid::make(3)->schema([
                                                Hidden::make('vendido_por')
                                                    ->default(VendidoPor::Repartidor->value),

                                                Select::make('producto_id')
                                                    ->label('Producto')
                                                    ->options(fn() => Producto::query()
                                                        ->orderBy('nombre')
                                                        ->pluck('nombre', 'id'))
                                                    ->required()
                                                    ->searchable()
                                                    ->preload()
                                                    ->reactive()
                                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                        $nombre = Producto::query()->whereKey($state)->value('nombre');
                                                        $pts = (int) Producto::query()->whereKey($state)->value('puntos');

                                                        if ($nombre === 'Producto Externo') {
                                                            $set('cantidad', 1);
                                                        }

                                                        $cantidad = (int) ($get('cantidad') ?? 1);
                                                        $set('puntos_linea', $cantidad * $pts);

                                                        $total = collect($get('../../productos') ?? [])
                                                            ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0));
                                                        $set('../../puntos', $total);
                                                    }),

                                                TextInput::make('cantidad')
                                                    ->label('Cant. vendida')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->default(1)
                                                    ->required()
                                                    ->reactive()
                                                    ->readOnly(fn(Get $get) =>
                                                        Producto::query()->whereKey($get('producto_id'))->value('nombre') === 'Producto Externo')
                                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                        $nombre = Producto::query()->whereKey($get('producto_id'))->value('nombre');
                                                        $pts = (int) Producto::query()->whereKey($get('producto_id'))->value('puntos');

                                                        $cantidad = $nombre === 'Producto Externo' ? 1 : max((int) $state, 1);
                                                        $set('cantidad', $cantidad);
                                                        $set('puntos_linea', $cantidad * $pts);

                                                        $total = collect($get('../../productos') ?? [])
                                                            ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0));
                                                        $set('../../puntos', $total);
                                                    }),

                                                TextInput::make('puntos_linea')
                                                    ->label('Pts art.')
                                                    ->numeric()
                                                    ->disabled()
                                                    ->dehydrated(false),
                                            ]),
                                        ]),
                                ]),
                        ]),
                ]),
        ]);
    }

    /* Evita que intente persistir el repeater en 'ventas'. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['ofertas_repartidor']);
        return $data;
    }

    /* ÚNICO botón del formulario: “Siguiente” */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Siguiente')
                ->color('primary'),
        ];
    }

    /* Tras guardar, ir al edit de Entrega con Venta. */
    protected function getRedirectUrl(): string
    {
        return EntregaConVentaResource::getUrl('edit', ['record' => $this->getRecord()->getKey()]);
    }

    /* Guardado de ofertas + recálculo total */
    protected function afterSave(): void
    {
        /** @var \App\Models\Venta $venta */
        $venta = $this->getRecord();
        $state = $this->form->getRawState();   // incluye campos no-dehydrated
        $packs = $state['ofertas_repartidor'] ?? [];

        if (empty($packs)) {
            Notification::make()->title('No agregaste ofertas nuevas.')->info()->send();
            return;
        }

        DB::transaction(function () use ($venta, $packs) {
            foreach ($packs as $pack) {
                $ofertaId = $pack['oferta_id'] ?? null;
                if (!$ofertaId)
                    continue;

                $puntosPack = (int) collect($pack['productos'] ?? [])
                    ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0));

                $vo = $venta->ventaOfertas()->create([
                    'oferta_id' => $ofertaId,
                    'puntos' => $puntosPack,
                ]);

                foreach (($pack['productos'] ?? []) as $l) {
                    $productoId = $l['producto_id'] ?? null;
                    $cantidad = (int) ($l['cantidad'] ?? 0);
                    if (!$productoId || $cantidad <= 0)
                        continue;

                    $vo->productos()->create([
                        'producto_id' => $productoId,
                        'cantidad' => $cantidad,
                        'cantidad_entregada' => 0,
                        'puntos_linea' => (int) ($l['puntos_linea'] ?? 0),
                        'vendido_por' => VendidoPor::Repartidor->value,
                    ]);
                }
            }

            $nuevoTotal = $venta->ventaOfertas()
                ->with('oferta:id,precio_base')
                ->get()
                ->sum(fn($vo) => (float) ($vo->oferta->precio_base ?? 0));

            $venta->update(['importe_total' => number_format($nuevoTotal, 2, '.', '')]);
        });

        // Limpia el buffer
        $this->form->fill(['ofertas_repartidor' => []]);

        Notification::make()
            ->title('Ofertas agregadas')
            ->body('Se añadieron las ofertas del repartidor y se actualizó el importe total.')
            ->success()
            ->send();
    }
}
