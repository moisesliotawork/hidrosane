<?php

namespace App\Filament\Commercial\Resources;

use App\Filament\Commercial\Resources\VentaDesdeCeroResource\Pages;
use App\Models\{Venta, User, Producto, Oferta, PostalCode};
use App\Enums\{HorarioNotas, NoteStatus};
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Components\{
    Section,
    Select,
    TextInput,
    DatePicker,
    Toggle,
    Repeater,
    Grid,
    Group,
    Placeholder,
    FileUpload,
    Textarea
};
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Validation\Rule;

class VentaDesdeCeroResource extends Resource
{
    protected static ?string $model = Venta::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-on-rectangle';

    // 👇 nombre en el menú lateral
    protected static ?string $navigationLabel = 'Puerta Fría';

    /** Ir directo al Create al hacer clic en el menú */
    public static function getNavigationUrl(): string
    {
        return static::getUrl('create');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            /* ==================== CLIENTE ==================== */
            Section::make('Información del cliente')->schema([
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

                    Forms\Components\Select::make('postal_code_id')
                        ->label('Código postal')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search) {
                            return \App\Models\PostalCode::query()
                                ->join('cities', 'cities.id', '=', 'postal_codes.city_id')
                                ->when(
                                    filled($search),
                                    fn($q) => $q->where(function ($q) use ($search) {
                                        $q->where('postal_codes.code', 'like', "%{$search}%")
                                            ->orWhere('cities.title', 'like', "%{$search}%");
                                    })
                                )
                                ->orderBy('cities.title')
                                ->orderBy('postal_codes.code')
                                ->limit(50)
                                ->select('postal_codes.id')                                       // <-- id
                                ->selectRaw("CONCAT(cities.title, ' - ', postal_codes.code) AS label") // <-- label
                                ->pluck('label', 'postal_codes.id');                               // <-- pluck por strings
                        })
                        ->getOptionLabelUsing(function ($value) {
                            if (!$value)
                                return null;

                            return \App\Models\PostalCode::query()
                                ->join('cities', 'cities.id', '=', 'postal_codes.city_id')
                                ->where('postal_codes.id', $value)
                                ->selectRaw("CONCAT(cities.title, ' - ', postal_codes.code) AS label")
                                ->value('label');
                        })
                        ->searchPrompt('Escribe ciudad o código…')
                        ->native(false)
                        ->validationMessages([
                            'required' => 'El código postal es obligatorio',
                        ]),

                    TextInput::make('primary_address')->label('Dirección 1')->columnSpanFull(),
                    TextInput::make('secondary_address')->label('Dirección 2')->columnSpanFull(),
                    TextInput::make('parish')->label('Parroquia'),

                    Select::make('tipo_vivienda')->label('Tipo de vivienda')
                        ->options(\App\Enums\TipoVivienda::options())->required()->native(false),

                    Select::make('estado_civil')->label('Estado civil')
                        ->options(\App\Enums\EstadoCivil::options())->required()->native(false),

                    Select::make('situacion_laboral')->label('Situación laboral')
                        ->options(\App\Enums\SituacionLaboral::options())->required()->native(false),

                    Select::make('ingresos_rango')->label('Ingresos netos mensuales')
                        ->options(\App\Enums\IngresosRango::options())->required()->native(false),

                    Select::make('num_hab_casa')->label('Número de personas que residen en la casa')
                        ->options(
                            fn() => collect(range(1, 10))
                                ->mapWithKeys(fn($n) => [$n => (string) $n])->toArray()
                        )
                        ->searchable()->preload()->default(1)->required()->reactive(),

                    TextInput::make('iban')
                        ->label('IBAN')
                        ->columnSpanFull()
                        ->formatStateUsing(fn(?string $state) => $state ? implode(' ', str_split(strtoupper($state), 4)) : null)
                        ->dehydrateStateUsing(fn(?string $state) => $state ? str_replace(' ', '', strtoupper($state)) : null)
                        ->afterStateUpdated(function (string $state, \Filament\Forms\Set $set) {
                            $plain = str_replace(' ', '', strtoupper($state ?? ''));
                            $formatted = implode(' ', str_split($plain, 4));

                            if ($formatted !== $state) {
                                // ✅ Set requiere (campo, valor)
                                $set('iban', $formatted);
                            }
                        }),

                ]),
            ]),

            /* ==================== NOTA ==================== */
            Section::make('Datos de la nota')->schema([
                Select::make('nota_comercial_id')
                    ->label('Comercial asignado a la nota')
                    ->options(
                        User::role('commercial')
                            ->select('id', 'empleado_id', 'name', 'last_name')
                            ->orderBy('name')->get()
                            ->mapWithKeys(fn($u) => [$u->id => "{$u->empleado_id} - {$u->name} {$u->last_name}"])
                            ->all()
                    )
                    ->searchable()->native(false)
                    ->default(fn() => auth()->id())->required(),

                Select::make('nota_status')->label('Estado de la nota')
                    ->options(NoteStatus::options())
                    ->default(NoteStatus::CONTACTED->value)
                    ->native(false)->reactive()->required(),

                DatePicker::make('nota_visit_date')->label('Fecha de visita')
                    ->timezone('Europe/Madrid')->native(false)
                    ->visible(fn(Forms\Get $get) => $get('nota_status') === NoteStatus::CONTACTED->value),

                Select::make('nota_visit_schedule')->label('Horario de visita')
                    ->options(HorarioNotas::options())
                    ->default(HorarioNotas::TD->value)
                    ->native(false)->searchable()
                    ->visible(fn(Forms\Get $get) => $get('nota_status') === NoteStatus::CONTACTED->value),

                TextInput::make('nota_ayuntamiento')->label('Ayuntamiento')->required(),

                Toggle::make('nota_de_camino')->label('¿De camino?')->default(false),
            ])->columns(2),

            /* ==================== COMPAÑERO ==================== */
            Section::make('¿Estás en pareja con otro compañero?')->schema([
                Select::make('companion_id')->label('Compañero')
                    ->native(false)->searchable()->nullable()->default(null)
                    ->options(
                        fn() => ['' => 'SIN COMPAÑERO'] + User::role(['commercial', 'team_leader'])
                            ->whereKeyNot(auth()->id())
                            ->select('id', 'empleado_id', 'name', 'last_name')->orderBy('name')->distinct()->get()
                            ->mapWithKeys(fn($u) => [$u->id => "{$u->empleado_id} - {$u->name} {$u->last_name}"])
                            ->all()
                    )
                    ->dehydrateStateUsing(fn($state) => blank($state) ? null : $state),
            ]),

            /* ==================== OFERTAS / PRODUCTOS ==================== */
            Section::make('Ofertas incluidas')->schema([
                Repeater::make('ventaOfertas')
                    ->relationship()
                    ->createItemButtonLabel('Agregar Oferta')
                    ->addAction(fn($action) => $action->button()->color('success'))
                    ->minItems(1)->label(false)
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        $ids = collect($get('ventaOfertas') ?? [])->pluck('oferta_id')->filter()->all();
                        $precios = Oferta::query()->whereIn('id', $ids)->pluck('precio_base', 'id');
                        $total = collect($get('ventaOfertas') ?? [])->sum(fn($o) => (float) ($precios[$o['oferta_id']] ?? 0));
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
                        : Oferta::query()->whereKey($state['oferta_id'])->value('nombre')
                    )
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('oferta_id')->label('Oferta')
                                ->relationship('oferta', 'nombre')->searchable()->preload()->reactive()->required()
                                ->afterStateUpdated(function (Set $set, Get $get) {
                                    $total = collect($get('../../../ventaOfertas') ?? [])->sum(
                                        fn($o) => Oferta::find($o['oferta_id'] ?? 0)?->precio_base ?? 0
                                    );
                                    $set('../../../importe_total', number_format($total, 2, '.', ''));
                                }),
                            TextInput::make('puntos')->numeric()->disabled()->dehydrated()->reactive()
                                ->helperText(function (Get $get) {
                                    $ofertaId = $get('oferta_id');
                                    $base = Oferta::find($ofertaId)?->puntos_base ?? 0;
                                    $total = (int) $get('puntos');
                                    $diff = $total - $base;
                                    return $diff === 0 ? 'Igual a los puntos base'
                                        : ($diff > 0 ? "+{$diff} sobre el límite" : "{$diff} por debajo");
                                }),
                        ]),
                        Section::make('Productos de la oferta')->collapsed()->schema([
                            Repeater::make('productos')->relationship()->minItems(1)
                                ->validationMessages([
                                    'min' => 'Debes agregar al menos un producto a la oferta.',
                                    'required' => 'Debes agregar al menos un producto a la oferta.',
                                ])
                                ->defaultItems(1)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $total = collect($get('productos') ?? [])
                                        ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0));
                                    $set('puntos', $total);
                                })
                                ->schema([
                                    Grid::make(3)->schema([
                                        Select::make('producto_id')->label('Producto')
                                            ->relationship('producto', 'nombre')
                                            ->searchable()->preload()->reactive()->required()
                                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                $nombre = Producto::query()->whereKey($state)->value('nombre');
                                                $puntosUnidad = (int) Producto::query()->whereKey($state)->value('puntos');
                                                if ($nombre === 'Producto Externo')
                                                    $set('cantidad', 1);
                                                $cantidad = (int) ($get('cantidad') ?? 1);
                                                $set('puntos_linea', $cantidad * $puntosUnidad);
                                                $total = collect($get('../../productos') ?? [])
                                                    ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0));
                                                $set('../../puntos', $total);
                                            }),

                                        TextInput::make('cantidad')->numeric()->minValue(1)->default(1)
                                            ->required()->reactive()
                                            ->readOnly(
                                                fn(Get $get) =>
                                                Producto::query()->whereKey($get('producto_id'))->value('nombre') === 'Producto Externo'
                                            )
                                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                $nombre = Producto::query()
                                                    ->whereKey($get('producto_id'))->value('nombre');
                                                $puntosUnidad = (int) Producto::query()
                                                    ->whereKey($get('producto_id'))->value('puntos');
                                                $cantidad = $nombre === 'Producto Externo' ? 1 : max((int) $state, 1);
                                                $set('cantidad', $cantidad);
                                                $set('puntos_linea', $cantidad * $puntosUnidad);
                                                $total = collect($get('../../productos') ?? [])
                                                    ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0));
                                                $set('../../puntos', $total);
                                            }),

                                        TextInput::make('puntos_linea')->label('Total Puntos Seleccionados')
                                            ->numeric()->disabled()->dehydrated(),
                                    ]),
                                ])->columns(1),
                        ])->columns(1),
                    ])
                    ->columns(1)
                    ->collapsible(),
            ]),

            /* ==================== PRODUCTOS EXTERNOS ==================== */
            Section::make('Productos externos')
                ->visible(function (Get $get) {
                    $ids = collect($get('ventaOfertas') ?? [])
                        ->flatMap(fn($o) => $o['productos'] ?? [])
                        ->pluck('producto_id')->filter()->all();
                    if (empty($ids))
                        return false;
                    return Producto::query()->whereIn('id', $ids)->where('nombre', 'Producto Externo')->exists();
                })
                ->schema(function (Get $get) {
                    $lineas = collect($get('ventaOfertas') ?? [])
                        ->flatMap(fn($o) => $o['productos'] ?? [])->values();
                    $ids = $lineas->pluck('producto_id')->filter()->all();
                    $nombres = Producto::query()->whereIn('id', $ids)->pluck('nombre', 'id');
                    $externas = $lineas->filter(fn($l) => ($nombres[$l['producto_id']] ?? '') === 'Producto Externo')->values();
                    return $externas->map(
                        fn($__, $idx) => TextInput::make("productos_externos.$idx")
                            ->label('Nombre producto externo #' . ($idx + 1))
                            ->required()->dehydrated()
                    )->all();
                })
                ->columns(1)->collapsible()->reactive(),

            /* ==================== DATOS DE LA VENTA ==================== */
            Section::make('Datos de la venta')->schema([

                TextInput::make('importe_total')->label('Importe total (€)')
                    ->numeric()->prefix('€')->disabled()->dehydrated()->reactive()
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        $cuotas = (int) ($get('num_cuotas') ?? 1);
                        $importe = (float) $state;
                        $set('cuota_mensual', number_format($importe / max($cuotas, 1), 2, '.', ''));
                    }),

                Select::make('modalidad_pago')->label('Modalidad de pago')
                    ->options(['Contado' => 'Contado', 'Financiado' => 'Financiado', 'NS' => 'NS'])
                    ->default('Financiado')->required()->reactive()
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        if (in_array($state, ['Contado', 'NS'], true))
                            $set('num_cuotas', 1);
                        else
                            $set('num_cuotas', 6);
                        $importe = (float) ($get('importe_total') ?? 0);
                        $cuotas = (int) (in_array($state, ['Contado', 'NS'], true) ? 1 : ($get('num_cuotas') ?? 6));
                        $set('cuota_mensual', number_format($importe / max($cuotas, 1), 2, '.', ''));
                        if ($state !== 'Contado')
                            $set('forma_pago', null);
                    }),

                Select::make('forma_pago')->label('Forma de pago')
                    ->options([
                        'datafono' => 'Datáfono (TPV)',
                        'giro_sepa' => 'Giro SEPA',
                        'transferencia_bancaria' => 'Transferencia bancaria',
                    ])
                    ->native(false)
                    ->visible(fn(Get $get) => $get('modalidad_pago') === 'Contado')
                    ->required(fn(Get $get) => $get('modalidad_pago') === 'Contado')
                    ->dehydrateStateUsing(fn($state, Get $get) => $get('modalidad_pago') === 'Contado' ? $state : null),

                Select::make('num_cuotas')->label('Nº de cuotas')
                    ->options(collect([1])->merge(range(6, 39))->mapWithKeys(fn($n) => [$n => $n])->toArray())
                    ->required()->reactive()->native(false)
                    ->disabled(fn(Get $get) => in_array($get('modalidad_pago'), ['Contado', 'NS'], true))
                    ->default(fn(Get $get) => in_array($get('modalidad_pago'), ['Contado', 'NS'], true) ? 1 : 6)
                    ->rules(['integer', Rule::in(array_merge([1], range(6, 39)))])
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        $importe = (float) ($get('importe_total') ?? 0);
                        $cuotas = (int) ($state ?: 1);
                        $set('cuota_mensual', number_format($importe / max($cuotas, 1), 2, '.', ''));
                    })
                    ->afterStateHydrated(function (Set $set, Get $get) {
                        if (in_array($get('modalidad_pago'), ['Contado', 'NS'], true))
                            $set('num_cuotas', 1);
                    }),

                TextInput::make('cuota_mensual')->label('Cuota mensual (€)')
                    ->numeric()->prefix('€')->disabled()->dehydrated()->reactive(),

                TextInput::make('accesorio_entregado')
                    ->label('¿Haz entregado algun ACCESORIO AL CLIENTE?')
                    ->placeholder('Ej.: Almohada viscoelástica'),

                Toggle::make('crema')
                    ->label('¿Incluye crema?')
                    ->default(false),
            ])->columns(2),

            Section::make('Informe al REPARTIDOR')->schema([
                DatePicker::make('fecha_entrega')->label('Fecha de entrega')
                    ->timezone('Europe/Madrid')->native(false)->required(),
                Select::make('horario_entrega')->label('Horario de entrega')
                    ->options(HorarioNotas::options())->default(HorarioNotas::TD->value)
                    ->native(false)->searchable()->required(),
                Select::make('motivo_venta')->label('¿Por qué vendiste?')->options([
                    'Eliminación de miedos' => 'Eliminación de miedos',
                    'Placer' => 'Placer',
                    'Me compró el cliente' => 'Me compró el cliente',
                    'Muy rebatido de objeciones' => 'Muy rebatido de objeciones',
                ])->required()->native(false),
                Select::make('motivo_horario')->label('¿Por qué pusiste ese horario?')->options([
                    '3ª personas' => '3ª personas',
                    'Se lo dije y marqué cuando firmó' => 'Se lo dije y marqué cuando firmó',
                    'No va a estar a otra hora en casa' => 'No va a estar a otra hora en casa',
                ])->required()->native(false),
                Toggle::make('interes_art')
                    ->label('¿Al cliente le ha interesado más artículos que no le has vendido?')
                    ->reactive(),
                Textarea::make('interes_art_detalle')->label('Otros artículos de interés')
                    ->placeholder('Detalle los artículos que despertaron interés')->rows(3)->columnSpanFull()
                    ->visible(fn(Get $get) => (bool) $get('interes_art'))
                    ->required(fn(Get $get) => (bool) $get('interes_art'))
                    ->maxLength(500),
                Textarea::make('observaciones_repartidor')
                    ->label('Observaciones adicionales para el repartidor')->rows(3)->columnSpanFull(),
            ])->columns(2),

            Section::make('Gestión Documentos')->schema([
                self::docCard('precontractual', 'Precontractual', true),
                self::docCard('dni_anverso', 'DNI – Anverso'),
                self::docCard('dni_reverso', 'DNI – Reverso'),
                self::docCard('documento_titularidad', 'Documento de titularidad'),
                self::docCard('nomina', 'Nómina'),
                self::docCard('pension', 'Pensión'),
                self::docCard('contrato_firmado', 'Contrato firmado'),
            ])->columns(1)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        // No se usa listado para este resource (entra directo al create)
        return $table;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVentaDesdeCero::route('/'),
            'create' => Pages\CreateVentaDesdeCero::route('/create'),
        ];
    }

    /** Helper para tarjetas de documentos */
    protected static function docCard(string $field, string $label, bool $required = false): Group
    {
        return Group::make([
            Placeholder::make("{$field}_title")
                ->content(strtoupper($label))
                ->extraAttributes(['class' => 'text-xl font-extrabold'])
                ->label(""),
            Placeholder::make("{$field}_desc")
                ->content("Este espacio está diseñado para que puedas actualizar y modificar el archivo de <strong>{$label}</strong>. Es necesario actualizarlo para mantener tus datos al día.")
                ->label(""),
            FileUpload::make($field)
                ->label("")->disk('public')->directory('ventas')
                ->preserveFilenames()->openable()->downloadable()
                ->required($required)
                ->validationMessages(['required' => "El documento {$label} es obligatorio."])
                ->extraAttributes(['class' => 'border-2 border-dashed py-16'])
                ->columnSpanFull(),
        ])->columns(1);
    }
}
