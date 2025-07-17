<?php

namespace App\Filament\Admin\Resources;

use App\Models\{Venta, Producto, Oferta, User, PostalCode};
use App\Enums\HorarioNotas;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\{
    Section,
    Select,
    TextInput,
    DatePicker,
    Toggle,
    Repeater,
    Hidden,
    Grid
};
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\{TextColumn, ToggleColumn};
use App\Filament\Admin\Resources\VentaResource\Pages;
use Filament\Forms\Components\Placeholder;

class VentaResource extends Resource
{

    protected static ?string $model = Venta::class;
    protected static ?string $navigationLabel = 'Ventas';
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';


    public static function form(Form $form): Form
    {
        return $form->schema([

            Placeholder::make('nro_nota')
                ->label('Nº Nota')
                ->content(fn(?Venta $record) => $record?->note?->nro_nota ?? '-')
                ->extraAttributes(['class' => 'text-2xl font-bold'])   // tamaño y peso
                ->columnSpanFull(),

            /* guarda la relación con la nota; no se muestra */
            Hidden::make('note_id')->required(),

            /* ───────── Información del cliente ────────── */
            Section::make('Información del cliente')
                ->relationship('customer')   // ← ¡clave!
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->schema([
                        TextInput::make('first_names')->label('Nombres')->required(),
                        TextInput::make('last_names')->label('Apellidos')->required(),
                        TextInput::make('dni')->label('DNI')->columnSpanFull(),

                        DatePicker::make('fecha_nac')->label('Fec. nac.'),
                        TextInput::make('age')->numeric()->label('Edad'),

                        TextInput::make('phone')->label('Teléfono')->tel()->required(),
                        TextInput::make('secondary_phone')->label('Teléfono 2')->tel(),
                        TextInput::make('email')->label('Email')->email()->columnSpanFull(),

                        Select::make('postal_code_id')
                            ->label('Código postal')
                            ->required()
                            ->options(fn() => PostalCode::query()
                                ->select('postal_codes.id', 'postal_codes.code', 'cities.title as city_title')
                                ->join('cities', 'cities.id', '=', 'postal_codes.city_id')
                                ->orderBy('cities.title')
                                ->orderBy('postal_codes.code')
                                ->limit(500)
                                ->get()
                                ->mapWithKeys(fn($item) => [
                                    $item->id => "{$item->city_title} - {$item->code}",
                                ])
                                ->all())
                            ->searchable(['code', 'city.title'])
                            ->preload()
                            ->native(false)
                            ->columnSpanFull(),

                        TextInput::make('primary_address')->label('Dirección 1')->columnSpanFull(),
                        TextInput::make('secondary_address')->label('Dirección 2')->columnSpanFull(),
                        TextInput::make('parish')->label('Parroquia'),

                        Select::make('tipo_vivienda')->label('Tipo de vivienda')
                            ->options(\App\Enums\TipoVivienda::options())
                            ->required()
                            ->native(false),

                        Select::make('estado_civil')->label('Estado civil')
                            ->options(\App\Enums\EstadoCivil::options())
                            ->required()
                            ->native(false),

                        Select::make('situacion_laboral')->label('Situación laboral')
                            ->options(\App\Enums\SituacionLaboral::options())
                            ->required()
                            ->native(false),

                        Select::make('ingresos_rango')->label('Ingresos netos mensuales')
                            ->options(\App\Enums\IngresosRango::options())
                            ->required()
                            ->native(false),

                        Select::make('num_hab_casa')->label('Número de habitaciones')
                            ->options(fn() => collect(range(1, 10))
                                ->mapWithKeys(fn($n) => [$n => $n])
                                ->all())
                            ->default(1)
                            ->required()
                            ->reactive(),

                        /* ---------- IBAN con formato ---------- */
                        TextInput::make('iban')
                            ->label('IBAN')
                            ->columnSpanFull()
                            ->formatStateUsing(
                                fn($state) =>
                                $state
                                ? implode(' ', str_split(strtoupper($state), 4))
                                : null
                            )
                            ->dehydrateStateUsing(
                                fn($state) =>
                                $state
                                ? str_replace(' ', '', strtoupper($state))
                                : null
                            )
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                $clean = str_replace(' ', '', strtoupper($state ?? ''));
                                $set(implode(' ', str_split($clean, 4)));
                            }),
                    ]),
                ]),


            /* ------------- Compañero -------------- */
            Section::make('¿Estás en pareja con otro compañero?')
                ->schema([
                    Select::make('companion_id')
                        ->label('Compañero')
                        ->searchable()
                        ->native(false)
                        ->nullable()
                        ->default(null)
                        ->options(fn() => User::role('commercial')
                            ->whereKeyNot(auth()->id())
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn($u) => [
                                $u->id => "{$u->empleado_id} - {$u->name} {$u->last_name}",
                            ])
                            ->prepend('SIN COMPAÑERO', '')
                            ->all())                       //  ← array, no Collection
                        ->dehydrateStateUsing(fn($state) => blank($state) ? null : $state),
                ]),

            /* ------------- Ofertas --------------- */
            Section::make('Ofertas incluidas')
                ->schema([
                    Repeater::make('ventaOfertas')
                        ->relationship()
                        ->minItems(1)
                        ->label(false)
                        ->defaultItems(1)
                        ->createItemButtonLabel('Agregar Oferta')
                        ->afterStateUpdated(
                            fn(Get $get, Set $set) =>
                            $set(
                                'importe_total',
                                collect($get('ventaOfertas'))->sum(
                                    fn($o) =>
                                    Oferta::find($o['oferta_id'] ?? 0)?->precio_base ?? 0
                                )
                            )
                        )
                        ->itemLabel(
                            fn($state) =>
                            blank($state['oferta_id'] ?? null)
                            ? 'Nueva oferta'
                            : Oferta::find($state['oferta_id'])?->nombre
                        )
                        ->schema([
                            /* ---------- Cabecera de Oferta ---------- */
                            Grid::make(3)->schema([
                                Select::make('oferta_id')
                                    ->label('Oferta')
                                    ->relationship('oferta', 'nombre')
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->required()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $set(
                                            '../../../importe_total',
                                            collect($get('../../../ventaOfertas'))->sum(
                                                fn($o) =>
                                                Oferta::find($o['oferta_id'] ?? 0)?->precio_base ?? 0
                                            )
                                        );
                                    }),

                                TextInput::make('puntos')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->reactive()
                                    ->helperText(function (Get $get) {
                                        $base = Oferta::find($get('oferta_id'))?->puntos_base ?? 0;
                                        $total = (int) $get('puntos');
                                        $diff = $total - $base;

                                        return $diff === 0
                                            ? 'Igual a los puntos base'
                                            : ($diff > 0 ? "+$diff sobre el límite" : "$diff por debajo");
                                    }),
                            ]),

                            /* ---------- Detalle de productos ---------- */
                            Section::make('Productos de la oferta')
                                ->collapsed()
                                ->schema([
                                    Repeater::make('productos')
                                        ->relationship()
                                        ->minItems(1)
                                        ->defaultItems(1)
                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            // recalcula puntos de la oferta
                                            $set(
                                                '../../puntos',
                                                collect($get('productos'))
                                                    ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0))
                                            );
                                        })
                                        ->schema([
                                            Grid::make(4)->schema([
                                                /* PRODUCTO */
                                                Select::make('producto_id')
                                                    ->label('Producto')
                                                    ->relationship('producto', 'nombre')
                                                    ->searchable()
                                                    ->preload()
                                                    ->reactive()
                                                    ->required()
                                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                        $producto = \App\Models\Producto::find($state);
                                                        $cantidad = (int) ($get('cantidad') ?? 1);
                                                        $unit = $producto && $producto->nombre !== 'Producto Externo'
                                                            ? ($producto->puntos ?? 0)
                                                            : (int) ($get('puntos_manual') ?? 0);

                                                        $set('puntos_linea', $cantidad * $unit);

                                                        $set(
                                                            '../../puntos',
                                                            collect($get('../../productos'))
                                                                ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0))
                                                        );
                                                    }),

                                                /* CANTIDAD */
                                                TextInput::make('cantidad')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->required()
                                                    ->default(1)
                                                    ->reactive()
                                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                        $state = (int) ($state ?: 1);
                                                        if ($state < 1) {
                                                            $state = 1;
                                                            $set('cantidad', 1);
                                                        }

                                                        $producto = \App\Models\Producto::find($get('producto_id'));
                                                        $unit = $producto && $producto->nombre !== 'Producto Externo'
                                                            ? ($producto->puntos ?? 0)
                                                            : (int) ($get('puntos_manual') ?? 0);

                                                        $set('puntos_linea', $state * $unit);

                                                        // puntos totales de la oferta
                                                        $set(
                                                            '../../puntos',
                                                            collect($get('../../productos'))
                                                                ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0))
                                                        );
                                                    }),


                                                TextInput::make('puntos_linea')
                                                    ->label('Pts línea')
                                                    ->numeric()
                                                    ->required()
                                                    ->readonly()
                                                    ->dehydrated()
                                                    ->reactive()
                                                    ->live(),

                                                Hidden::make('puntos_manual')
                                                    ->reactive(),
                                            ]),
                                        ])->columns(1),
                                ]),

                        ])->columns(1)->collapsible(),
                ]),


            /* ------------ Productos externos ------------- */
            Section::make('Productos externos')
                ->visible(
                    fn(Get $get) =>
                    collect($get('ventaOfertas'))
                        ->flatMap(fn($o) => $o['productos'] ?? [])
                        ->contains(
                            fn($l) =>
                            optional(\App\Models\Producto::find($l['producto_id']))->nombre === 'Producto Externo'
                        )
                )
                ->schema(function (Get $get) {
                    /** @var array<array{int,int}> $indices  [ofertaIdx, productoIdx] */
                    $indices = [];
                    foreach (($get('ventaOfertas') ?? []) as $ofIdx => $oferta) {
                        foreach (($oferta['productos'] ?? []) as $prIdx => $linea) {
                            if (optional(\App\Models\Producto::find($linea['producto_id']))->nombre === 'Producto Externo') {
                                $indices[] = [$ofIdx, $prIdx];
                            }
                        }
                    }

                    return collect($indices)->map(function (array $pair, int $idx) {
                        [$ofIdx, $prIdx] = $pair;

                        return Grid::make(3)->schema([
                            /* NOMBRE */
                            TextInput::make("productos_externos.$idx")
                                ->label('Nombre producto externo #' . ($idx + 1))
                                ->required()
                                ->columnSpan(2)
                                ->dehydrated(),

                            /* PTS UNIDAD */
                            TextInput::make("ventaOfertas.$ofIdx.productos.$prIdx.puntos_manual")
                                ->label('Pts unidad')
                                ->numeric()
                                ->required()
                                ->afterStateUpdated(function (Get $get, Set $set, $unit) use ($ofIdx, $prIdx) {
                                    $cantidad = (int) ($get("ventaOfertas.$ofIdx.productos.$prIdx.cantidad") ?? 1);

                                    // actualiza Pts línea dentro del repeater
                                    $set(
                                        "ventaOfertas.$ofIdx.productos.$prIdx.puntos_linea",
                                        $cantidad * (int) $unit
                                    );

                                    // total de la oferta
                                    $set(
                                        "ventaOfertas.$ofIdx.puntos",
                                        collect($get("ventaOfertas.$ofIdx.productos"))
                                            ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0))
                                    );
                                }),
                        ]);
                    })->all();
                })
                ->reactive()
                ->columns(1)
                ->collapsible(),

            /* ------------ Datos de la Venta ------------- */
            Section::make('Datos de la venta')
                ->schema([
                    TextInput::make('importe_total')
                        ->label('Importe total (€)')
                        ->numeric()
                        ->prefix('€')
                        ->disabled()
                        ->dehydrated()
                        ->reactive()
                        ->afterStateUpdated(
                            fn(Get $get, Set $set, $state) =>
                            $set(
                                'cuota_mensual',
                                number_format(
                                    (float) $state / max((int) ($get('num_cuotas') ?? 1), 1),
                                    2,
                                    '.',
                                    ''
                                )
                            )
                        ),

                    TextInput::make('num_cuotas')
                        ->label('Nº de cuotas')
                        ->numeric()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(
                            fn(Get $get, Set $set, $state) =>
                            $set(
                                'cuota_mensual',
                                number_format(
                                    (float) ($get('importe_total') ?? 0) / max((int) $state, 1),
                                    2,
                                    '.',
                                    ''
                                )
                            )
                        ),

                    TextInput::make('accesorio_entregado')->label('¿Has entregado algún accesorio?'),

                    TextInput::make('cuota_mensual')
                        ->label('Cuota mensual (€)')
                        ->numeric()
                        ->prefix('€')
                        ->disabled()
                        ->dehydrated()
                        ->reactive(),
                ])->columns(2),

            Section::make('Informe al repartidor')
                ->schema([
                    DatePicker::make('fecha_entrega')->label('Fecha de entrega')->required(),
                    Select::make('horario_entrega')
                        ->label('Horario de entrega')
                        ->options(HorarioNotas::options())
                        ->native(false)
                        ->searchable()
                        ->required(),
                    TextInput::make('motivo_venta')->label('Motivo de la venta'),
                    TextInput::make('motivo_horario')->label('Motivo del horario'),
                    Toggle::make('interes_art')->label('¿Interés en otros artículos?'),
                    Forms\Components\Textarea::make('observaciones_repartidor')
                        ->label('Observaciones')
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    /* ------------------------------------------------------------------------
     | TABLA (LISTADO)
     * ---------------------------------------------------------------------*/
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('note.nro_nota')->label('Nº Nota')->sortable()->searchable(),
                TextColumn::make('customer.name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('fecha_venta')->label('Fecha venta')->date('d/m/Y')->sortable(),
                TextColumn::make('hora_venta')
                    ->label('Hora')
                    ->state(fn(Venta $r) => optional($r->fecha_venta)->format('H:i'))
                    ->sortable(),
                TextColumn::make('comercial.name')->label('Comercial')->sortable()->searchable(),
                TextColumn::make('fecha_entrega')->label('F. repartidor')->date('d/m/Y'),
                TextColumn::make('horario_entrega')->label('Horario rep.'),
                TextColumn::make('customer.primary_address')
                    ->label('Dirección')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);  // sin bulk delete
    }

    /* ------------------------------------------------------------------------
     | RELACIONES, PÁGINAS, PERMISOS
     * ---------------------------------------------------------------------*/
    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVentas::route('/'),
            'edit' => Pages\EditVenta::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
    public static function canViewAny(): bool
    {
        return true;
    }
}
