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
    Grid,
    FileUpload,
    Group
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

            Hidden::make('productos_externos')
                ->default(fn(?Venta $record) => $record->productos_externos ?? [])
                ->dehydrated(),

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
                            $set('cuota_mensual', number_format(
                                (float) $state /
                                max((int) ($get('num_cuotas') ?? 1), 1),
                                2,
                                '.',
                                ''
                            ))
                        ),

                    Select::make('modalidad_pago')
                        ->label('Modalidad de pago')
                        ->options([
                            'Contado' => 'Contado',
                            'Financiado' => 'Financiado',
                            'NS' => 'NS',          // 👈 nueva opción
                        ])
                        ->default('Financiado')
                        ->required()
                        ->reactive()
                        ->debounce(300)
                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            /* Si es Contado o NS → 1 cuota; si no, 6 */
                            $cuotas = in_array($state, ['Contado', 'NS'], true) ? 1 : 6;
                            $importe = (float) ($get('importe_total') ?? 0);

                            $set('num_cuotas', $cuotas);
                            $set('cuota_mensual', number_format(
                                $importe / max($cuotas, 1),
                                2,
                                '.',
                                ''
                            ));
                        }),

                    TextInput::make('num_cuotas')
                        ->label('Nº de cuotas')
                        ->numeric()
                        ->required()
                        ->reactive()
                        ->disabled(fn(Get $get) => in_array($get('modalidad_pago'), ['Contado', 'NS'], true))
                        ->afterStateUpdated(
                            fn(Get $get, Set $set, $state) =>
                            $set('cuota_mensual', number_format(
                                (float) ($get('importe_total') ?? 0) /
                                max((int) $state, 1),
                                2,
                                '.',
                                ''
                            ))
                        ),

                    TextInput::make('accesorio_entregado')->label('¿Has entregado algún accesorio?'),

                    TextInput::make('cuota_mensual')
                        ->label('Cuota mensual (€)')
                        ->numeric()
                        ->prefix('€')
                        ->disabled()
                        ->dehydrated()
                        ->reactive(),
                ])
                ->columns(2),

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
                            label: fn($state): string => blank($state['oferta_id'])
                            ? 'Nueva oferta'
                            : Oferta::query()->whereKey($state['oferta_id'])->value('nombre') ?? 'Oferta eliminada'
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
                                                        $producto = Producto::find($state);
                                                        $cantidad = (int) ($get('cantidad') ?? 1);


                                                        if ($producto && $producto->nombre !== 'Producto Externo') {
                                                            $set('puntos_linea', $cantidad * ($producto->puntos ?? 0));
                                                        }

                                                        // Total puntos oferta
                                                        $set(
                                                            '../../puntos',
                                                            collect($get('../../productos'))->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0))
                                                        );
                                                    }),


                                                /* CANTIDAD */
                                                TextInput::make('cantidad')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->default(1)
                                                    ->required()
                                                    ->reactive()
                                                    ->readOnly(
                                                        fn(Get $get) =>           // ← cambia disabled() por readOnly()
                                                        optional(Producto::find($get('producto_id')))->nombre === 'Producto Externo'
                                                    )
                                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                        // fuerza a 1 si es externo, y ≥1 en caso normal
                                                        $producto = Producto::find($get('producto_id'));
                                                        $cantidad = $producto?->nombre === 'Producto Externo'
                                                            ? 1
                                                            : max((int) $state, 1);

                                                        $set('cantidad', $cantidad);
                                                        $set('puntos_linea', $cantidad * ($producto?->puntos ?? 0));

                                                        // total de puntos de la oferta
                                                        $total = collect($get('../../productos') ?? [])
                                                            ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0));
                                                        $set('../../puntos', $total);
                                                    }),


                                                TextInput::make('puntos_linea')
                                                    ->label('Pts línea')
                                                    ->numeric()
                                                    ->required()
                                                    ->dehydrated()
                                                    ->reactive()
                                                    ->readOnly(
                                                        fn(Get $get) =>
                                                        optional(\App\Models\Producto::find($get('producto_id')))->nombre !== 'Producto Externo'
                                                    )
                                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                        // actualiza total puntos en la oferta si se modifican manualmente
                                                        $set(
                                                            '../../puntos',
                                                            collect($get('../../productos'))
                                                                ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0))
                                                        );
                                                    }),

                                            ]),
                                        ])->columns(1),
                                ]),

                        ])->columns(1)->collapsible(),
                ]),


            Section::make('Productos externos')
                ->visible(
                    fn(Get $get) =>
                    collect($get('ventaOfertas'))
                        ->flatMap(fn($o) => $o['productos'] ?? [])
                        ->contains(
                            fn($l) =>
                            optional(Producto::find($l['producto_id'] ?? null))->nombre === 'Producto Externo'
                        )
                )
                ->schema(function (Get $get) {
                    $fields = [];
                    $index = 0;

                    foreach ($get('ventaOfertas') ?? [] as $oferta) {
                        foreach ($oferta['productos'] ?? [] as $producto) {
                            if (optional(Producto::find($producto['producto_id'] ?? null))->nombre === 'Producto Externo') {
                                $fields[] = Grid::make(3)->schema([
                                    TextInput::make("productos_externos.$index")
                                        ->label('Nombre producto externo #' . ($index + 1))
                                        ->required()
                                        ->columnSpan(2)
                                        ->live(onBlur: true)     //  ⬅️  cambió: ya no envía cada tecla
                                        ->key("prod-ext-$index"),
                                ]);
                                $index++;
                            }
                        }
                    }

                    return $fields;
                })
                ->columns(1)
                ->reactive()
                ->collapsible(),

            /* ────────── Datos de la venta ────────── */

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
            Section::make('Gestión Documentos')
                ->schema([
                    self::docCard('precontractual', 'Precontractual'),
                    self::docCard('dni_anverso', 'DNI – Anverso'),
                    self::docCard('dni_reverso', 'DNI – Reverso'),
                    self::docCard('documento_titularidad', 'Documento de titularidad'),
                    self::docCard('nomina', 'Nómina'),
                    self::docCard('pension', 'Pensión'),
                    self::docCard('contrato_firmado', 'Contrato firmado'),
                ])
                ->columns(1)
                ->columnSpanFull(),

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

    protected static function docCard(string $field, string $label): Group
    {
        return Group::make([
            Placeholder::make("{$field}_title")
                ->content(strtoupper($label))
                ->extraAttributes(['class' => 'text-xl font-extrabold'])
                ->label(""),

            Placeholder::make("{$field}_desc")
                ->content("Este espacio está diseñado para actualizar el archivo de <strong>{$label}</strong>.")
                ->label(""),

            FileUpload::make($field)
                ->label("")
                ->disk('public')
                ->directory('ventas')
                ->preserveFilenames()
                ->openable()
                ->downloadable()
                ->extraAttributes(['class' => 'border-2 border-dashed py-16'])
                ->columnSpanFull(),
        ]);
    }

}
