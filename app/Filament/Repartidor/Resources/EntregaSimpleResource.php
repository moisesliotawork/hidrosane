<?php

namespace App\Filament\Repartidor\Resources;

use App\Models\{Venta, Producto, Oferta, User};
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
use App\Filament\Repartidor\Resources\EntregaSimpleResource\Pages;
use Filament\Forms\Components\Placeholder;
use App\Enums\Financiera;
use Carbon\Carbon;
use Illuminate\Support\HtmlString;


class EntregaSimpleResource extends Resource
{

    protected static ?string $model = Venta::class;
    protected static ?string $navigationLabel = 'Entregas Simple';
    protected static ?string $modelLabel = 'Entrega Simple';
    protected static ?string $pluralModelLabel = 'Entregas Simple';
    protected static ?string $breadcrumb = 'Entregas Simple';
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

                        DatePicker::make('fecha_nac')
                            ->label('Fec. nac.')
                            ->timezone('Europe/Madrid')
                            ->native(false)
                            ->maxDate(now())                 // evita fechas futuras
                            ->reactive()
                            ->afterStateHydrated(function ($state, Set $set) {
                                $set('age', $state ? Carbon::parse($state)->age : null);
                            })
                            ->afterStateUpdated(function ($state, Set $set) {
                                $set('age', $state ? Carbon::parse($state)->age : null);
                            }),

                        TextInput::make('age')
                            ->numeric()
                            ->label('Edad')
                            ->readOnly()                     // no editable
                            ->dehydrated(false),             // no se envía; la calcula el modelo

                        TextInput::make('phone')->label('Teléfono')->tel()->required(),
                        TextInput::make('secondary_phone')->label('Teléfono 2')->tel(),
                        TextInput::make('third_phone')->label('Teléfono 3')->tel(),
                        TextInput::make('email')->label('Email')->email()->columnSpanFull(),

                        TextInput::make('postal_code')
                            ->required()
                            ->maxLength(255)
                            ->label('Codigo Postal'),

                        TextInput::make('ciudad')
                            ->required()
                            ->maxLength(255)
                            ->label('Ciudad'),

                        TextInput::make('provincia')
                            ->required()
                            ->maxLength(255)
                            ->label('Provincia'),
                        TextInput::make('primary_address')->required()->label('Dirección 1')->columnSpanFull(),
                        TextInput::make('secondary_address')->label('Dirección 2')->columnSpanFull(),
                        

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

