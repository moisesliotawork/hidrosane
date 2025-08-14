<?php

namespace App\Filament\Repartidor\Resources;

use App\Filament\Repartidor\Resources\EntregaConVentaResource\Pages;
use App\Models\{Venta, Producto, Oferta, User, PostalCode, VentaOferta};
use App\Enums\{HorarioNotas, VendidoPor};
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\{TextColumn};
use Filament\Forms\Components\{
    Section,
    Grid,
    Select,
    TextInput,
    DatePicker,
    Toggle,
    Repeater,
    Hidden,
    Group,
    FileUpload,
    Placeholder,
    Textarea
};
use Illuminate\Validation\Rule;
use Filament\Forms\Components\Actions\Action as FormAction;


class EntregaConVentaResource extends Resource
{
    protected static ?string $model = Venta::class;

    protected static ?string $navigationLabel = 'Entregas con Venta';
    protected static ?string $modelLabel = 'Entrega con Venta';
    protected static ?string $pluralModelLabel = 'Entregas con Venta';
    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    public static function form(Form $form): Form
    {
        return $form->schema([

            Hidden::make('productos_externos')
                ->default(fn(?Venta $record) => $record->productos_externos ?? [])
                ->dehydrated(),

            /* Nº Nota (solo lectura bonito) */
            Placeholder::make('nro_nota')
                ->label('Nº Nota')
                ->content(fn(?Venta $record) => $record?->note?->nro_nota ?? '-')
                ->extraAttributes(['class' => 'text-2xl font-bold'])
                ->columnSpanFull(),

            Hidden::make('note_id')->required(),

            /* ───────────────── Información del cliente ───────────────── */
            Section::make('Información del cliente')
                ->relationship('customer')
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->schema([
                        TextInput::make('first_names')->label('Nombres')->required(),
                        TextInput::make('last_names')->label('Apellidos')->required(),
                        TextInput::make('dni')->label('DNI')->columnSpanFull(),

                        DatePicker::make('fecha_nac')->label('Fec. nac.')
                            ->timezone('Europe/Madrid')->native(false),

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
                                ]))
                            ->searchable(['code', 'city.title'])
                            ->preload()
                            ->native(false)
                            ->columnSpanFull()
                            ->validationMessages([
                                'required' => 'El código postal es obligatorio',
                            ]),

                        TextInput::make('primary_address')->label('Dirección 1')->columnSpanFull(),
                        TextInput::make('secondary_address')->label('Dirección 2')->columnSpanFull(),
                        TextInput::make('parish')->label('Parroquia'),

                        Select::make('tipo_vivienda')->label('Tipo de vivienda')
                            ->required()->options(\App\Enums\TipoVivienda::options())->native(false),

                        Select::make('estado_civil')->label('Estado civil')
                            ->required()->options(\App\Enums\EstadoCivil::options())->native(false),

                        Select::make('situacion_laboral')->label('Situación laboral')
                            ->required()->options(\App\Enums\SituacionLaboral::options())->native(false),

                        Select::make('ingresos_rango')->label('Ingresos netos mensuales')
                            ->required()->options(\App\Enums\IngresosRango::options())->native(false),

                        Select::make('num_hab_casa')->label('Número de habitaciones')
                            ->options(fn() => collect(range(1, 10))->mapWithKeys(fn($n) => [$n => (string) $n])->toArray())
                            ->searchable()->preload()->default(1)->required()->reactive(),

                        TextInput::make('iban')->label('IBAN')->columnSpanFull()
                            ->formatStateUsing(fn(?string $state) => $state ? implode(' ', str_split(strtoupper($state), 4)) : null)
                            ->dehydrateStateUsing(fn(?string $state) => $state ? str_replace(' ', '', strtoupper($state)) : null)
                            ->afterStateUpdated(function ($state, callable $set) {
                                $plain = str_replace(' ', '', strtoupper($state ?? ''));
                                $formatted = implode(' ', str_split($plain, 4));
                                if ($formatted !== $state)
                                    $set($formatted);
                            }),
                    ]),
                ]),

            /* ───────────────── Pareja/Compañero ───────────────── */
            Section::make('¿Estás en pareja con otro compañero?')
                ->schema([
                    Select::make('companion_id')
                        ->label('Compañero')
                        ->native(false)->searchable()
                        ->nullable()->default(null)
                        ->options(
                            fn() => ['' => 'SIN COMPAÑERO']
                            + User::role('commercial')
                                ->whereKeyNot(auth()->id())
                                ->select('id', 'empleado_id', 'name', 'last_name')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn($u) => [
                                    $u->id => "{$u->empleado_id} - {$u->name} {$u->last_name}",
                                ])
                                ->all()
                        )
                        ->dehydrateStateUsing(fn($state) => blank($state) ? null : $state),
                ]),

            /* ───────────────── Ofertas (lectura comercial + edición/creación repartidor) ───────────────── */
            Section::make('Ofertas incluidas')
                ->schema([
                    Repeater::make('ventaOfertas')
                        ->relationship()
                        ->createItemButtonLabel('Agregar Oferta')
                        ->addAction(fn($action) => $action->button()->color('success'))
                        ->minItems(1)
                        ->label(false)
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            $ids = collect($get('ventaOfertas') ?? [])
                                ->pluck('oferta_id')->filter()->all();

                            $precios = Oferta::query()
                                ->whereIn('id', $ids)
                                ->pluck('precio_base', 'id');

                            $total = collect($get('ventaOfertas') ?? [])
                                ->sum(fn($o) => (float) ($precios[$o['oferta_id']] ?? 0));

                            $set('importe_total', number_format($total, 2, '.', ''));
                        })
                        ->validationMessages([
                            'min' => 'Debes agregar al menos una oferta a la venta.',
                            'required' => 'Debes agregar al menos una oferta a la venta.',
                        ])
                        ->defaultItems(1)
                        ->itemLabel(
                            fn($state) =>
                            blank($state['oferta_id'] ?? null)
                            ? 'Nueva oferta'
                            : (Oferta::query()->whereKey($state['oferta_id'])->value('nombre') ?? 'Oferta')
                        )
                        ->deletable(true)
                        ->deleteAction(function (\Filament\Forms\Components\Actions\Action $action) {
                            $action
                                ->requiresConfirmation()
                                ->visible(function (array $arguments) {
                                    /** @var \App\Models\VentaOferta|null $record */
                                    $record = $arguments['record'] ?? null;   // ítem persistido (si existe)
                                    $state = $arguments['state'] ?? [];     // estado del ítem (si es nuevo)
                    
                                    // a) Si ya existe en BD, decidir con el modelo:
                                    if ($record) {
                                        return !$record->hasComercialLines();
                                    }

                                    // b) Ítem nuevo: decidir con el estado del formulario:
                                    $tieneComercial = collect($state['productos'] ?? [])
                                        ->contains(function ($l) {
                                        $v = $l['vendido_por'] ?? null;
                                        if ($v instanceof \App\Enums\VendidoPor)
                                            $v = $v->value;
                                        return $v === \App\Enums\VendidoPor::Comercial->value;
                                    });

                                    return !$tieneComercial; // solo mostrar “Eliminar” si NO hay líneas del comercial
                                });
                        })

                        ->schema([

                            /* Cabecera de la oferta */
                            Grid::make(3)->schema([
                                Select::make('oferta_id')
                                    ->label('Oferta')
                                    ->relationship('oferta', 'nombre')
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->required()
                                    // 🔒 Si hay alguna línea del comercial → bloquear
                                    // 🔓 Si se está creando (sin líneas) o todas son del repartidor → habilitar
                                    ->disabled(function (Get $get) {
                                        $lineas = collect($get('productos') ?? []);
                                        if ($lineas->isEmpty())
                                            return false; // creando
                                        $todasRep = $lineas->every(
                                            fn($l) => ($l['vendido_por'] ?? VendidoPor::Repartidor->value) === VendidoPor::Repartidor->value
                                        );
                                        return !$todasRep;
                                    })
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        $total = collect($get('../../../ventaOfertas') ?? [])
                                            ->sum(fn($o) => Oferta::find($o['oferta_id'] ?? 0)?->precio_base ?? 0);
                                        $set('../../../importe_total', number_format($total, 2, '.', ''));
                                    }),

                                TextInput::make('puntos') // total puntos del pack
                                    ->numeric()
                                    ->disabled(function (Get $get) {
                                        $lineas = collect($get('productos') ?? []);
                                        if ($lineas->isEmpty())
                                            return false;
                                        $todasRep = $lineas->every(
                                            fn($l) => ($l['vendido_por'] ?? VendidoPor::Repartidor->value) === VendidoPor::Repartidor->value
                                        );
                                        return !$todasRep;
                                    })
                                    ->dehydrated()
                                    ->reactive()
                                    ->helperText(function (Get $get) {
                                        $ofertaId = $get('oferta_id');
                                        $base = Oferta::find($ofertaId)?->puntos_base ?? 0;
                                        $total = (int) $get('puntos');
                                        $diff = $total - $base;
                                        return $diff === 0
                                            ? 'Igual a los puntos base'
                                            : ($diff > 0 ? "+{$diff} sobre el límite" : "{$diff} por debajo");
                                    }),

                                Placeholder::make('origen_pack')
                                    ->label('Origen del pack')
                                    ->content(function (Get $get) {
                                        $lineas = collect($get('productos') ?? []);
                                        if ($lineas->isEmpty() && blank($get('id'))) {
                                            return 'REPARTIDOR (nuevo)';
                                        }
                                        $hayRep = $lineas->contains(fn($l) => ($l['vendido_por'] ?? null) === VendidoPor::Repartidor->value);
                                        $hayCom = $lineas->contains(fn($l) => ($l['vendido_por'] ?? null) === VendidoPor::Comercial->value);
                                        return collect([$hayCom ? 'COMERCIAL' : null, $hayRep ? 'REPARTIDOR' : null])
                                            ->filter()->implode(' + ');
                                    })
                                    ->extraAttributes(['class' => 'text-xs font-bold']),
                            ]),

                            /* Productos de la oferta */
                            Section::make('Productos de la oferta')
                                ->collapsed()
                                ->schema([
                                    Repeater::make('productos')
                                        ->relationship()
                                        ->minItems(1)
                                        ->defaultItems(1)
                                        ->columns(5)
                                        ->deleteAction(
                                            fn(\Filament\Forms\Components\Actions\Action $action) =>
                                            $action
                                                ->requiresConfirmation()
                                                ->visible(function (\Filament\Forms\Get $get) {
                                                    // Puede venir enum o string: normalizamos
                                                    $v = $get('vendido_por');
                                                    if ($v instanceof \App\Enums\VendidoPor) {
                                                        $v = $v->value;
                                                    }
                                                    return $v !== \App\Enums\VendidoPor::Comercial->value;
                                                })
                                        )


                                        ->schema([
                                            Grid::make(5)->schema([
                                                Hidden::make('vendido_por')
                                                    ->default(VendidoPor::Repartidor->value)
                                                    ->dehydrated(),

                                                Select::make('producto_id')
                                                    ->label('Producto')
                                                    ->relationship('producto', 'nombre')
                                                    ->searchable()
                                                    ->preload()
                                                    ->reactive()
                                                    ->required()
                                                    ->disabled(fn(Get $get) => (string) ($get('vendido_por') ?? '') !== VendidoPor::Repartidor->value)
                                                    ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                                                        $puntos = (int) Producto::query()->whereKey($state)->value('puntos');
                                                        $nombre = Producto::query()->whereKey($state)->value('nombre');
                                                        if ($nombre === 'Producto Externo')
                                                            $set('cantidad', 1);

                                                        $cantidad = (int) ($get('cantidad') ?? 1);
                                                        $set('puntos_linea', $cantidad * $puntos);

                                                        $total = collect($get('../../productos') ?? [])
                                                            ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0));
                                                        $set('../../puntos', $total);
                                                    }),

                                                TextInput::make('cantidad')
                                                    ->label('Cant. vendida')
                                                    ->numeric()->minValue(1)->required()->reactive()
                                                    ->disabled(fn(Get $get) => (string) ($get('vendido_por') ?? '') !== VendidoPor::Repartidor->value)
                                                    ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                                                        $prodId = $get('producto_id');
                                                        $puntos = (int) Producto::query()->whereKey($prodId)->value('puntos');
                                                        $nombre = Producto::query()->whereKey($prodId)->value('nombre');

                                                        $cantidad = $nombre === 'Producto Externo' ? 1 : max((int) $state, 1);
                                                        $set('cantidad', $cantidad);
                                                        $set('puntos_linea', $cantidad * $puntos);

                                                        $total = collect($get('../../productos') ?? [])
                                                            ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0));
                                                        $set('../../puntos', $total);
                                                    }),

                                                TextInput::make('puntos_linea')
                                                    ->label('Pts Art.')
                                                    ->numeric()->disabled()->dehydrated(),

                                                Placeholder::make('vendido_por_badge')
                                                    ->label('Vendido por')
                                                    ->content(fn(Get $get) => strtoupper((string) ($get('vendido_por') ?? '')))
                                                    ->extraAttributes(function (Get $get) {
                                                        $v = (string) ($get('vendido_por') ?? '');
                                                        $base = 'inline-block px-2 py-1 rounded-md text-xs font-bold border';
                                                        return [
                                                            'class' => $v === VendidoPor::Comercial->value
                                                                ? $base . ' text-green-600 bg-green-50 border-green-200'
                                                                : $base . ' text-gray-600 bg-gray-50 border-gray-200',
                                                        ];
                                                    }),

                                                // ⬇️ SIEMPRE editable
                                                TextInput::make('cantidad_entregada')
                                                    ->label('Cant. entregada')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->dehydrated()
                                                    ->default(0),
                                            ]),
                                        ])

                                        // Defensa por si el hidden no llega
                                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                            $data['vendido_por'] = $data['vendido_por'] ?? VendidoPor::Repartidor->value;
                                            $data['cantidad_entregada'] = $data['cantidad_entregada'] ?? 0;
                                            return $data;
                                        })

                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            $total = collect($get('productos') ?? [])
                                                ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0));
                                            $set('puntos', $total);
                                        }),

                                ]),
                        ])
                        ->columns(1)
                        ->collapsible(),
                ]),

            /* ───────────────── Productos externos: capturar nombres libres ───────────────── */
            Section::make('Productos externos')
                ->visible(function (Get $get) {
                    $ids = collect($get('ventaOfertas') ?? [])
                        ->flatMap(fn($o) => $o['productos'] ?? [])
                        ->pluck('producto_id')->filter()->all();

                    if (empty($ids))
                        return false;

                    return Producto::query()
                        ->whereIn('id', $ids)
                        ->where('nombre', 'Producto Externo')
                        ->exists();
                })
                ->schema(function (Get $get) {
                    $lineas = collect($get('ventaOfertas') ?? [])
                        ->flatMap(fn($o) => $o['productos'] ?? [])
                        ->values();

                    $ids = $lineas->pluck('producto_id')->filter()->all();

                    $nombres = Producto::query()
                        ->whereIn('id', $ids)
                        ->pluck('nombre', 'id');

                    $externas = $lineas->filter(
                        fn($l) => ($nombres[$l['producto_id']] ?? '') === 'Producto Externo'
                    )->values();

                    return $externas->map(
                        fn($__, $idx) =>
                        TextInput::make("productos_externos.$idx")
                            ->label('Nombre producto externo #' . ($idx + 1))
                            ->required()
                            ->dehydrated()
                    )->all();
                })
                ->columns(1)
                ->collapsible()
                ->reactive(),

            /* ───────────────── Datos de la venta ───────────────── */
            Section::make('Datos de la venta')->schema([

                TextInput::make('importe_total')
                    ->label('Importe total (€)')
                    ->numeric()->prefix('€')->disabled()->dehydrated()->reactive()
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        $cuotas = (int) ($get('num_cuotas') ?? 1);
                        $importe = (float) $state;
                        $set('cuota_mensual', number_format($importe / max($cuotas, 1), 2, '.', ''));
                    }),

                Select::make('modalidad_pago')
                    ->label('Modalidad de pago')
                    ->options([
                        'Contado' => 'Contado',
                        'Financiado' => 'Financiado',
                        'NS' => 'NS',
                    ])
                    ->default('Financiado')->required()->reactive()
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        $cuotas = in_array($state, ['Contado', 'NS'], true) ? 1 : 6;
                        $set('num_cuotas', $cuotas);

                        $importe = (float) ($get('importe_total') ?? 0);
                        $set('cuota_mensual', number_format($importe / max($cuotas, 1), 2, '.', ''));

                        if ($state !== 'Contado')
                            $set('forma_pago', null);
                    }),

                Select::make('forma_pago')
                    ->label('Forma de pago')
                    ->options([
                        'datafono' => 'Datáfono (TPV)',
                        'giro_sepa' => 'Giro SEPA',
                        'transferencia_bancaria' => 'Transferencia bancaria',
                    ])
                    ->native(false)
                    ->visible(fn(Get $get) => $get('modalidad_pago') === 'Contado')
                    ->required(fn(Get $get) => $get('modalidad_pago') === 'Contado')
                    ->dehydrateStateUsing(fn($state, Get $get) => $get('modalidad_pago') === 'Contado' ? $state : null),

                Select::make('num_cuotas')
                    ->label('Nº de cuotas')
                    ->options(collect([1])->merge(range(6, 39))->mapWithKeys(fn($n) => [$n => $n])->toArray())
                    ->required()->reactive()->native(false)
                    ->disabled(fn(Get $get) => in_array($get('modalidad_pago'), ['Contado', 'NS'], true))
                    ->default(fn(Get $get) => in_array($get('modalidad_pago'), ['Contado', 'NS'], true) ? 1 : 6)
                    ->rules(['integer', Rule::in(array_merge([1], range(6, 39)))])
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        $importe = (float) ($get('importe_total') ?? 0);
                        $cuotas = (int) ($state ?: 1);
                        $set('cuota_mensual', number_format($importe / $cuotas, 2, '.', ''));
                    })
                    ->afterStateHydrated(function (Set $set, Get $get) {
                        if (in_array($get('modalidad_pago'), ['Contado', 'NS'], true))
                            $set('num_cuotas', 1);
                    }),

                TextInput::make('cuota_mensual')
                    ->label('Cuota mensual (€)')
                    ->numeric()->prefix('€')->disabled()->dehydrated()->reactive(),

                TextInput::make('accesorio_entregado')
                    ->label('¿Has entregado algún accesorio?'),
            ])->columns(2),

            /* ───────────────── Informe al Repartidor ───────────────── */
            Section::make('Informe al repartidor')->schema([
                Select::make('repartidor_id')
                    ->label('Repartidor')
                    ->options(fn() => User::role('delivery')
                        ->select('id', 'empleado_id')
                        ->orderBy('empleado_id')->get()
                        ->mapWithKeys(fn($u) => [$u->id => $u->empleado_id]))
                    ->searchable()->native(false)->placeholder('Seleccionar repartidor')
                    ->nullable()->preload()->columnSpanFull(),

                DatePicker::make('fecha_entrega')
                    ->label('Fecha de entrega')->required()
                    ->timezone('Europe/Madrid')->native(false),

                Select::make('horario_entrega')
                    ->label('Horario de entrega')
                    ->options(HorarioNotas::options())
                    ->native(false)->searchable()->required(),

                TextInput::make('motivo_venta')->label('Motivo de la venta'),
                TextInput::make('motivo_horario')->label('Motivo del horario'),

                Toggle::make('interes_art')
                    ->label('¿Al cliente le ha interesado más artículos que no le has vendido?')
                    ->reactive(),

                Textarea::make('interes_art_detalle')
                    ->label('Otros artículos de interés')
                    ->placeholder('Detalle los artículos que despertaron interés')
                    ->rows(3)->columnSpanFull()
                    ->visible(fn(Get $get) => (bool) $get('interes_art'))
                    ->required(fn(Get $get) => (bool) $get('interes_art'))
                    ->maxLength(500),

                Textarea::make('observaciones_repartidor')
                    ->label('Observaciones')
                    ->rows(3)->columnSpanFull(),
            ])->columns(2),

            /* ───────────────── Gestión Documentos ───────────────── */
            Section::make('Gestión Documentos')
                ->schema([
                    self::docCard('precontractual', 'Precontractual', true),
                    self::docCard('dni_anverso', 'DNI – Anverso'),
                    self::docCard('dni_reverso', 'DNI – Reverso'),
                    self::docCard('documento_titularidad', 'Documento de titularidad'),
                    self::docCard('nomina', 'Nómina'),
                    self::docCard('pension', 'Pensión'),
                    self::docCard('contrato_firmado', 'Contrato firmado'),
                ])
                ->columns(1)->columnSpanFull(),

            /* ───────────────── Extras de la entrega ───────────────── */
            Section::make('Extras de la entrega')
                ->schema([
                    Grid::make(3)->schema([
                        Toggle::make('reparto_extras.cliente_firma_garantias')
                            ->label('Cliente firma garantías')
                            ->default(fn(?Venta $r) => (bool) $r?->reparto?->cliente_firma_garantias)
                            ->dehydrated(false),

                        Toggle::make('reparto_extras.cliente_comentario_goodwork')
                            ->label('Cliente comentó en GoodWork')
                            ->default(fn(?Venta $r) => (bool) $r?->reparto?->cliente_comentario_goodwork)
                            ->dehydrated(false),

                        Toggle::make('reparto_extras.cliente_firma_digital')
                            ->label('Cliente firma digital')
                            ->default(fn(?Venta $r) => (bool) $r?->reparto?->cliente_firma_digital)
                            ->dehydrated(false),
                    ]),
                ])->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('note.nro_nota')->label('Nº Nota')->sortable()->searchable(),
                TextColumn::make('customer.name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('fecha_venta')->label('Fecha venta')->date('d/m/Y')->sortable(),
                TextColumn::make('hora_venta')->label('Hora')->state(fn(Venta $r) => optional($r->fecha_venta)->format('H:i'))->sortable(),
                TextColumn::make('comercial.name')->label('Comercial')->sortable()->searchable(),
                TextColumn::make('fecha_entrega')->label('F. repartidor')->date('d/m/Y'),
                TextColumn::make('horario_entrega')->label('Horario rep.'),
                TextColumn::make('customer.primary_address')->label('Dirección')->toggleable(isToggledHiddenByDefault: true)->wrap(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEntregaConVentas::route('/'),
            'edit' => Pages\EditEntregaConVenta::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static function docCard(string $field, string $label, bool $required = false): Group
    {
        return Group::make([
            Placeholder::make("{$field}_title")
                ->content(strtoupper($label))
                ->extraAttributes(['class' => 'text-xl font-extrabold'])
                ->label(""),

            Placeholder::make("{$field}_desc")
                ->content(
                    "Este espacio está diseñado para que puedas actualizar y modificar el archivo de "
                    . "<strong>{$label}</strong>. Es necesario actualizarlo para mantener tus datos al día."
                )
                ->label(""),

            FileUpload::make($field)
                ->label("")
                ->disk('public')
                ->directory('ventas')
                ->preserveFilenames()
                ->openable()
                ->downloadable()
                ->required($required) // ⬅️ aquí se vuelve requerido si $required=true
                ->validationMessages([
                    'required' => "El documento {$label} es obligatorio.",
                ])
                ->extraAttributes([
                    'class' => 'border-2 border-dashed py-16',
                ])
                ->columnSpanFull(),
        ])->columns(1);
    }
}
