<?php

namespace App\Filament\Commercial\Resources;

use App\Filament\Commercial\Resources\HistoricoContratosResource\Pages;
use App\Models\Venta;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\EstadoEntrega;      // por si quieres formatear labels de entrega
use function Filament\Facades\filament;
use App\Models\User;
use App\Models\Producto;
use App\Models\Oferta;
use App\Enums\HorarioNotas;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\SoftDeletingScope;
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
use Illuminate\Validation\Rule;
use Filament\Forms\Components\{
    Group,
    Placeholder,
    FileUpload
};

class HistoricoContratosResource extends Resource
{
    protected static ?string $model = Venta::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Histórico de Contratos';
    protected static ?string $pluralModelLabel = 'Histórico de Contratos';
    protected static ?string $slug = 'historico-contratos';

    public static function form(Form $form): Form
    {

        return $form
            ->schema([
                // 👇 este hidden queda a nivel Venta (fuera de la sección del cliente)
                Hidden::make('note_id')->required(),

                /* ---------- Cliente (editable) ---------- */
                Section::make('Información del cliente')
                    ->relationship('customer')   // ⬅️ clave: hidrata/guarda contra la relación customer
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 3,
                        ])->schema([
                                    TextInput::make('first_names')->label('Nombres')->required(),
                                    TextInput::make('last_names')->label('Apellidos')->required(),
                                    TextInput::make('dni')->label('DNI')->columnSpanFull(),
                                    DatePicker::make('fecha_nac')->label('Fec. nac.')->timezone('Europe/Madrid')->native(false),
                                    TextInput::make('age')->numeric()->label('Edad'),

                                    TextInput::make('phone')->label('Teléfono')->tel()->required(),
                                    TextInput::make('secondary_phone')->label('Teléfono 2')->tel(),
                                    TextInput::make('email')->label('Email')->email()->columnSpanFull(),

                                    Forms\Components\TextInput::make('postal_code')
                                        ->required()
                                        ->maxLength(255)
                                        ->label('Codigo Postal'),

                                    Forms\Components\TextInput::make('ciudad')
                                        ->required()
                                        ->maxLength(255)
                                        ->label('Ciudad'),

                                    Forms\Components\TextInput::make('provincia')
                                        ->required()
                                        ->maxLength(255)
                                        ->label('Provincia'),

                                    TextInput::make('primary_address')->label('Dirección 1')->columnSpanFull(),
                                    TextInput::make('secondary_address')->label('Dirección 2')->columnSpanFull(),
                                    TextInput::make('parish')->label('Parroquia'),

                                    Select::make('tipo_vivienda')->label('Tipo de vivienda')->required()->options(\App\Enums\TipoVivienda::options())->native(false),
                                    Select::make('estado_civil')->label('Estado civil')->required()->options(\App\Enums\EstadoCivil::options())->native(false),
                                    Select::make('situacion_laboral')->label('Situación laboral')->required()->options(\App\Enums\SituacionLaboral::options())->native(false),
                                    Select::make('ingresos_rango')->label('Ingresos netos mensuales')->required()->options(\App\Enums\IngresosRango::options())->native(false),
                                    Select::make('num_hab_casa')
                                        ->label('Número de habitaciones')
                                        ->options(fn() => collect(range(1, 10))->mapWithKeys(fn($n) => [$n => (string) $n])->toArray())
                                        ->searchable()->preload()->default(1)->required()->reactive(),

                                    TextInput::make('iban')
                                        ->label('IBAN')->columnSpanFull()
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

                /* ---------- Ofertas ---------- */
                Section::make('Ofertas incluidas')
                    ->schema([
                        Repeater::make('ventaOfertas')
                            ->relationship()
                            ->createItemButtonLabel('Agregar Oferta')
                            ->addAction(fn($action) => $action->button()->color('success'))
                            ->minItems(1)
                            ->label(false)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                // 1. IDs de ofertas presentes en el repeater
                                $ids = collect($get('ventaOfertas') ?? [])
                                    ->pluck('oferta_id')
                                    ->filter()
                                    ->all();

                                // 2. Traemos precio_base solo una vez
                                $precios = Oferta::query()
                                    ->whereIn('id', $ids)
                                    ->pluck('precio_base', 'id');   // [id => precio_base]
                    
                                // 3. Calculamos el total
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
                                : Oferta::query()
                                    ->whereKey($state['oferta_id'])
                                    ->value('nombre')
                            )
                            ->schema([

                                /* ─────────── Cabecera de la oferta ─────────── */
                                Grid::make(3)->schema([
                                    Select::make('oferta_id')
                                        ->label('Oferta')
                                        ->relationship('oferta', 'nombre')
                                        ->searchable()
                                        ->preload()
                                        ->reactive()
                                        ->required()
                                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                            // 1. actualizar total puntos (ya lo tienes arriba)
                                            // 2. recalcular importe_total global
                                            $total = collect($get('../../../ventaOfertas') ?? [])
                                                ->sum(function ($o) {
                                                return Oferta::find($o['oferta_id'] ?? 0)?->precio_base ?? 0;
                                            });

                                            $set('../../../importe_total', number_format($total, 2, '.', ''));
                                        }),

                                    TextInput::make('puntos')              // total puntos de la oferta
                                        ->numeric()
                                        ->disabled()
                                        ->dehydrated()
                                        ->reactive()                        // para refrescar el helper
                                        ->helperText(function (Get $get) {
                                            $ofertaId = $get('oferta_id');
                                            $base = Oferta::find($ofertaId)?->puntos_base ?? 0;
                                            $total = (int) $get('puntos');
                                            $diff = $total - $base;

                                            return $diff === 0
                                                ? 'Igual a los puntos base'
                                                : ($diff > 0
                                                    ? "+{$diff} sobre el límite"
                                                    : "{$diff} por debajo");
                                        }),
                                ]),

                                /* ─────────── Detalle de productos ─────────── */

                                Section::make('Productos de la oferta')
                                    ->collapsed()
                                    ->schema([
                                        Repeater::make('productos')
                                            ->relationship()
                                            ->minItems(1)
                                            ->validationMessages([
                                                'min' => 'Debes agregar al menos un producto a la oferta.',
                                                'required' => 'Debes agregar al menos un producto a la oferta.',
                                            ])
                                            ->defaultItems(1)

                                            /* Cuando se agrega o quita una línea */
                                            ->afterStateUpdated(function (Get $get, Set $set) {
                                                $total = collect($get('productos') ?? [])
                                                    ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0));

                                                $set('puntos', $total);
                                            })

                                            ->schema([
                                                Grid::make(3)->schema([

                                                    /* ─── Producto ─── */
                                                    Select::make('producto_id')
                                                        ->label('Producto')
                                                        ->relationship('producto', 'nombre')
                                                        ->searchable()
                                                        ->preload()
                                                        ->reactive()
                                                        ->required()
                                                        ->afterStateUpdated(function (Set $set, Get $get, $state): void {

                                                            // ──────────────────────────
                                                            // 1. Traemos solo lo necesario
                                                            // ──────────────────────────
                                                            $nombre = Producto::query()->whereKey($state)->value('nombre');
                                                            $puntosUnidad = (int) Producto::query()->whereKey($state)->value('puntos');

                                                            // ──────────────────────────
                                                            // 2. Forzar cantidad = 1 si es externo
                                                            // ──────────────────────────
                                                            if ($nombre === 'Producto Externo') {
                                                                $set('cantidad', 1);
                                                            }

                                                            // ──────────────────────────
                                                            // 3. Recalcular puntos de la línea
                                                            // ──────────────────────────
                                                            $cantidad = (int) ($get('cantidad') ?? 1);
                                                            $set('puntos_linea', $cantidad * $puntosUnidad);

                                                            // ──────────────────────────
                                                            // 4. Recalcular total de puntos de la oferta
                                                            // ──────────────────────────
                                                            $total = collect($get('../../productos') ?? [])
                                                                ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0));

                                                            $set('../../puntos', $total);
                                                        }),

                                                    /* ─── Cantidad ─── */
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


                                                    /* ─── Puntos línea (solo lectura) ─── */
                                                    TextInput::make('puntos_linea')
                                                        ->label("Total Puntos Seleccionados")
                                                        ->numeric()
                                                        ->disabled()
                                                        ->dehydrated(),
                                                ]),
                                            ])
                                            ->columns(1)
                                            ->itemLabel(
                                                ""
                                            ),
                                    ])
                                    ->columns(1),

                            ])
                            ->columns(1)
                            ->collapsible(),
                    ]),

                /* ---------- Productos externos ---------- */
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


                /* ---------- Datos de la venta ---------- */
                Section::make('Datos de la venta')->schema([

                    TextInput::make('importe_total')
                        ->label('Importe total (€)')
                        ->numeric()
                        ->prefix('€')
                        ->disabled()
                        ->dehydrated()
                        ->reactive()
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
                        ->default('Financiado')
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            if ($state === 'Contado' || $state === 'NS') {
                                $set('num_cuotas', 1);
                            } else {
                                $set('num_cuotas', 6); // valor por defecto al volver a Financiado
                            }

                            // Actualizar cuota mensual al cambiar modalidad
                            $importe = (float) ($get('importe_total') ?? 0);
                            $cuotas = (int) (
                                in_array($state, ['Contado', 'NS'], true)
                                ? 1
                                : ($get('num_cuotas') ?? 6)
                            );
                            $set('cuota_mensual', number_format($importe / max($cuotas, 1), 2, '.', ''));

                            // 2· Si ya **no** es Contado, vaciamos forma_pago
                            if ($state !== 'Contado') {
                                $set('forma_pago', null);
                            }
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

                        // ⬇️ 1. Desactivar si es Contado o NS
                        ->disabled(fn(Get $get) => in_array($get('modalidad_pago'), ['Contado', 'NS'], true))

                        // ⬇️ 2. Valor por defecto: 1 si es Contado o NS, 6 en los demás casos
                        ->default(fn(Get $get) => in_array($get('modalidad_pago'), ['Contado', 'NS'], true) ? 1 : 6)

                        ->rules([
                            'integer',
                            Rule::in(array_merge([1], range(6, 39))),
                        ])

                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            $importe = (float) ($get('importe_total') ?? 0);
                            $cuotas = (int) ($state ?: 1);
                            $set('cuota_mensual', number_format($importe / $cuotas, 2, '.', ''));
                        })

                        // ⬇️ 3. Forzar 1 cuota al hidratar si es Contado o NS
                        ->afterStateHydrated(function (Set $set, Get $get, $state) {
                            if (in_array($get('modalidad_pago'), ['Contado', 'NS'], true)) {
                                $set('num_cuotas', 1);
                            }
                        }),

                    TextInput::make('cuota_mensual')
                        ->label('Cuota mensual (€)')
                        ->numeric()
                        ->prefix('€')
                        ->disabled()
                        ->dehydrated()
                        ->reactive(),

                    TextInput::make('accesorio_entregado')
                        ->label('¿Haz entregado algun ACCESORIO AL CLIENTE?')
                        ->placeholder('Ej.: Almohada viscoelástica'),


                ])->columns(2),

                Section::make('Informe al REPARTIDOR')->schema([
                    DatePicker::make('fecha_entrega')
                        ->label('Fecha de entrega')
                        ->timezone('Europe/Madrid')
                        ->native(false)
                        ->required(),
                    Select::make('horario_entrega')
                        ->label('Horario de entrega')
                        ->options(HorarioNotas::options())
                        ->default(HorarioNotas::TD->value)
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
                        ->label('Observaciones adicionales para el repartidor')
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
                        self::docCard('contrato_firmado', 'Contrato firmado'),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

            ]);
    }

    /** Solo lectura */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        if (!$record instanceof Venta) {
            return false;
        }

        // Si no tiene fecha_venta, no editar
        if (blank($record->fecha_venta)) {
            return false;
        }

        // Hasta las 23:00 del mismo día
        $fechaLimite = $record->fecha_venta->copy()->setTime(23, 0, 0);

        return now()->lessThanOrEqualTo($fechaLimite);
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $inicio = now('Europe/Madrid')->startOfDay();
                $fin = now('Europe/Madrid')->setTime(23, 0, 0);

                return Venta::query()
                    ->with(['note.customer', 'comercial', 'reparto'])
                    // ⬇️ si NO es sales_manager, limitamos a sus propias ventas
                    ->when(
                        !static::isSalesManager(),
                        fn(Builder $q) =>
                        $q->where('comercial_id', auth()->id())
                    )
                    // Rango del día (igual que tenías)
                    ->whereBetween('fecha_venta', [$inicio, $fin])
                    ->latest('id');
            })
            ->columns([
                // ID / Nº de Nota
                TextColumn::make('note.nro_nota')
                    ->label('ID Nota')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state ? "Nota {$state}" : '-')
                    ->searchable(),

                // Fecha de declaración
                TextColumn::make('fecha_venta')
                    ->label('Fecha declaración')
                    ->dateTime('Y-m-d H:i'),

                TextColumn::make('nro_contr_adm')
                    ->label('Nº Contr. ADM')
                    ->badge()
                    ->alignCenter()
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn($state) => $state ? str_pad((string) $state, 5, '0', STR_PAD_LEFT) : '—'),

                // Cliente (nombre completo)
                TextColumn::make('cliente')
                    ->label('Cliente')
                    ->state(function (Venta $record) {
                        $c = $record->note?->customer;
                        return trim(($c->first_names ?? '') . ' ' . ($c->last_names ?? ''));
                    })
                    ->searchable(
                        query: function (Builder $query, string $search) {
                            $query->whereHas('note.customer', function (Builder $q) use ($search) {
                                $like = '%' . str_replace('%', '\%', $search) . '%';
                                $q->whereRaw("CONCAT(COALESCE(first_names,''),' ',COALESCE(last_names,'')) LIKE ?", [$like])
                                    ->orWhere('dni', 'like', $like)
                                    ->orWhere('phone', 'like', $like)
                                    ->orWhere('secondary_phone', 'like', $like);
                            });
                        }
                    ),

                TextColumn::make('note.customer.dni')
                    ->label('DNI')
                    ->sortable()
                    ->searchable(
                        query: function (Builder $query, string $search) {
                            $like = '%' . str_replace('%', '\%', $search) . '%';
                            $query->whereHas('note.customer', fn(Builder $q) => $q->where('dni', 'like', $like));
                        }
                    ),

                // Estado venta (enum con label/color)
                TextColumn::make('estado_venta')
                    ->label('Estado venta')
                    ->badge()
                    ->formatStateUsing(fn(Venta $r) => $r->estado_venta?->label() ?? '')
                    ->color(fn(Venta $r) => $r->estado_venta?->color()),
            ])
            ->defaultSort('id', 'desc')
            ->actions([

                Tables\Actions\EditAction::make()
                    ->label('')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn(Venta $record) => static::puedeEditarHastaLas23($record)),

                Tables\Actions\Action::make('docs')
                    ->label('+DOCS')
                    ->icon('heroicon-o-document-plus')
                    ->url(function (Venta $record) {

                        $panelId = 'comercial';

                        return static::getUrl(
                            'docs',
                            ['record' => $record],
                            panel: $panelId   // ⚠️ aquí debe ser 'comercial' (no 'commercial')
                        );
                    })
                    ->openUrlInNewTab(false),
            ])
            ->bulkActions([]);
    }

    protected static function puedeEditarHastaLas23(Venta $venta): bool
    {
        if (blank($venta->fecha_venta)) {
            return false;
        }

        $limite = $venta->fecha_venta->copy()->setTime(23, 0, 0);
        return now()->lessThanOrEqualTo($limite);
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHistoricoContratos::route('/'),
            'edit' => Pages\EditVenta::route('/{record}/edit'),
            'docs' => Pages\GestionDocumentos::route('/{record}/docs'),
        ];
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

    protected static function isSalesManager(): bool
    {
        return auth()->check() && auth()->user()->hasRole('sales_manager');
    }

}