                        Select::make('num_hab_casa')->label('Número de personas que residen en la casa')
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
                                $set('iban', implode(' ', str_split($clean, 4))); // ← clave + valor
                            }),
                    ]),
                ]),


            /* ------------- Compañero -------------- */
            Section::make('¿Estás en pareja con otro compañero?')
                ->schema([
                    Section::make('¿Estás en pareja con otro compañero?')
                        ->schema([
                            Select::make('companion_id')
                                ->label('Compañero')
                                ->native(false)
                                ->searchable()
                                ->nullable()
                                ->default(null)
                                ->options(
                                    fn() => ['' => 'SIN COMPAÑERO']      // primera opción
                                    + User::role(['commercial', 'team_leader'])
                                        ->whereKeyNot(auth()->id())
                                        ->whereNull('baja')
                                        ->select('id', 'empleado_id', 'name', 'last_name')
                                        ->orderBy('name')
                                        ->distinct()
                                        ->get()
                                        ->mapWithKeys(fn($u) => [
                                            $u->id => "{$u->empleado_id} - {$u->name} {$u->last_name}",
                                        ])
                                        ->all()                       // array que necesita Filament
                                )
                                ->dehydrateStateUsing(fn($state) => blank($state) ? null : $state)

                        ])


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
                            if ($state !== 'Contado') {
                                $set('forma_pago', null);
                            }
                        }),

                    Select::make('financiera')
                        ->label('Financiera')
                        ->options(
                            collect(Financiera::cases())
                                ->mapWithKeys(fn($f) => [$f->value => $f->label()])
                                ->toArray()
                        )
                        ->nullable()
                        ->native(false)
                        ->searchable()
                        ->visible(fn(Get $get) => $get('modalidad_pago') === 'Financiado')
                        ->helperText('Seleccione la entidad financiera (opcional)'),

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
                        // si dejan de estar en “Contado”, vaciamos el campo
                        ->dehydrateStateUsing(
                            fn($state, Get $get) =>
                            $get('modalidad_pago') === 'Contado' ? $state : null
                        ),


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

                    TextInput::make('cuota_mensual')
                        ->label('Cuota mensual (€)')
                        ->numeric()
                        ->prefix('€')
                        ->disabled()
                        ->dehydrated()
                        ->reactive(),

                    TextInput::make('accesorio_entregado')->label('¿Has entregado algún accesorio?'),

                    Toggle::make('crema')
                        ->label('¿Incluye crema?')
                        ->default(false),
                ])
                ->columns(2),

            /* ------------- Ofertas --------------- */
            Section::make('Ofertas incluidas')
                ->schema([
                    Repeater::make('ventaOfertas')
                        ->relationship()

                        /* ⬇️ 1) SOLO LECTURA  */
                        ->disableItemCreation()   // no “Agregar oferta”
                        ->disableItemDeletion()   // no papelera
                        ->disableItemMovement()   // no drag-and-drop
                        ->columns(1)              // (opcional) evita auto-grid

                        /* el botón lo ocultamos al desactivar creación */
                        ->label(false)
                        ->itemLabel(
                            fn($state) =>
                            blank($state['oferta_id'] ?? null)
                            ? 'Nueva oferta'
                            : Oferta::query()
                                ->whereKey($state['oferta_id'])
                                ->value('nombre')
                        )

                        /* ───────── Cabecera de la oferta ───────── */
                        ->schema([
                            Grid::make(3)->schema([
                                Select::make('oferta_id')
                                    ->label('Oferta')
                                    ->relationship('oferta', 'nombre')
                                    ->searchable()
                                    ->preload()
                                    ->disabled(),          // ⬅️ 2) bloqueado

                                TextInput::make('puntos')
                                    ->numeric()
                                    ->disabled()           // 3) ya bloqueado
                                    ->dehydrated()
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
                                        ->schema([
                                            Grid::make(5)->schema([

                                                /* PRODUCTO (solo lectura) */
                                                Select::make('producto_id')
                                                    ->label('Producto')
                                                    ->options(
                                                        fn() => Producto::query()
                                                            ->where('delete', false)        // ← ocultar eliminados lógicamente
                                                            ->orderBy('nombre')
                                                            ->pluck('nombre', 'id')
                                                            ->all()
                                                    )
                                                    ->getOptionLabelUsing(
                                                        fn($value) =>
                                                        Producto::find($value)?->nombre
                                                        ?? 'Producto eliminado (no disponible)' // si el registro guardado ya fue eliminado
                                                    )
                                                    ->searchable()
                                                    ->preload()
                                                    ->disabled(),

                                                /* CANTIDAD VENDIDA (solo lectura) */
                                                TextInput::make('cantidad')
                                                    ->numeric()
                                                    ->label('Cant. vendida')
                                                    ->disabled(),          // no editable

                                                /* PUNTOS (solo lectura) */
                                                TextInput::make('puntos_linea')
                                                    ->label('Pts Art.')
                                                    ->numeric()
                                                    ->disabled(),          // no editable

                                                Forms\Components\Placeholder::make('vendido_por_badge')
                                                    ->label('Vendido por')
                                                    ->content(function (Get $get) {
                                                        $v = (string) ($get('vendido_por') ?? '');
                                                        return $v === 'comercial'
                                                            ? 'COMERCIAL'
                                                            : ($v === 'repartidor' ? 'REPARTIDOR' : '—');
                                                    })
                                                    ->extraAttributes(function (Get $get) {
                                                        $v = (string) ($get('vendido_por') ?? '');
                                                        $base = 'inline-block px-2 py-1 rounded-md text-xs font-bold border';
                                                        return [
                                                            'class' => $v === 'comercial'
                                                                ? $base . ' text-green-600 bg-green-50 border-green-200'
                                                                : $base . ' text-gray-600 bg-gray-50 border-gray-200',
                                                        ];
                                                    })
                                                    ->columnSpan(1),

                                                /* NUEVA: CANTIDAD ENTREGADA */
                                                TextInput::make('cantidad_entregada')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->label('Cant. entregada')
                                                    ->required()
                                                    ->helperText('Unidades realmente entregadas'),


                                            ]),
                                        ])
                                        ->columns(1),
                                ]),

                        ])->columns(1)->collapsible(),
                ]),


            Section::make('Productos externos')
                // 1️⃣  Mostrar la sección solo si hay al menos un “Producto Externo”
                ->visible(function (Get $get) {

                    // IDs de todos los productos en las ofertas
                    $ids = collect($get('ventaOfertas') ?? [])
                        ->flatMap(fn($oferta) => $oferta['productos'] ?? [])
                        ->pluck('producto_id')
                        ->filter()
                        ->all();

                    if (empty($ids)) {
                        return false;   // sin productos ⇒ nada que mostrar
                    }

                    return Producto::query()
                        ->whereIn('id', $ids)
                        ->where('nombre', 'Producto Externo')
                        ->exists();     // true si al menos uno es externo
                })

                // 2️⃣  Generar inputs solo para los externos
                ->schema(function (Get $get) {

                    // a) Agrupamos todos los productos y sus posiciones
                    $lineas = collect($get('ventaOfertas') ?? [])
                        ->flatMap(fn($oferta) => $oferta['productos'] ?? [])
                        ->values();   // renumeramos para que el índice sea 0-n
        
                    // b) Todos los IDs presentes
                    $ids = $lineas->pluck('producto_id')->filter()->all();

                    // c) Mapa [id => nombre] para saber cuáles son externos
                    $nombres = Producto::query()
                        ->whereIn('id', $ids)
                        ->pluck('nombre', 'id');   // ej. [17 => 'Producto Externo', 22 => 'Colchón']
        
                    // d) Filtramos solo los que son “Producto Externo”
                    $externas = $lineas->filter(
                        fn($l) => ($nombres[$l['producto_id']] ?? '') === 'Producto Externo'
                    )->values();

                    // e) Creamos un TextInput por cada línea externa
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


            /* ────────── Datos de la venta ────────── */

            Section::make('Informe al repartidor')
                ->schema([
                    Select::make('repartidor_id')
                        ->label('Repartidor')
                        ->options(
                            fn() => User::role('delivery')
                                ->select('id', 'empleado_id')
                                ->orderBy('empleado_id')
                                ->get()
                                ->mapWithKeys(fn($user) => [$user->id => $user->empleado_id])
                        )
                        ->searchable()
                        ->native(false)
                        ->placeholder('Seleccionar repartidor')
                        ->nullable()
                        ->preload()
                        ->columnSpanFull(),
                    DatePicker::make('fecha_entrega')
                        ->label('Fecha de entrega')
                        ->required()
                        ->timezone('Europe/Madrid')
                        ->native(false),
                    Select::make('horario_entrega')
                        ->label('Horario de entrega')
                        ->options(HorarioNotas::options())
                        ->native(false)
                        ->searchable()
                        ->required(),
                    TextInput::make('motivo_venta')->label('Motivo de la venta'),
                    TextInput::make('motivo_horario')->label('Motivo del horario'),
                    Toggle::make('interes_art')
                        ->label('¿Al cliente le ha interesado más artículos que no le has vendido?')
                        ->reactive(),

                    Forms\Components\Textarea::make('interes_art_detalle')
                        ->label('Otros artículos de interés')
                        ->placeholder('Detalle los artículos que despertaron interés')
                        ->rows(3)
                        ->columnSpanFull()
                        ->visible(fn(Get $get) => (bool) $get('interes_art'))
                        ->required(fn(Get $get) => (bool) $get('interes_art'))
                        ->maxLength(500),

                    Forms\Components\Textarea::make('observaciones_repartidor')
                        ->label('Observaciones')
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2),
            Section::make('Gestión Documentos')
                ->schema([
                    self::docCard('precontractual', 'Precontractual', true),
                    self::docCard('dni_anverso', 'DNI – Anverso'),
                    self::docCard('dni_reverso', 'DNI – Reverso'),
                    self::docCard('documento_titularidad', 'Documento de titularidad'),
                    self::docCard('nomina', 'Nómina'),
                    self::docCard('pension', 'Pensión'),
                    self::docCard('contrato_firmado', 'Otro Documento'),
                ])
                ->columns(1)
                ->columnSpanFull(),

            Section::make('Extras de la entrega')
                ->schema([
                    Grid::make(3)->schema([
                        Toggle::make('reparto_extras.cliente_firma_garantias')
                            ->label('Cliente firma garantías')
                            ->default(fn(?Venta $record) => (bool) $record?->reparto?->cliente_firma_garantias)
                            ->dehydrated(false),

                        Toggle::make('reparto_extras.cliente_comentario_goodwork')
                            ->label('Cliente comentó en GoodWork')
                            ->default(fn(?Venta $record) => (bool) $record?->reparto?->cliente_comentario_goodwork)
                            ->dehydrated(false),

                        Toggle::make('reparto_extras.cliente_firma_digital')
                            ->label('Cliente firma digital')
                            ->default(fn(?Venta $record) => (bool) $record?->reparto?->cliente_firma_digital)
                            ->dehydrated(false),
                    ]),
                ])
                ->columns(1),
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
            'index' => Pages\ListEntregaSimples::route('/'),
            'edit' => Pages\EditEntregaSimple::route('/{record}/edit'),
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

    protected static function docCard(string $field, string $label, bool $required = false): Group
    {
        return Group::make([
            Placeholder::make("{$field}_title")
                ->content(strtoupper($label))
                ->extraAttributes(['class' => 'text-xl font-extrabold'])
                ->label(""),

            // ↓ Aquí usamos HtmlString para que el <strong> se renderice
            Placeholder::make("{$field}_desc")
                ->content(new HtmlString(
                    "Este espacio está diseñado para que puedas actualizar y modificar el archivo de " .
                    "<strong>{$label}</strong>. Es necesario actualizarlo para mantener tus datos al día."
                ))
                ->label(""),

            // ↓ También en el aviso rojo
            Placeholder::make("{$field}_required_notice")
                ->label('')
                ->content(new HtmlString(
                    '<div class="text-red-500 text-l font-bold leading-6">
            ❗ El documento <strong>' . e($label) . '</strong> es <strong>obligatorio</strong>.
        </div>'
                ))
                ->visible(fn(Get $get) => $required && blank($get($field))),

            FileUpload::make($field)
                ->label("")
                ->disk('public')
                ->directory('ventas')
                ->preserveFilenames()
                ->openable()
                ->downloadable()
                ->required($required)

                ->validationMessages([
                    'required' => "El documento {$label} es obligatorio.",
                ])
                ->extraAttributes(['class' => 'border-2 border-dashed py-16'])
                ->columnSpanFull(),
        ])->columns(1);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

}
