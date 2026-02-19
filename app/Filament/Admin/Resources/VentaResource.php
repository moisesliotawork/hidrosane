<?php

namespace App\Filament\Admin\Resources;

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
use App\Filament\Admin\Resources\VentaResource\Pages;
use Filament\Forms\Components\Placeholder;
use App\Enums\EstadoVenta;
use App\Enums\Financiera;
use Carbon\Carbon;
use App\Enums\MesesEnum;
use Illuminate\Support\HtmlString;
use App\Enums\VendidoPor;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Tables\Filters\SelectFilter;
use App\Enums\OrigenVenta;
use App\Enums\FuenteNotas;
use Filament\Tables\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use App\Exports\VentaDirectExport;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Tables\Columns\IconColumn;

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
                //->columnSpan(1)
                ->columns(5),


            /* guarda la relación con la nota; no se muestra */
            Hidden::make('note_id')->required(),

            TextInput::make('seguimiento')
                ->label('Seguimiento'),
            TextInput::make('financieras_reparto')
                ->label('Financieras Reparto'),
            TextInput::make('pasadas_financieras')
                ->label('Como Van Pasadas Las Financieras'),




            Section::make('Administración')
                ->collapsible(true)

                ->schema([
                    Grid::make(5)->schema([
                        TextInput::make('nro_contr_adm')
                            ->label('NRO CONTRATO')
                            ->maxLength(50)
                            ->placeholder('Ej. 01023')
                            ->disabled(fn() => request()->routeIs('filament.admin.resources.ventas.create-b')),

                        TextInput::make('nro_cliente_adm')
                            ->label('NRO CLIENTE')
                            ->maxLength(50)
                            ->placeholder('Ej. 00527')
                            ->disabled(fn() => request()->routeIs('filament.admin.resources.ventas.create-b')),

                        Select::make('mes_contr')
                            ->label('MES')
                            ->options(
                                collect(MesesEnum::cases())
                                    ->mapWithKeys(fn($m) => [$m->value => $m->label()])
                                    ->toArray()
                            )
                            ->placeholder('Selecciona…')
                            ->native(false)
                            ->searchable()
                            ->nullable(),

                        // ✅ Solo visible en "create-b"
                        DatePicker::make('fecha_venta')
                            ->label('Fecha de venta')
                            ->timezone('Europe/Madrid')
                            ->native(false)
                            ->required()
                            ->visible(
                                fn(?Venta $record) =>
                                request()->routeIs('filament.admin.resources.ventas.create-b')
                                || (filled($record?->nro_contr_adm) && str_ends_with($record->nro_contr_adm, '-B'))
                            )
                        // (opcional) si quieres que SOLO se vea y no la puedan cambiar:
                        // ->disabled()
                        ,
                    ]),
                ]),


            Section::make('Informe al repartidor')
                ->collapsed()

                ->compact()
                ->schema([

                    Select::make('repartidor_id')
                        ->label('Repartidor')
                        ->columnSpan(1)
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
                        ->columnSpan(1),
                    DatePicker::make('fecha_entrega')
                        ->label('Fecha de entrega')
                        ->required()
                        ->timezone('Europe/Madrid')
                        ->native(false)
                        ->columnSpan(1),
                    Select::make('horario_entrega')
                        ->label('Horario de entrega')
                        ->options(HorarioNotas::options())
                        ->native(false)
                        ->searchable()
                        ->required()
                        ->columnSpan(1),

                    Select::make('motivo_venta')
                        ->label('¿Por qué vendiste?')
                        ->columnSpan(1)
                        ->options([
                            'Eliminación de miedos' => 'Eliminación de miedos',
                            'Placer' => 'Placer',
                            'Me compró el cliente' => 'Me compró el cliente',
                            'Muy rebatido de objeciones' => 'Muy rebatido de objeciones',
                        ])
                        ->required()
                        ->native(false),

                    Select::make('motivo_horario')
                        ->columnSpan(2)
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
                        ->reactive()
                        ->columnSpan(2),

                    Forms\Components\Textarea::make('interes_art_detalle')
                        ->label('Otros artículos de interés')
                        ->placeholder('Detalle los artículos que despertaron interés')
                        ->rows(3)
                        ->columnSpan(1)
                        ->visible(fn(Get $get) => (bool) $get('interes_art'))
                        ->required(fn(Get $get) => (bool) $get('interes_art'))
                        ->maxLength(500),

                    Forms\Components\Textarea::make('observaciones_repartidor')
                        ->label('Observaciones')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(5),




            /* ───────── Información del cliente ────────── */
            Section::make('Información del cliente')
                ->relationship('customer')   // ← ¡clave!
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2, 'xl' => 5])->schema([
                        TextInput::make('first_names')->label('Nombres')->required(),
                        TextInput::make('last_names')->label('Apellidos')->required(),
                        TextInput::make('dni')->label('DNI'),
                        //->columnSpanFull(),
                        TextInput::make('customer.edadTelOp')
                            ->label('Edad (Tel. Op.)')
                            ->readOnly()
                            ->dehydrated(false),

                        DatePicker::make('fecha_nac')
                            ->label('Fec. nac.')
                            ->timezone('Europe/Madrid')
                            ->native(false)
                            ->maxDate(now())          // no permitir fechas futuras
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
                            ->readOnly()              // NO editable
                            ->dehydrated(false),      // no enviar al backend; el modelo la recalcula

                        TextInput::make('phone')
                            ->tel()
                            ->required()
                            ->maxLength(11)
                            ->minLength(11)
                            ->mask('999 999 999')
                            ->label('Teléfono 1 (requerido)')
                            ->validationMessages([
                                'min' => 'Debe tener exactamente 9 cifras',
                            ]),

                        TextInput::make('secondary_phone')
                            ->tel()
                            ->maxLength(11)
                            ->minLength(11)
                            ->mask('999 999 999')
                            ->label('Teléfono 2 (opcional)')
                            ->validationMessages([
                                'min' => 'Debe tener exactamente 9 cifras',
                            ]),

                        TextInput::make('third_phone')
                            ->tel()
                            ->maxLength(11)
                            ->minLength(11)
                            ->mask('999 999 999')
                            ->label('Teléfono 3 (opcional)')
                            ->validationMessages([
                                'min' => 'Debe tener exactamente 9 cifras',
                            ]),

                        TextInput::make('email')->label('Email')
                            ->email(),
                        //->columnSpanFull(),

                        Forms\Components\TextInput::make('nro_piso')
                            ->required()
                            ->maxLength(20)
                            ->label('No. y Piso'),

                        Forms\Components\TextInput::make('postal_code')
                            ->required()
                            ->maxLength(5)
                            ->minLength(5)
                            ->label('Codigo Postal'),

                        Forms\Components\TextInput::make('ciudad')
                            ->required()
                            ->maxLength(255)
                            ->label('Ayuntamiento/Localidad'),

                        Forms\Components\TextInput::make('provincia')
                            ->required()
                            ->maxLength(255)
                            ->label('Provincia'),

                        TextInput::make('primary_address')
                            ->required()
                            ->label('Dirección 1')
                            ->columnSpan(2),
                        TextInput::make('secondary_address')->label('Dirección 2')->columnSpan(2),
                        /*
                        TextInput::make('ayuntamiento') SE HA JUNTADO CON AYUNTAMIENTO/LOCALIDAD: columna: ciudad OJO
                            ->label('Ayuntamiento')
                            ->maxLength(255),*/

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

                        Select::make('num_hab_casa')->label(' # Personas en Casa')
                            ->options(fn() => collect(range(1, 10))
                                ->mapWithKeys(fn($n) => [$n => $n])
                                ->all())
                            ->default(1)
                            ->required()
                            ->reactive(),

                        /* ---------- IBAN con formato ---------- */
                        TextInput::make('iban')
                            ->columnSpan(2)
                            ->label('IBAN')
                            // Visual: mayúsculas
                            ->extraInputAttributes(['style' => 'text-transform: uppercase;'])
                            // Mostrar con espacios al cargar
                            ->formatStateUsing(
                                fn($state) => $state
                                ? trim(chunk_split(strtoupper(preg_replace('/\s+/', '', $state)), 4, ' '))
                                : null
                            )
                            // Guardar sin espacios y en mayúsculas
                            ->dehydrateStateUsing(
                                fn($state) => $state
                                ? strtoupper(preg_replace('/\s+/', '', $state))
                                : null
                            )
                            // 24 reales + 5 espacios = 29 visibles
                            ->maxLength(29)
                            // No reescribir mientras tecleas; solo al salir
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (\Filament\Forms\Set $set, ?string $state) {
                                $raw = strtoupper(preg_replace('/\s+/', '', $state ?? ''));
                                $raw = substr($raw, 0, 24);                       // fuerza máximo 24 (sin espacios)
                                $set('iban', implode(' ', str_split($raw, 4)));   // agrupa 4 en 4
                            })
                            // Validación: exactamente 24 alfanuméricos (sin espacios)
                            ->rule(function () {
                                return function (string $attribute, $value, $fail) {
                                    $raw = strtoupper(preg_replace('/\s+/', '', (string) $value));
                                    if (strlen($raw) !== 24) {
                                        $fail('El IBAN debe tener exactamente 24 caracteres (sin contar espacios).');
                                    }
                                    if (!preg_match('/^[A-Z0-9]{24}$/', $raw)) {
                                        $fail('El IBAN solo puede contener letras y números.');
                                    }
                                };
                            })
                            ->helperText('Se agrupa automáticamente: XXXX XXXX XXXX XXXX XXXX XXXX'),


                        Select::make('ingresos_rango')
                            ->label('Ingresos netos mensuales')
                            ->options(\App\Enums\IngresosRango::options())
                            ->required()
                            ->native(false),
                    ]),
                ])
                ->columns(5),

            /////// SECCION MOSTRAR INGRESOS  VIVIENDA Y S LABORAL EN PDF
            Section::make('Mostrar Datos en pdf')
                ->schema([
                    Toggle::make('mostrar_ingresos')
                        ->label('Mostrar ingresos en contrato PDF')
                        ->default(fn(?Venta $record) => (bool) ($record->mostrar_ingresos ?? true)),

                    Toggle::make('mostrar_tipo_vivienda')
                        ->label('Mostrar Tipo de Vivienda')
                        ->default(fn(?Venta $record) => (bool) ($record->mostrar_tipo_vivienda ?? true)),

                    Toggle::make('mostrar_situacion_lab')
                        ->label('Mostrar Situación Laboral')
                        ->default(fn(?Venta $record) => (bool) ($record->mostrar_situacion_lab ?? true)),
                ])
                ->collapsed()
                ->columns(3),


            Grid::make(2) // 1. Creamos una rejilla de 2 columnas
                ->schema([


                    /* ------------- Comercial asociado a la nota/venta -------------- */
                    Section::make('Comercial')
                        ->columnSpan(1)
                        ->schema([
                            Select::make('comercial_id')
                                ->label('Comercial')
                                ->searchable()
                                ->native(false)
                                ->nullable()
                                ->preload()
                                ->options(
                                    fn() =>
                                    User::role(['commercial', 'team_leader', 'sales_manager'])
                                        ->select('id', 'empleado_id', 'name', 'last_name')
                                        ->orderBy('empleado_id')
                                        ->get()
                                        ->mapWithKeys(fn($u) => [
                                            $u->id => "{$u->empleado_id} - {$u->name} {$u->last_name}",
                                        ])
                                        ->all()
                                )
                                ->getSearchResultsUsing(function (string $search) {
                                    return User::role(['commercial', 'team_leader', 'sales_manager'])
                                        ->where(function ($q) use ($search) {
                                            $q->where('empleado_id', 'like', "%{$search}%")
                                                ->orWhere('name', 'like', "%{$search}%")
                                                ->orWhere('last_name', 'like', "%{$search}%");
                                        })
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn($u) => [
                                            $u->id => "{$u->empleado_id} - {$u->name} {$u->last_name}",
                                        ])
                                        ->all();
                                })
                                ->dehydrateStateUsing(fn($state) => blank($state) ? null : $state),
                        ]),




                    /* ------------- Compañero -------------- */
                    Section::make('¿Estás en pareja con otro compañero?')
                        ->columnSpan(1)
                        ->schema([
                            Select::make('companion_id')
                                ->label('Compañero')
                                ->searchable()
                                ->native(false)
                                ->nullable()
                                ->default(null)
                                ->options(
                                    fn() => ['' => 'SIN COMPAÑERO']      // primera opción
                                    + User::role(['commercial', 'team_leader', 'sales_manager'])
                                        ->whereKeyNot(auth()->id())     // excluir al propio usuario
                                        ->select('id', 'empleado_id', 'name', 'last_name')
                                        ->orderBy('name')
                                        ->distinct()
                                        ->get()
                                        ->mapWithKeys(fn($u) => [
                                            $u->id => "{$u->empleado_id} - {$u->name} {$u->last_name}",
                                        ])
                                        ->all()
                                )
                                ->dehydrateStateUsing(fn($state) => blank($state) ? null : $state),
                        ]),

                ]),

            ////// SECCION DATOS DE LA VENTA/////
            Section::make('Datos de la venta')
                ->schema([
                    TextInput::make('importe_total')
                        ->label('Importe total (€)')
                        ->numeric()
                        ->prefix('€')
                        ->disabled()
                        ->dehydrated()
                        ->reactive()
                        ->afterStateHydrated(function (Get $get, Set $set) {
                            if (request()->routeIs('filament.admin.resources.ventas.create-b')) {
                                // en -B arrancamos en cero limpio
                                $set('importe_total', 0);
                                $set('monto_extra', 0);
                                $set('entrada', 0);
                                $set('total_final', 0);
                                $set('cuota_final', 0);
                                return;
                            }

                            self::recalcTotales($get, $set);
                        })
                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            $set('cuota_mensual', number_format(
                                (float) $state / max((int) ($get('num_cuotas') ?? 1), 1),
                                2,
                                '.',
                                ''
                            ));
                            self::recalcTotales($get, $set);
                        }),

                    TextInput::make('cuota_mensual')
                        ->label('Cuota mensual (€)')
                        ->numeric()
                        ->prefix('€')
                        ->disabled()
                        ->dehydrated()
                        ->reactive(),

                    Forms\Components\TextInput::make('monto_extra')
                        ->label('Monto extra')
                        ->numeric()
                        ->prefix('€')
                        ->default(0)
                        ->reactive()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => self::recalcTotales($get, $set)),

                    Forms\Components\TextInput::make('entrada')
                        ->label('Entrada')
                        ->numeric()
                        ->prefix('€')
                        ->default(0)
                        ->minValue(0)
                        ->maxValue(fn(Get $get) => (float) ($get('importe_total') ?? 0) + (float) ($get('monto_extra') ?? 0))
                        ->reactive()
                        ->live(onBlur: true)
                        ->helperText('Importe entregado como entrada. Se resta del total.')
                        ->afterStateUpdated(fn(Get $get, Set $set) => self::recalcTotales($get, $set)),



                    Forms\Components\TextInput::make('total_final')
                        ->label('Total final')
                        ->numeric()
                        ->prefix('€')
                        ->readOnly()
                        ->afterStateHydrated(fn(Get $get, Set $set) => self::recalcTotales($get, $set)),

                    Forms\Components\TextInput::make('cuota_final')
                        ->label('Cuota final')
                        ->numeric()
                        ->prefix('€')
                        ->readOnly(),

                    Select::make('modalidad_pago')
                        ->label('Modalidad de pago')
                        ->options([
                            'Contado' => 'Contado',
                            'Financiado' => 'Financiado',
                            'NS' => 'NG',
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

                    Select::make('num_cuotas')
                        ->label('Nº de cuotas')
                        ->options(
                            collect(range(1, 39))
                                ->mapWithKeys(fn($num) => [$num => $num])
                                ->toArray()
                        )
                        ->required()
                        ->reactive()
                        ->native(false)
                        ->disabled(fn(Get $get) => in_array($get('modalidad_pago'), ['Contado', 'NS'], true))
                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            $set('cuota_mensual', number_format(
                                (float) ($get('importe_total') ?? 0) / max((int) $state, 1),
                                2,
                                '.',
                                ''
                            ));
                            self::recalcTotales($get, $set);   // ← recalcula cuota_final con el nuevo total
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


                    TextInput::make('accesorio_entregado')->label('¿Has entregado algún accesorio?'),

                    Toggle::make('crema')
                        ->label('¿Incluye crema?')
                        ->default(false),
                ])
                ->columns(5),




            /* ------------- Ofertas --------------- */
            Section::make('Ofertas incluidas')
                ->schema([
                    Repeater::make('ventaOfertas')
                        ->defaultItems(fn() => request()->routeIs('filament.admin.resources.ventas.create-b') ? 0 : 1)
                        ->relationship()
                        ->minItems(1)
                        ->label(false)
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
                            fn(array $state): string => blank($state['oferta_id'] ?? null)
                            ? 'Nueva oferta'
                            : (function () use ($state): string{
                                // Nombre de la oferta
                                $nombre = Oferta::query()
                                    ->whereKey($state['oferta_id'])
                                    ->value('nombre') ?? 'Oferta';

                                // Líneas de productos de esta oferta (state del repeater hijo)
                                $productos = collect($state['productos'] ?? []);

                                $tieneComercial = $productos->contains(
                                    fn($l) => ($l['vendido_por'] ?? null) === VendidoPor::Comercial->value
                                );
                                $tieneRepartidor = $productos->contains(
                                    fn($l) => ($l['vendido_por'] ?? null) === VendidoPor::Repartidor->value
                                );

                                // Decidimos la “etiqueta” de origen
                                $origen = null;
                                if ($tieneComercial && !$tieneRepartidor) {
                                    $origen = 'COMERCIAL';
                                } elseif ($tieneRepartidor && !$tieneComercial) {
                                    $origen = 'REPARTIDOR';
                                } elseif ($tieneComercial && $tieneRepartidor) {
                                    $origen = 'MIXTA';
                                }

                                return $origen ? "{$nombre} ({$origen})" : $nombre;
                            })()
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
                                //->collapsed()
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
                                                    ->options(
                                                        fn() => Producto::query()
                                                            ->where('delete', false)
                                                            ->orderBy('nombre')
                                                            ->pluck('nombre', 'id')
                                                            ->all()
                                                    )
                                                    ->getOptionLabelUsing(
                                                        fn($value) =>
                                                        Producto::find($value)?->nombre ?? 'Producto eliminado (no disponible)'
                                                    )
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
                    //RESTO: CÁMARA
                    self::docCard('precontractual', 'Precontractual', true, true),
                    self::docCard('dni_anverso', 'DNI – Anverso', false, true),
                    self::docCard('dni_reverso', 'DNI – Reverso', false, true),
                    self::docCard('documento_titularidad', 'Documento de titularidad', false, true),
                    self::docCard('nomina', 'Nómina', false, true),
                    self::docCard('pension', 'Pensión', false, true),
                    self::docCard('contrato_firmado', 'Contrato Firmado', false, true),
                    self::docCard('otros_documentos', 'Otros Documentos', false, true),
                ])
                ->columns(1)
                ->collapsible()
                ->collapsed()
                ->columnSpanFull(),
        ]);
    }

    /* ------------------------------------------------------------------------
     | TABLA (LISTADO)
     * ---------------------------------------------------------------------*/
    public static function table(Table $table): Table
    {
        return $table

            ->headerActions([
                // 👇 DESCARGA EXCEL
                Action::make('export_mensual')
                    ->label('Descarga Excel Contr x Mes')
                    ->icon('heroicon-o-calendar')
                    ->color('success')
                    ->form([
                        Grid::make(2)->schema([
                            Select::make('mes')
                                ->label('Mes')
                                ->options([
                                    '01' => 'Enero',
                                    '02' => 'Febrero',
                                    '03' => 'Marzo',
                                    '04' => 'Abril',
                                    '05' => 'Mayo',
                                    '06' => 'Junio',
                                    '07' => 'Julio',
                                    '08' => 'Agosto',
                                    '09' => 'Septiembre',
                                    '10' => 'Octubre',
                                    '11' => 'Noviembre',
                                    '12' => 'Diciembre',
                                ])
                                ->default(now()->format('m'))
                                ->required(),
                            Select::make('anio')
                                ->label('Año')
                                ->options(function () {
                                    $years = range(now()->year, 2020);
                                    return array_combine($years, $years);
                                })
                                ->default(now()->year)
                                ->required(),
                        ]),
                    ])
                    ->modalHeading('Selecciona período')
                    ->modalSubmitActionLabel('Descargar Excel')
                    ->action(function ($data, $livewire) {
                        // 1. Obtenemos la consulta base
                        $query = Venta::query();

                        // 2. Filtramos por el rango de fechas seleccionado
                        // NOTA: Asegúrate que 'created_at' es tu campo de fecha. Si usas 'fecha_venta', cámbialo aquí.
                        $inicio = Carbon::createFromDate($data['anio'], $data['mes'], 1)->startOfDay();
                        $fin = Carbon::createFromDate($data['anio'], $data['mes'], 1)->endOfMonth()->endOfDay();

                        $query->whereBetween('fecha_venta', [$inicio, $fin]);

                        // 3. Descargamos
                        return Excel::download(
                            new VentaDirectExport($query),
                            'Ventas_' . $data['mes'] . '-' . $data['anio'] . '.xlsx'
                        );
                    }),
            ])

            ->modifyQueryUsing(function (Builder $query) {
                $query->where(function ($q) {
                    $q->whereNull('nro_contr_adm')
                        ->orWhere('nro_contr_adm', '=', '')
                        ->orWhere('nro_contr_adm', 'not like', '%-B%');
                });
            })
            ->defaultSort('created_at', 'desc')
            ->columns([ // <--- ¡IMPORTANTE! AQUI EMPIEZAN LAS COLUMNAS

                TextColumn::make('nro_contr_adm')->label('Nº Contrato')->sortable()->searchable(),
                TextColumn::make('contrato_b')
                    ->label('-B')
                    ->state(function (Venta $r) {
                        // Si agregaste el helper en el modelo:
                        $b = method_exists($r, 'contratoB') ? $r->contratoB() : $r->asociadas()->where('nro_contr_adm', 'like', '%-B')->first();
                        return $b?->nro_contr_adm ?? '—';
                    })
                    ->url(function (Venta $r) {
                        // Hacer clic para editar el -B si existe
                        $b = method_exists($r, 'contratoB') ? $r->contratoB() : $r->asociadas()->where('nro_contr_adm', 'like', '%-B')->first();
                        return $b ? self::getUrl('edit', ['record' => $b]) : null;
                    })
                    ->openUrlInNewTab(false)
                    ->tooltip('Editar contrato -B')
                    ->sortable(false)
                    ->searchable(false),

                IconColumn::make('cf')
                    ->label('CF')
                    ->alignCenter()
                    ->state(fn(Venta $record): bool => filled($record->contrato_firmado))
                    ->boolean() // ✅ muestra check verde / x roja
                    ->sortable(query: function (Builder $query, string $direction) {
                        // Ordena: primero los que tienen contrato_firmado (no null)
                        return $query->orderByRaw("contrato_firmado IS NULL {$direction}");
                    }),


                /* FUENTE DE LA TELEOPERADORA //
                 TextColumn::make('note.fuente')
            ->label('Fuente'),  */
                TextColumn::make('note.fuente')
                    ->label('Fuente')
                    ->badge()
                    // 1. COLOR A PRUEBA DE FALLOS:
                    // Mapeamos manualmente tus casos a colores que SÍ existen en Filament o Hex directos.
                    ->color(fn($state) => match ($state instanceof FuenteNotas ? $state : FuenteNotas::tryFrom($state)) {
                        FuenteNotas::CALLE => 'warning',      // Naranja (warning siempre funciona)
                        FuenteNotas::VIP_INT => 'success',    // Verde (success siempre funciona)
                        FuenteNotas::VIP_EXT => 'info',    // Amarillo (Forzado con HEX)
                        default => 'gray',
                    })
                    // 2. TEXTO BONITO:
                    ->formatStateUsing(function ($state) {
                        // Intentamos convertir a Enum para sacar el label bonito ("VIP Interno")
                        $enum = $state instanceof FuenteNotas ? $state : FuenteNotas::tryFrom($state);
                        return $enum?->getLabel() ?? $state;
                    })
                    // 3. ACCIÓN DE ROTACIÓN:
                    ->action(function ($record) {
                        $cases = FuenteNotas::cases();

                        // Obtenemos el valor actual (sea objeto o texto)
                        $val = $record->note->fuente;
                        $val = $val instanceof FuenteNotas ? $val : FuenteNotas::tryFrom($val);

                        // Buscamos índice y rotamos
                        $idx = array_search($val, $cases);
                        $nextIdx = ($idx === false) ? 0 : ($idx + 1) % count($cases);

                        // Guardamos
                        $record->note->update([
                            'fuente' => $cases[$nextIdx],
                        ]);
                    }),




                TextColumn::make('note.nro_nota')->label('Nº Nota')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('estado_venta')
                    ->badge()
                    ->color(fn(EstadoVenta $state): string => $state->color())
                    ->formatStateUsing(fn(EstadoVenta $state): string => $state->label())
                    ->sortable()
                    ->label('ESTADO/CONTR'),
                TextColumn::make('nro_cliente_adm')->label('Nº Cliente')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Nombre')->searchable(['first_names', 'last_names'])->sortable(),
                TextColumn::make('fecha_venta')->label('Fecha venta')->date('d/m/Y')->sortable(),
                TextColumn::make('hora_venta')
                    ->label('Hora')
                    ->state(fn(Venta $r) => optional($r->fecha_venta)->format('H:i'))
                    ->sortable(),
                TextColumn::make('comercial.empleado_id')
                    ->label('Comercial')
                    ->state(function (Venta $r) {
                        $u = $r->comercial;
                        return $u ? "{$u->empleado_id} - {$u->name} {$u->last_name}" : null;
                    })
                    ->formatStateUsing(fn($state) => $state ?: '--')
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('comercial', function ($q) use ($search) {
                            $q->where('empleado_id', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function ($query, string $direction) {
                        $query->leftJoin('users as com', 'com.id', '=', 'ventas.comercial_id')
                            ->orderBy('com.empleado_id', $direction)
                            ->select('ventas.*');
                    }),
                TextColumn::make('companion.empleado_id')
                    ->label('Compañero')
                    ->state(function (Venta $r) {
                        $u = $r->companion;
                        return $u ? "{$u->empleado_id} - {$u->name} {$u->last_name}" : null;
                    })
                    ->formatStateUsing(fn($state) => $state ?: '--')
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('companion', function ($q) use ($search) {
                            $q->where('empleado_id', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function ($query, string $direction) {
                        // Ordena por empleado_id del compañero
                        $query->leftJoin('users as comp', 'comp.id', '=', 'ventas.companion_id')
                            ->orderBy('comp.empleado_id', $direction)
                            ->select('ventas.*');
                    }),
                TextColumn::make('fecha_entrega')->label('F. repartidor')->date('d/m/Y'),
                TextColumn::make('horario_entrega')->label('Horario rep.'),
                TextColumn::make('customer.primary_address')
                    ->label('Dirección')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
                TextColumn::make('origen_venta')
                    ->label('Origen')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->state(fn(Venta $r) => $r->origen_venta?->value ?? '__NULL__') // ✅ ahora es string
                    ->formatStateUsing(fn($state) => match ($state) {
                        '__NULL__', '' => 'SIN ORIGEN',
                        'puerta_fria' => 'PUERTA FRÍA',
                        'venta_normal' => 'VENTA NORMAL',
                        default => strtoupper((string) $state),
                    })
                    ->color(fn($state) => match ($state) {
                        '__NULL__', '' => 'gray',
                        'puerta_fria' => 'warning',
                        'venta_normal' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('contrato_firmado_at')
                    ->label('CF At')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('Europe/Madrid')
                    ->formatStateUsing(fn($state) => $state ? Carbon::parse($state)->format('d/m/Y H:i') : '—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
            ->filters([

                Tables\Filters\TernaryFilter::make('cf')
                    ->label('CF (Contrato firmado)')
                    ->trueLabel('Con CF')
                    ->falseLabel('Sin CF')
                    ->queries(
                        true: fn(Builder $q) => $q->whereNotNull('contrato_firmado'),
                        false: fn(Builder $q) => $q->whereNull('contrato_firmado'),
                        blank: fn(Builder $q) => $q,
                    ),


                /*
                // FILTRO PARA FECHAS EN EXCEL

                Filter::make('fecha_venta')
        ->form([
            DatePicker::make('desde')->label('Desde'),
            DatePicker::make('hasta')->label('Hasta'),
        ])
        ->query(function (Builder $query, array $data): Builder {
            return $query
                ->when($data['desde'], fn ($q) => $q->whereDate('fecha_venta', '>=', $data['desde']))
                ->when($data['hasta'], fn ($q) => $q->whereDate('fecha_venta', '<=', $data['hasta']));
        }),
    */


                SelectFilter::make('origen_venta')
                    ->label('Origen')
                    ->native(false)
                    ->options([
                        '__NULL__' => 'SIN ORIGEN',
                        'puerta_fria' => 'PUERTA FRÍA',
                        'venta_normal' => 'VENTA NORMAL',
                    ])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;
                        return match (true) {
                            $value === '__NULL__' => $query->whereNull('origen_venta'),
                            blank($value) => $query,
                            default => $query->where('origen_venta', $value),
                        };
                    }),
            ])
            ->bulkActions([]);  // sin bulk delete
    }

    /* ------------------------------------------------------------------------
     | RELACIONES, PÁGINAS, PERMISOS
     * ---------------------------------------------------------------------*/
    public static function getRelations(): array
    {
        return [
            \App\Filament\Admin\Resources\VentaResource\RelationManagers\AsociadasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVentas::route('/'),
            'edit' => Pages\EditVenta::route('/{record}/edit'),
            'create-b' => Pages\CreateContratoBPage::route('/{record}/create-b'),
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

    protected static function recalcTotales(Get $get, Set $set): void
    {
        $importe = (float) ($get('importe_total') ?? 0);
        $extra = (float) ($get('monto_extra') ?? 0);
        $entrada = max(0.0, (float) ($get('entrada') ?? 0)); // nunca negativa
        $cuotas = max((int) ($get('num_cuotas') ?? 0), 1);   // evita división por cero

        // TOTAL FINAL = importe + extra - entrada (nunca negativo)
        $base = $importe + $extra;
        $totalFinal = max(0.0, round($base - $entrada, 2));

        $set('total_final', number_format($totalFinal, 2, '.', ''));
        $set('cuota_final', number_format($totalFinal / $cuotas, 2, '.', ''));
    }




    protected static function docCard(
        string $field,
        string $label,
        bool $required = false,
        bool $soloCamara = true,
    ): Group {
        return Group::make([
            Placeholder::make("{$field}_title")
                ->content(strtoupper($label))
                ->extraAttributes(['class' => 'text-xl font-extrabold'])
                ->label(""),

            Placeholder::make("{$field}_desc")
                ->content(new HtmlString(
                    "Este espacio está diseñado para que puedas actualizar y modificar el archivo de " .
                    "<strong>{$label}</strong>. Es necesario actualizarlo para mantener tus datos al día."
                ))
                ->label(""),

            Placeholder::make("{$field}_required_notice")
                ->label('')
                ->content(new HtmlString(
                    '<div class="text-red-500 text-l font-bold leading-6">
                    ❗ El documento <strong>' . e($label) . '</strong> es <strong>obligatorio</strong>.
                </div>'
                ))
                ->visible(fn(Get $get, ?Venta $record) => $required && !self::isContratoB($record) && blank($get($field))),

            FileUpload::make($field)
                ->label("")
                ->disk('public')
                ->directory('ventas')
                ->openable()
                ->downloadable()
                ->required(fn(?Venta $record) => $required && !self::isContratoB($record))
                ->validationMessages([
                    'required' => "El documento {$label} es obligatorio.",
                ])
                ->getUploadedFileNameForStorageUsing(
                    function (TemporaryUploadedFile $file) use ($field): string {
                        $user = auth()->user();

                        $timestamp = now()->format('Ymd_His');
                        $empleadoId = $user?->empleado_id ?? 'sin-id';
                        $fullName = $user
                            ? Str::slug($user->name . ' ' . $user->last_name, '_')
                            : 'sin-usuario';

                        $fieldSlug = Str::slug($field, '_');
                        $extension = $file->getClientOriginalExtension();

                        return "{$timestamp}_{$empleadoId}_{$fullName}_{$fieldSlug}.{$extension}";
                    }
                )
                ->extraAttributes(
                    $soloCamara
                    ? [
                        'class' => 'border-2 border-dashed py-16',
                        'accept' => 'image/*',
                        'capture' => 'environment',
                    ]
                    : [
                        'class' => 'border-2 border-dashed py-16',
                        'accept' => 'image/*',
                    ]
                )
                ->columnSpanFull(),
        ])->columns(1);
    }

    protected static function isContratoB(?Venta $record): bool
    {
        return filled($record?->nro_contr_adm) && str_contains($record->nro_contr_adm, '-B');
    }


}
