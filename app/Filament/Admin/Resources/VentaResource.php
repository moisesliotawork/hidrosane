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
use App\Enums\EstadoVenta;
use App\Enums\Financiera;

class VentaResource extends Resource
{

    protected static ?string $model = Venta::class;
    protected static ?string $navigationLabel = 'Contratos';
    protected static ?string $modelLabel = 'Contrato';
    protected static ?string $pluralModelLabel = 'Contratos';
    protected static ?string $breadcrumb = 'Contratos';
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

            Select::make('estado_venta')
                ->label('Estado de la venta')
                ->options(
                    collect(EstadoVenta::cases())
                        ->mapWithKeys(fn($e) => [$e->value => $e->label()])
                        ->toArray()
                )
                ->default(EstadoVenta::EN_REVISION->value)
                ->required()
                ->native(false)
                ->searchable()
                ->columnSpanFull(),

            /* guarda la relación con la nota; no se muestra */
            Hidden::make('note_id')->required(),


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
                    Select::make('motivo_venta')
                        ->label('¿Por qué vendiste?')
                        ->options([
                            'Eliminación de miedos' => 'Eliminación de miedos',
                            'Placer' => 'Placer',
                            'Me compró el cliente' => 'Me compró el cliente',
                            'Muy rebatido de objeciones' => 'Muy rebatido de objeciones',
                        ])
                        ->required()
                        ->native(false),

                    Select::make('motivo_horario')
                        ->label('¿Por qué pusiste ese horario?')
                        ->options([
                            '3ª personas' => '3ª personas',
                            'Se lo dije y marqué cuando firmó' => 'Se lo dije y marqué cuando firmó',
                            'No va a estar a otra hora en casa' => 'No va a estar a otra hora en casa',
                        ])
                        ->required()
                        ->native(false),
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
                            ->native(false),

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
                        ->options(
                            fn() => ['' => 'SIN COMPAÑERO']      // primera opción
                            + User::role('commercial')
                                ->whereKeyNot(auth()->id())
                                ->select('id', 'empleado_id', 'name', 'last_name')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn($u) => [
                                    $u->id => "{$u->empleado_id} - {$u->name} {$u->last_name}",
                                ])
                                ->all()                       // array que necesita Filament
                        )
                        ->dehydrateStateUsing(fn($state) => blank($state) ? null : $state),
                ]),

            Section::make('Datos de la venta')
                ->schema([
                    TextInput::make('importe_total')
                        ->label('Importe total (€)')
                        ->numeric()
                        ->prefix('€')
                        ->disabled()
                        ->dehydrated(false)
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


                    Select::make('num_cuotas')
                        ->label('Nº de cuotas')
                        ->options(
                            collect([1])->merge(range(6, 39))
                                ->mapWithKeys(fn($num) => [$num => $num])
                                ->toArray()
                        )
                        ->required()
                        ->reactive()
                        ->native(false)
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
                                                        $producto = Producto::find($state);   // Model|null
                                            
                                                        /** @var \App\Models\Producto|null $producto */   // ← esto aclara el tipo
                                                        $cantidad = (int) ($get('cantidad') ?? 1);

                                                        /** @var \App\Models\Producto|null $producto */
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
                                                        fn(Get $get) =>
                                                        Producto::query()
                                                            ->whereKey($get('producto_id'))
                                                            ->value('nombre') === 'Producto Externo'
                                                    )
                                                    ->afterStateUpdated(function (Get $get, Set $set, $state): void {

                                                        // ── Traemos lo justo 
                                                        $nombre = Producto::query()
                                                            ->whereKey($get('producto_id'))
                                                            ->value('nombre');
                                                        $puntosUnidad = (int) Producto::query()
                                                            ->whereKey($get('producto_id'))
                                                            ->value('puntos');

                                                        // ── Forzar cantidad
                                                        $cantidad = $nombre === 'Producto Externo'
                                                            ? 1
                                                            : max((int) $state, 1);

                                                        $set('cantidad', $cantidad);

                                                        // ── Puntos de la línea 
                                                        $set('puntos_linea', $cantidad * $puntosUnidad);

                                                        // ── Total de puntos de la oferta 
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
                                                        Producto::query()
                                                            ->whereKey($get('producto_id'))
                                                            ->value('nombre') !== 'Producto Externo'
                                                    )
                                                    ->afterStateUpdated(function (Get $get, Set $set, $state): void {

                                                        $set(
                                                            '../../puntos',
                                                            collect($get('../../productos') ?? [])
                                                                ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0))
                                                        );
                                                    }),


                                            ]),
                                        ])->columns(1),

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
                Tables\Columns\TextColumn::make('estado_venta')
                    ->badge()
                    ->color(fn(EstadoVenta $state): string => $state->color())
                    ->formatStateUsing(fn(EstadoVenta $state): string => $state->label())
                    ->sortable()
                    ->label('ESTADO/CONTR'),
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
                
                Tables\Actions\DeleteAction::make()
                    ->label('') // sin texto, solo ícono
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar contrato')
                    ->modalDescription('Esta acción eliminará el contrato y sus datos relacionados. ¿Deseas continuar?')
                    ->successNotificationTitle('Contrato eliminado'),
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
