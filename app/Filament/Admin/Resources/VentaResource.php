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
    Group,
    Radio,
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
use App\Models\Financiera;
use Carbon\Carbon;
use App\Enums\MesesEnum;
use Illuminate\Support\HtmlString;
use App\Enums\VendidoPor;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Support\Colors\Color;
use App\Enums\OrigenVenta;
use App\Enums\FuenteNotas;
use App\Services\VentaCustomerIdentityService;
use App\Filament\Support\SuperAdminVentaCustomerId;
use App\Filament\Support\CustomerPhoneForm;
use App\Support\Filament\FechaNacimientoField;
use App\Support\Filament\VentaCustomerRelationshipSection;
use App\Support\Filament\VentaDocumentUpload;
use App\Support\Filament\VentaSoftDeleteTableAction;
use App\Support\CustomerNameSearch;
use App\Support\NroContratoAdmin;
use Filament\Tables\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use App\Exports\VentaDirectExport;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Support\Facades\Storage;

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

            Placeholder::make('nro_nota')
                ->label(fn (): string => self::isSuperAdminPanel() ? '' : 'Nº Nota')
                ->content(function (?Venta $record) {
                    if (! self::isSuperAdminPanel()) {
                        return $record?->note?->nro_nota ?? '-';
                    }

                    $record?->loadMissing(['note', 'customer']);
                    $nota = $record?->note?->nro_nota ?? '-';
                    $nroAdm = filled($record?->nro_contr_adm) ? (string) $record->nro_contr_adm : '—';
                    $nombre = trim(implode(' ', array_filter([
                        $record?->customer?->first_names,
                        $record?->customer?->last_names,
                    ])));
                    if ($nombre === '') {
                        $nombre = '—';
                    }

                    $fechaContrato = '—';
                    if ($record?->fecha_venta) {
                        $fechaContrato = \Illuminate\Support\Carbon::parse($record->fecha_venta)
                            ->timezone('Europe/Madrid')
                            ->format('d/m/Y');
                    }

                    $blue = 'color:#2563eb;font-size:20px;font-weight:700;line-height:1.25;white-space:nowrap;';
                    // Nº y fecha siempre completos: más compactos, sin truncar ni encoger
                    $red = 'color:#dc2626;font-size:16px;font-weight:700;line-height:1.25;white-space:nowrap;flex-shrink:0;';
                    $green = 'color:#16a34a;font-size:15px;font-weight:600;line-height:1.25;white-space:nowrap;flex-shrink:0;margin-left:1.25rem;';

                    $dato = function (string $label, string $valor): string {
                        return '<div style="flex:0 0 auto;min-width:max-content;text-align:right;overflow:visible;">'
                            .'<div class="text-gray-500 dark:text-gray-400" style="font-size:12px;font-weight:400;white-space:nowrap;">'.e($label).'</div>'
                            .'<div class="text-gray-700 dark:text-gray-200" style="font-size:15px;font-weight:700;white-space:nowrap;overflow:visible;text-overflow:clip;">'.e($valor).'</div>'
                            .'</div>';
                    };

                    return new HtmlString(
                        '<div style="display:flex;width:100%;max-width:100%;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:1rem;overflow:visible;">'
                        .'<div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:0.35rem;min-width:max-content;flex:1 1 auto;overflow:visible;">'
                        .'<span style="'.$blue.'">'.e($nombre).'</span>'
                        .'<span style="'.$blue.'"> - </span>'
                        .'<span style="'.$red.'">'.e($nroAdm).'</span>'
                        // Fecha del contrato en verde, al lado del nº (con espacio)
                        .'<span style="'.$green.'">'.e($fechaContrato).'</span>'
                        .'</div>'
                        .'<div style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:flex-end;gap:1.5rem;min-width:max-content;flex:0 0 auto;overflow:visible;">'
                        .$dato('Nº Nota', (string) $nota)
                        .$dato('Nro Contrato', $nroAdm)
                        .$dato('Fecha Contrato', $fechaContrato)
                        .'</div>'
                        .'</div>'
                    );
                })
                ->extraAttributes(fn (): array => self::isSuperAdminPanel()
                    ? [
                        'class' => 'w-full !max-w-none !overflow-visible',
                        'style' => 'overflow:visible;max-width:none;width:100%;',
                    ]
                    : [
                        'class' => 'text-2xl font-bold',
                    ])
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


            /* Relación con la nota (oculta). Opcional: contratos recuperados
             * y algunos históricos no tienen nota vinculada; antes se podía guardar igual. */
            Hidden::make('note_id')
                ->nullable(),

            TextInput::make('seguimiento')
                ->label('Seguimiento'),
            TextInput::make('financieras_reparto')
                ->label('Financieras Reparto'),
            TextInput::make('pasadas_financieras')
                ->label('Como Van Pasadas Las Financieras'),




            Section::make('ATAJOS DOCUMENTOS')
                ->visible(fn (): bool => self::isSuperAdminPanel())
                ->collapsible()
                ->collapsed(false)
                ->compact()
                ->schema([
                    Placeholder::make('atajos_documentos')
                        ->label('')
                        ->content(fn (?Venta $record): HtmlString => self::atajosDocumentosHtml($record))
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Administración')
                ->collapsible(true)

                ->schema([
                    Grid::make([
    'default' => 1, // 1 columna en móvil
    'sm' => 2,      // 2 en tablets
    'lg' => 5,      // 5 en PC
])->schema([
                        TextInput::make('nro_contr_adm')
                            ->label('NRO CONTRATO')
                            ->maxLength(50)
                            ->placeholder('Ej. 01023')
                            ->disabled(fn() => request()->routeIs('filament.*.resources.ventas*.create-b')),

                        TextInput::make('nro_cliente_adm')
                            ->label('NRO CLIENTE')
                            ->maxLength(50)
                            ->placeholder('Ej. 00527')
                            ->disabled(fn() => request()->routeIs('filament.*.resources.ventas*.create-b')),

                        Select::make('mes_contr')
                            ->label('MES')
                            ->options(
                                collect(MesesEnum::cases())
                                    ->mapWithKeys(fn($m) => [$m->value => $m->label()])
                                    ->toArray()
                            )
                            ->placeholder('Seleccionar')
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
                                request()->routeIs('filament.*.resources.ventas*.create-b')
                                || (filled($record?->nro_contr_adm) && str_ends_with($record->nro_contr_adm, '-B'))
                            )
                        // (opcional) si quieres que SOLO se vea y no la puedan cambiar:
                        // ->disabled()
                        ,

                        // Fecha del contrato -B asociado (editable desde el contrato padre)
                        DatePicker::make('fecha_contrato_b_virtual')
                            ->label('Fecha Contrato -B')
                            ->timezone('Europe/Madrid')
                            ->native(false)
                            ->dehydrated(false)
                            ->live()
                            ->afterStateHydrated(function ($component, ?Venta $record) {
                                if ($record) {
                                    $b = $record->contratoB();
                                    $component->state($b?->fecha_venta?->toDateString());
                                }
                            })
                            ->afterStateUpdated(function (?string $state, ?Venta $record) {
                                if ($record && $state) {
                                    $b = $record->contratoB();
                                    if (! $b) {
                                        return;
                                    }
                                    $old = $b->fecha_venta;
                                    $b->updateQuietly(['fecha_venta' => $state]);
                                    \App\Support\VentaFechaCambioTracker::recordIfChanged(
                                        $b->fresh() ?? $b,
                                        $old,
                                        $state,
                                        auth()->id(),
                                    );
                                }
                            })
                            ->visible(fn(?Venta $record) => $record !== null && !self::isContratoB($record)),
                    ]),
                ]),


            Section::make('Informe al repartidor')
                ->collapsible()
                ->collapsed(false)
                ->compact()
                ->schema([

                    Select::make('repartidor_id')
                        ->label('Repartidor')
                        ->columnSpan(1)
                        ->options(function () {
                            if (! \Spatie\Permission\Models\Role::query()->where('name', 'delivery')->exists()) {
                                return [];
                            }

                            return User::role('delivery')
                                ->select('id', 'empleado_id')
                                ->orderBy('empleado_id')
                                ->get()
                                ->mapWithKeys(fn ($user) => [$user->id => $user->empleado_id]);
                        })
                        ->searchable()
                        ->native(false)
                        ->placeholder('Seleccionar repartidor')
                        ->nullable()
                        ->preload()
                        ->columnSpan(1),
                    DatePicker::make('fecha_venta')
                        ->label('Fecha de la venta')
                        ->timezone('Europe/Madrid')
                        ->native(false)
                        ->nullable()
                        ->columnSpan(1)
                        ->visible(fn(?Venta $record) => !self::isContratoB($record)),

                    DatePicker::make('fecha_entrega')
                        ->label('Fecha de entrega')
                        ->required(fn (): bool => ! self::isSuperAdminPanel())
                        ->timezone('Europe/Madrid')
                        ->native(false)
                        ->columnSpan(1),
                    Select::make('horario_entrega')
                        ->label('Horario de entrega')
                        ->options(HorarioNotas::options())
                        ->native(false)
                        ->searchable()
                        ->required(fn (): bool => ! self::isSuperAdminPanel())
                        ->columnSpan(1),
                    Select::make('horario_entrega_2')
                        ->label('Horario de entrega 2')
                        ->options(HorarioNotas::options())
                        ->native(false)
                        ->searchable()
                        ->nullable()
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
                        ->required(fn (): bool => ! self::isSuperAdminPanel())
                        ->native(false),

                    Select::make('motivo_horario')
                        ->columnSpan(2)
                        ->label('¿Por qué pusiste ese horario?')
                        ->options([
                            '3ª personas' => '3ª personas',
                            'Se lo dije y marqué cuando firmó' => 'Se lo dije y marqué cuando firmó',
                            'No va a estar a otra hora en casa' => 'No va a estar a otra hora en casa',
                        ])
                        ->required(fn (): bool => ! self::isSuperAdminPanel())
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
                        ->required(fn(Get $get) => (bool) $get('interes_art') && ! self::isSuperAdminPanel())
                        ->maxLength(500),

                    Forms\Components\Textarea::make('observaciones_repartidor')
                        ->label('Observaciones')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(5),




            /* ───────── Información del cliente ────────── */
            ...SuperAdminVentaCustomerId::formFields(),

            Placeholder::make('customer_shared_warning')
                ->label('')
                ->columnSpanFull()
                ->content(new HtmlString(
                    '<p class="text-sm text-warning-600 dark:text-warning-400">'
                    . 'Este ID de cliente lo comparten otros contratos o notas. '
                    . 'Nombre y DNI están <strong>fijos</strong> aquí: use el botón amarillo <strong>Cambio/nombre</strong> '
                    . 'para reasignar <em>solo este contrato</em> a otro cliente (existente o nuevo). '
                    . 'Otros datos (teléfonos, dirección, etc.) sí se actualizan en la misma ficha.'
                    . '</p>'
                ))
                ->visible(fn (?Venta $record) => self::isSuperAdminPanel()
                    && filled($record?->customer_id)
                    && VentaCustomerIdentityService::customerIsShared($record)),

            Section::make('Información del cliente')
                ->relationship('customer')   // ← ¡clave!
                ->mutateRelationshipDataBeforeFillUsing(
                    fn (array $data): array => VentaCustomerRelationshipSection::mutateDataBeforeFill($data),
                )
                ->mutateRelationshipDataBeforeSaveUsing(
                    fn (array $data): array => VentaCustomerRelationshipSection::mutateDataBeforeSave($data),
                )
                ->schema([
                    Grid::make([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 5])->schema([
                        Grid::make(['default' => 1, 'md' => 3])
                            ->schema([
                                TextInput::make('first_names')
                                    ->label('Nombres')
                                    ->disabled(fn (): bool => self::isSuperAdminPanel())
                                    ->dehydrated(fn (): bool => ! self::isSuperAdminPanel()),
                                TextInput::make('last_names')
                                    ->label('Apellidos')
                                    ->disabled(fn (): bool => self::isSuperAdminPanel())
                                    ->dehydrated(fn (): bool => ! self::isSuperAdminPanel()),
                                TextInput::make('dni')
                                    ->label('DNI')
                                    ->disabled(fn (): bool => self::isSuperAdminPanel())
                                    ->dehydrated(fn (): bool => ! self::isSuperAdminPanel()),
                            ])
                            ->columnSpanFull(),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('abrirCambioNombre')
                                ->label('Cambio/nombre')
                                ->icon('heroicon-o-arrows-right-left')
                                ->color('warning')
                                ->visible(fn (): bool => self::isSuperAdminPanel())
                                ->action(function ($livewire): void {
                                    $livewire->mountAction('cambioNombre');
                                }),
                        ])
                            ->visible(fn (): bool => self::isSuperAdminPanel())
                            ->columnSpanFull(),
                        TextInput::make('customer.edadTelOp')
                            ->label('Edad (Tel. Op.)')
                            ->readOnly()
                            ->dehydrated(false),

                        FechaNacimientoField::make(),

                        TextInput::make('age')
                            ->numeric()
                            ->label('Edad')
                            ->readOnly()              // NO editable
                            ->dehydrated(false),      // no enviar al backend; el modelo la recalcula

                        CustomerPhoneForm::make('phone', 'Teléfono 1 (opcional)')
                            ->columnSpan(1),

                        CustomerPhoneForm::make('secondary_phone', 'Teléfono 2 (opcional)'),

                        CustomerPhoneForm::make('third_phone', 'Teléfono 3 (opcional)'),

                        TextInput::make('phone1_commercial')
                            ->tel()
                            ->maxLength(11)
                            ->minLength(11)
                            ->mask('999 999 999')
                            ->label('Teléfono comercial 1')
                            ->validationMessages([
                                'min' => 'Debe tener exactamente 9 cifras',
                            ]),

                        TextInput::make('phone2_commercial')
                            ->tel()
                            ->maxLength(11)
                            ->minLength(11)
                            ->mask('999 999 999')
                            ->label('Teléfono comercial 2')
                            ->validationMessages([
                                'min' => 'Debe tener exactamente 9 cifras',
                            ]),

                        TextInput::make('email')->label('Correo electrónico')
                            ->email(),


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
                            ->columnSpan([
                    'default' => 1,
                    'lg' => 3,
                ]),
                        TextInput::make('secondary_address')
                        ->label('Dirección 2')
                        ->columnSpan([
                    'default' => 1,
                    'lg' => 3,
                ]),
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
                            ->native(false)
                            ->live(),

                        Select::make('antiguedad')
                            ->label('Antigüedad')
                            ->options([
                                '1' => '1 año',
                                '2' => '2 años',
                                '3' => '3 años',
                                '4' => '4 años',
                                '5' => '5 años',
                                '6' => '6 años',
                                '7' => '7 años',
                                '8+' => 'Más de 8 años',
                            ])
                            ->required()
                            ->visible(fn(Get $get) => in_array($get('situacion_laboral'), ['empleado', 'autonomo'])),

                        TextInput::make('nombre_empresa')
                            ->label('NOMBRE DE LA EMPRESA')
                            ->visible(fn(Get $get) => in_array($get('situacion_laboral'), ['empleado', 'autonomo'])),

                        TextInput::make('oficio')
                            ->label('OFICIO')
                            ->required()
                            ->visible(fn(Get $get) => in_array($get('situacion_laboral'), ['empleado', 'autonomo'])),

                        Select::make('num_hab_casa')->label(' # Personas en Casa')
                            ->options(fn() => collect(range(1, 10))
                                ->mapWithKeys(fn($n) => [$n => $n])
                                ->all())
                            ->default(1)
                            ->required()
                            ->reactive(),


                        Select::make('ingresos_rango')
                            ->label('Ingresos netos mensuales')
                            ->options(\App\Enums\IngresosRango::options())
                            ->required()
                            ->native(false),
                    ]),
                ]),
                //->columns(1),

            self::gestionRepartoSection(),

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
                            if (request()->routeIs('filament.*.resources.ventas*.create-b')) {
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
                            'Giro '
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
                            collect(range(1, 40))
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
                        ->options(fn (?Venta $record): array => Financiera::opcionesSelect(
                            is_string($record?->financiera) ? $record->financiera : null
                        ))
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

                    /* ---------- IBAN con formato ---------- */
                    TextInput::make('iban')
                        ->columnSpanFull()
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
                            $raw = substr($raw, 0, 24);
                            $set('iban', implode(' ', str_split($raw, 4)));
                        })
                        // Validación solo si se rellena: exactamente 24 alfanuméricos (sin espacios)
                        ->rule(function () {
                            return function (string $attribute, $value, $fail) {
                                $raw = strtoupper(preg_replace('/\s+/', '', (string) $value));
                                if ($raw === '') {
                                    return;
                                }
                                if (strlen($raw) !== 24) {
                                    $fail('El IBAN debe tener exactamente 24 caracteres (sin contar espacios).');
                                }
                                if (!preg_match('/^[A-Z0-9]{24}$/', $raw)) {
                                    $fail('El IBAN solo puede contener letras y números.');
                                }
                            };
                        })
                        ->helperText('Se agrupa automáticamente: XXXX XXXX XXXX XXXX XXXX XXXX'),
                ])
                ->columns(5),




            /* ------------- Ofertas --------------- */
            Section::make('Ofertas incluidas')
                ->schema([
                    Repeater::make('ventaOfertas')
                        ->defaultItems(fn() => request()->routeIs('filament.*.resources.ventas*.create-b') ? 0 : 1)
                        ->relationship()
                        ->minItems(1)
                        ->label(false)
                        ->createItemButtonLabel('Agregar Oferta')
                        ->afterStateUpdated(function (Get $get, Set $set): void {
                            $set(
                                'importe_total',
                                collect($get('ventaOfertas'))->sum(
                                    fn($o) =>
                                    Oferta::find($o['oferta_id'] ?? 0)?->precio_base ?? 0
                                )
                            );
                            self::syncTotalVentasComercial($get, $set);
                        })
                        ->truncateItemLabel(false)
                        ->itemLabel(function (array $state): HtmlString {
                            if (blank($state['oferta_id'] ?? null)) {
                                $texto = 'NUEVA OFERTA';
                            } else {
                                $nombre = Oferta::query()
                                    ->whereKey($state['oferta_id'])
                                    ->value('nombre') ?? 'Oferta';

                                $productos = collect($state['productos'] ?? []);

                                $tieneComercial = $productos->contains(
                                    fn ($l) => ($l['vendido_por'] ?? null) === VendidoPor::Comercial->value
                                );
                                $tieneRepartidor = $productos->contains(
                                    fn ($l) => ($l['vendido_por'] ?? null) === VendidoPor::Repartidor->value
                                );

                                $origen = null;
                                if ($tieneComercial && ! $tieneRepartidor) {
                                    $origen = 'COMERCIAL';
                                } elseif ($tieneRepartidor && ! $tieneComercial) {
                                    $origen = 'REPARTIDOR';
                                } elseif ($tieneComercial && $tieneRepartidor) {
                                    $origen = 'MIXTA';
                                }

                                $texto = mb_strtoupper($origen ? "{$nombre} ({$origen})" : $nombre, 'UTF-8');
                            }

                            return new HtmlString(
                                '<span style="display:inline-flex;align-items:center;max-width:100%;border-radius:999px;padding:0.28rem 0.8rem;background:#16a34a;color:#fff;font-size:13px;font-weight:800;letter-spacing:0.03em;line-height:1.35;white-space:normal;">'
                                .e($texto)
                                .'</span>'
                            );
                        })
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
                                        self::syncTotalVentasComercial($get, $set, '../../');
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
                                                    ->default(0)
                                                    ->dehydrated()
                                                    ->reactive()
                                                    ->readOnly(
                                                        fn(Get $get) =>
                                                        Producto::query()
                                                            ->whereKey($get('producto_id'))
                                                            ->value('nombre') !== 'Producto Externo'
                                                    )
                                                    ->afterStateHydrated(function (TextInput $component, Get $get, mixed $state): void {
                                                        // Líneas antiguas pueden venir sin puntos_linea; tomar del producto.
                                                        if ($state !== null && $state !== '') {
                                                            return;
                                                        }

                                                        $producto = Producto::query()->find($get('producto_id'));
                                                        if (! $producto || $producto->nombre === 'Producto Externo') {
                                                            $component->state(0);

                                                            return;
                                                        }

                                                        $cantidad = max((int) ($get('cantidad') ?? 1), 1);
                                                        $component->state($cantidad * (int) ($producto->puntos ?? 0));
                                                    })
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
                ->visible(function (Get $get) {
                    $ids = collect($get('ventaOfertas') ?? [])
                        ->flatMap(fn($oferta) => $oferta['productos'] ?? [])
                        ->pluck('producto_id')
                        ->filter()
                        ->all();

                    if (empty($ids))
                        return false;

                    return Producto::query()
                        ->whereIn('id', $ids)
                        ->where('nombre', 'Producto Externo')
                        ->exists();
                })
                ->schema([
                    Forms\Components\Repeater::make('productos_externos')
                        ->label(false)
                        ->simple(
                            TextInput::make('value')
                                ->label(function ($state, $component) {
                                    // índice humano 1..n
                                    $index = method_exists($component, 'getIndex') ? $component->getIndex() : null;
                                    return 'Nombre producto externo #' . (($index ?? 0) + 1);
                                })
                                ->required()
                                ->live(onBlur: true) // ✅ evita pérdidas mientras escribes
                        )
                        ->dehydrated()
                        ->minItems(1),
                ])
                ->columns(1)
                ->collapsible(),


            /* ────────── Datos de la venta ────────── */


            Section::make('Gestión Documentos')
                ->extraAttributes(['class' => 'gestion-docs'])
                ->schema([
                    Placeholder::make('gestion_docs_styles')
                        ->hiddenLabel()
                        ->content(new HtmlString(self::gestionDocsStyles()))
                        ->extraAttributes(['style' => 'display:none'])
                        ->columnSpanFull(),

                    //RESTO: CÁMARA
                    self::docCard('precontractual', 'Precontractual', ! self::isSuperAdminPanel(), true),
                    self::docCard('foto_sorteo', 'Foto Sorteo', false, true),
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
                // Si falta precontractual, abrir la sección para que se vea el error al guardar
                ->collapsed(fn (?Venta $record): bool => filled($record?->precontractual) || self::isContratoB($record))
                ->columnSpanFull(),
        ]);
    }

    /* ------------------------------------------------------------------------
     | TABLA (LISTADO)
     * ---------------------------------------------------------------------*/
    public static function table(Table $table): Table
    {
        return $table


            ->modifyQueryUsing(function (Builder $query, $livewire = null): void {
                $nroSearch = is_object($livewire)
                    ? NroContratoAdmin::resolveNroSearchTermFromLivewire($livewire)
                    : null;

                NroContratoAdmin::constrainAdminGerenteList($query, $nroSearch);
            })
            ->defaultSort('id', 'desc')
            ->columns([ // <--- ¡IMPORTANTE! AQUI EMPIEZAN LAS COLUMNAS

                TextColumn::make('nro_contr_adm')
                    ->label('Nº Contrato')
                    ->badge()
                    // Recuperados → amarillo (warning); resto → verde.
                    ->color(fn ($state): string => \App\Models\ContratoRecuperado::isRecuperado(
                        filled($state) ? (string) $state : null
                    ) ? 'warning' : 'success')
                    ->formatStateUsing(fn ($state): string => filled($state) ? (string) $state : '—')
                    ->wrap(false)
                    ->sortable()
                    ->searchable(
                        query: function (Builder $query, string $search): Builder {
                            $values = NroContratoAdmin::searchValues($search);
                            if ($values === []) {
                                return $query->whereRaw('1 = 0');
                            }

                            // Coincidencia exacta (1 ≠ 10/1001; sí admite 001 ↔ 1 y variantes -B).
                            return $query->whereIn('nro_contr_adm', $values);
                        },
                        isIndividual: true,
                    )
                    ->extraHeaderAttributes(['class' => 'min-w-[7rem]'])
                    ->extraCellAttributes(['class' => 'min-w-[7rem] whitespace-nowrap']),

                // Igual que SuperAdmin: Nombre justo después de Nº Contrato.
                TextColumn::make('customer.name')
                    ->label('Nombre')
                    ->extraAttributes(['class' => 'font-bold'])
                    ->searchable(
                        query: fn (Builder $query, string $search): Builder => CustomerNameSearch::constrainHasCustomer($query, $search),
                        isIndividual: true,
                    )
                    ->sortable(),

                TextColumn::make('ver_cont')
                    ->label('Ver/Cont')
                    ->state('Ver/cont')
                    ->badge()
                    ->color('info')
                    ->url(fn (Venta $record): string => static::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab()
                    ->tooltip('Abrir formulario de este contrato')
                    ->alignCenter()
                    ->grow(false)
                    ->toggleable(),

                TextColumn::make('pgc')
                    ->label('PGC')
                    ->state(fn (Venta $record): string => filled($record->customer_id) ? 'Ir_PGC' : '—')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Ir_PGC' ? 'info' : 'gray')
                    ->url(function (Venta $record): ?string {
                        if (! filled($record->customer_id)) {
                            return null;
                        }

                        return CustomerResource::getUrl('view', [
                            'record' => $record->customer_id,
                        ], panel: 'admin');
                    })
                    ->openUrlInNewTab()
                    ->tooltip('Posición Global de este cliente')
                    ->alignCenter()
                    ->grow(false)
                    ->toggleable(),

                TextColumn::make('fecha_venta')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('contrato_b')
                    ->label('-B')
                    ->badge()
                    ->state(function (Venta $r) {
                        // Si agregaste el helper en el modelo:
                        $b = method_exists($r, 'contratoB') ? $r->contratoB() : $r->asociadas()->where('nro_contr_adm', 'like', '%-B')->first();
                        return $b?->nro_contr_adm ?? '—';
                    })
                    ->url(function (Venta $r) {
                        $b = method_exists($r, 'contratoB') ? $r->contratoB() : $r->asociadas()->where('nro_contr_adm', 'like', '%-B')->first();
                        if (!$b) return null;
                        // Usar la VentaResource del panel activo para generar la URL correcta
                        $panel = \Filament\Facades\Filament::getCurrentPanel();
                        if ($panel) {
                            foreach ($panel->getResources() as $resource) {
                                if ($resource::getModel() === \App\Models\Venta::class
                                    && array_key_exists('edit', $resource::getPages())) {
                                    return $resource::getUrl('edit', ['record' => $b]);
                                }
                            }
                        }
                        return self::getUrl('edit', ['record' => $b]);
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

                // FUENTE DE LA TELEOPERADORA //

                TextColumn::make('fuente_display')
                    ->label('Fuente')
                    ->badge()
                    ->state(fn (Venta $record) => \App\Support\VentaOrigenResolver::fuenteDisplayForVenta($record))
                    ->color(fn ($state) => match ($state instanceof FuenteNotas ? $state : FuenteNotas::tryFrom($state)) {
                        FuenteNotas::CALLE => 'warning',
                        FuenteNotas::VIP_INT => 'success',
                        FuenteNotas::VIP_EXT => 'info',
                        FuenteNotas::PTA_FRIA, FuenteNotas::AG_N_ADMIN, FuenteNotas::PF_ADMIN => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(function ($state) {
                        $enum = $state instanceof FuenteNotas ? $state : FuenteNotas::tryFrom($state);

                        return $enum?->getLabel() ?? $state;
                    }),

                TextColumn::make('note.nro_nota')->label('Nº Nota')->badge()->color(Color::Pink)->sortable()->searchable(isIndividual: true),
                Tables\Columns\TextColumn::make('estado_venta')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->color(fn(EstadoVenta $state): string => $state->color())
                    ->formatStateUsing(fn(EstadoVenta $state): string => $state->label())
                    ->sortable()
                    ->label('ESTADO/CONTR'),
                TextColumn::make('nro_cliente_adm')
                    ->label('Nº Cliente')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->searchable(isIndividual: true)
                    ->sortable(),
                TextColumn::make('customer.postal_code')
                    ->label('CP')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('info')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('telefonos_cl')
                    ->label('Teléfonos_CL')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->state(function (Venta $record): string {
                        $customer = $record->customer;
                        if (!$customer) return '-';

                        $fmt = fn(?string $p): string => $p
                            ? implode('&nbsp;', str_split(preg_replace('/\D/', '', $p), 3))
                            : '';

                        $phones = collect([
                            $customer->phone,
                            $customer->secondary_phone,
                            $customer->third_phone,
                            $customer->phone1_commercial,
                            $customer->phone2_commercial,
                        ])->filter()->map($fmt)->join('<br>');

                        return $phones
                            ? "<span class=\"font-bold text-amber-500\">{$phones}</span>"
                            : '-';
                    })
                    ->html()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function ($q) use ($search) {
                            $q->where('phone', 'like', "%{$search}%")
                              ->orWhere('secondary_phone', 'like', "%{$search}%")
                              ->orWhere('third_phone', 'like', "%{$search}%")
                              ->orWhere('phone1_commercial', 'like', "%{$search}%")
                              ->orWhere('phone2_commercial', 'like', "%{$search}%");
                        });
                    })
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tlf_comerciales')
                    ->label('Tlf_Com')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->state(function (Venta $record): string {
                        $customer = $record->customer;
                        if (!$customer) return '-';

                        $fmt = fn(?string $p): string => $p
                            ? implode('&nbsp;', str_split(preg_replace('/\D/', '', $p), 3))
                            : '';

                        $phones = collect([
                            $customer->phone1_commercial,
                            $customer->phone2_commercial,
                        ])->filter()->map($fmt)->join('<br>');

                        return $phones
                            ? "<span class=\"font-bold text-amber-500\">{$phones}</span>"
                            : '-';
                    })
                    ->html()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function ($q) use ($search) {
                            $q->where('phone1_commercial', 'like', "%{$search}%")
                              ->orWhere('phone2_commercial', 'like', "%{$search}%");
                        });
                    })
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hora_venta')
                    ->label('Hora')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->state(fn (Venta $r) => \App\Support\VentaFechaVenta::horaDisplay($r))
                    ->sortable(),
                TextColumn::make('comercial.empleado_id')
                    ->label('Comercial')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->state(function (Venta $r) {
                        $u = $r->comercial;
                        return $u ? "{$u->empleado_id} - {$u->name} {$u->last_name}" : null;
                    })
                    ->formatStateUsing(fn($state) => strtoupper($state ?: '--'))
                    ->badge()
                    ->color('success')
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
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->state(function (Venta $r) {
                        $u = $r->companion;
                        return $u ? "{$u->empleado_id} - {$u->name} {$u->last_name}" : null;
                    })
                    ->formatStateUsing(fn($state) => $state ?: '--')
                    ->badge()
                    ->color(fn($state) => $state && $state !== '--' ? 'success' : 'gray')
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('companion', function ($q) use ($search) {
                            $q->where('empleado_id', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function ($query, string $direction) {
                        $query->leftJoin('users as comp', 'comp.id', '=', 'ventas.companion_id')
                            ->orderBy('comp.empleado_id', $direction)
                            ->select('ventas.*');
                    }),
                TextColumn::make('fecha_entrega')
                    ->label('F. repartidor')
                    ->badge()
                    ->date('d/m/Y'),
                TextColumn::make('horario_entrega')->label('Horario rep.'),
              /*  TextColumn::make('customer.primary_address')
                    ->label('Dirección')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),

              */
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

                // Solo en el recurso Contratos de Admin: acceso rápido al PDF sin
                // tener que entrar a editar el contrato.
                TextColumn::make('ver_pdf')
                    ->label('Ver_pdf')
                    ->state('VerContrato')
                    ->badge()
                    ->color('info')
                    ->url(fn(Venta $record) => str_ends_with((string) $record->nro_contr_adm, '-B')
                        ? route('ventas.preview-b', $record)
                        : route('ventas.preview', $record))
                    ->openUrlInNewTab()
                    ->sortable(false)
                    ->searchable(false)
                    ->tooltip('Ver el último PDF de este contrato'),

            ])
            ->headerActions([
                // 👇 EXPORTAR EXCEL POR MES
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
                    ->action(function ($data) {
                        $query = Venta::query();
                        $inicio = Carbon::createFromDate($data['anio'], $data['mes'], 1)->startOfDay();
                        $fin = Carbon::createFromDate($data['anio'], $data['mes'], 1)->endOfMonth()->endOfDay();
                        $query->whereBetween('fecha_venta', [$inicio, $fin]);
                        return Excel::download(
                            new VentaDirectExport($query),
                            'Ventas_' . $data['mes'] . '-' . $data['anio'] . '.xlsx'
                        );
                    }),

                // 👇 IMPORTAR EXCEL
                Action::make('import_excel')
                    ->label('Importar Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('Archivo Excel')
                            ->required()
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                            ])
                            ->storeFiles(false),
                    ])
                    ->action(function ($data) {
                        /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null $file */
                        $file = $data['file'] ?? null;

                        if (!$file) {
                            \Filament\Notifications\Notification::make()
                                ->title('No se recibió ningún archivo')
                                ->danger()
                                ->send();

                            return;
                        }

                        // Disco privado: el Excel lleva datos de clientes y nunca se
                        // sirve por URL, sólo se lee aquí para procesarlo. Además así
                        // ->path() sigue siendo válido aunque los documentos de venta
                        // se muevan a un almacenamiento remoto.
                        $disk = 'local';
                        $folder = 'ventas_excel';

                        // Nombre único del archivo
                        $extension = $file->getClientOriginalExtension() ?: 'xlsx';
                        $fileName = 'ventas_import_' . now()->format('Ymd_His') . '_' . Str::random(6) . '.' . $extension;

                        // storeAs() crea la carpeta si no existe
                        $storedPath = $file->storeAs($folder, $fileName, $disk);

                        // Ruta física completa para leerlo con maatwebsite/excel
                        $absolutePath = Storage::disk($disk)->path($storedPath);

                        $rows = Excel::toArray(new \stdClass(), $absolutePath)[0] ?? [];

                        foreach ($rows as $index => $row) {
                            if ($index === 0) {
                                continue; // encabezado
                            }

                            if (blank(array_filter($row, fn($value) => filled($value)))) {
                                continue; // fila vacía
                            }

                            app(\App\Services\ImportVentaExcelService::class)->procesarFila(
                                row: $row,
                                origenVenta: OrigenVenta::EXCEL,
                                archivoImportado: $storedPath,
                            );
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Excel importado correctamente')
                            ->body("Archivo guardado en: {$storedPath}")
                            ->success()
                            ->send();
                    }),

                // 👇 BORRAR SELECCIÓN DE FILAS
                Action::make('borrar_seleccion')
                    ->label('Borrar la selección')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->disabled(fn ($livewire): bool => blank($livewire->selectedTableRecords))
                    ->action(function ($livewire): void {
                        $livewire->deselectAllTableRecords();
                    }),

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // Solo Abby (admin) puede archivar desde este listado; el resto de admins no ve la papelera.
                // La recuperación se hace en SuperAdmin → Contratos borrados.
                VentaSoftDeleteTableAction::makeForAbbyOnly(),
            ])
            ->filters([

                Filter::make('filtro_producto')
                    ->form([
                        Select::make('producto_id')
                            ->label('Filtro por producto')
                            ->placeholder('Buscar un producto')
                            ->searchable()
                            ->native(false)
                            ->options(fn (): array => self::productoFilterOptions()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $productoId = $data['producto_id'] ?? null;

                        if (blank($productoId)) {
                            return $query;
                        }

                        return $query
                            ->whereHas(
                                'ventaOfertas.productos',
                                fn (Builder $q) => $q->where('producto_id', $productoId)
                            )
                            ->reorder()
                            ->orderBy('id', 'desc');
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (blank($data['producto_id'] ?? null)) {
                            return null;
                        }

                        $nombre = Producto::query()
                            ->whereKey($data['producto_id'])
                            ->value('nombre');

                        return $nombre ? "Producto: {$nombre}" : null;
                    }),

                Filter::make('cp_contrato')
                    ->form([
                        TextInput::make('cp')
                            ->label('Contr-CP')
                            ->numeric()
                            ->minLength(5)
                            ->maxLength(5)
                            ->placeholder('Ej: 28001'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            filled($data['cp'] ?? null) && strlen((string) $data['cp']) === 5,
                            fn($q) => $q->whereHas('customer', fn($cq) => $cq->where('postal_code', $data['cp']))
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return filled($data['cp'] ?? null) ? 'CP: ' . $data['cp'] : null;
                    }),

                Filter::make('localidad')
                    ->form([
                        TextInput::make('localidad')
                            ->label('Localidad')
                            ->placeholder('Ej: Madrid'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            filled($data['localidad'] ?? null),
                            fn($q) => $q->whereHas('customer', fn($cq) => $cq->where('ciudad', 'like', '%' . $data['localidad'] . '%'))
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return filled($data['localidad'] ?? null) ? 'Localidad: ' . $data['localidad'] : null;
                    }),

                /*
                Tables\Filters\TernaryFilter::make('cf')
                    ->label('CF (Contrato firmado)')
                    ->trueLabel('Con CF')
                    ->falseLabel('Sin CF')
                    ->queries(
                        true: fn(Builder $q) => $q->whereNotNull('contrato_firmado'),
                        false: fn(Builder $q) => $q->whereNull('contrato_firmado'),
                        blank: fn(Builder $q) => $q,
                    ),
                */

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
                        'excel' => 'EXCEL',
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
            ->filtersLayout(FiltersLayout::AboveContent)
            ->selectable()
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

    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
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

        $totalFinalFormatted = number_format($totalFinal, 2, '.', '');

        $set('total_final', $totalFinalFormatted);
        $set('cuota_final', number_format($totalFinal / $cuotas, 2, '.', ''));
        $set('monto_final_reducida', $totalFinalFormatted);
        $set('monto_final_aumento', $totalFinalFormatted);
    }




    protected static function docCard(
        string $field,
        string $label,
        bool $required = false,
        bool $soloCamara = true,
    ): Group {
        $upload = VentaDocumentUpload::configure(
            FileUpload::make($field),
            $field,
            false,
            true,  // Admin: PDF en cualquier documento (p. ej. contrato firmado)
            false, // sin capture: en PC bloquea el selector de archivos
        )
            ->required(
                fn (?Venta $record) =>
                $required
                && ! self::isSuperAdminPanel()
                && filled($record)
                && ! self::isContratoB($record)
            )
            ->validationMessages([
                'required' => "El documento {$label} es obligatorio.",
            ])
            ->columnSpanFull();

        return VentaDocumentUpload::card($field, $label, $upload);
    }

    /**
     * Badges de atajo a cada slot de documento (solo SuperAdmin).
     */
    protected static function atajosDocumentosHtml(?Venta $record): HtmlString
    {
        return VentaDocumentUpload::atajosBadgesHtml($record, stack: false);
    }

    protected static function isContratoB(?Venta $record): bool
    {
        return filled($record?->nro_contr_adm) && str_contains($record->nro_contr_adm, '-B');
    }

    /** SuperAdmin → Contratos reutiliza este form; ahí precontractual no es obligatorio. */
    protected static function isSuperAdminPanel(): bool
    {
        return filament()->getCurrentPanel()?->getId() === 'superAdmin';
    }

    /** Resuelve los 3 productos prioritarios en orden fijo. */
    protected static function resolvePriorityProductoFilterOptions(): array
    {
        $slots = [
            [
                'label' => 'DEPURADOR VITA ABANTERA',
                'patterns' => [
                    'depurador vita abantera',
                    '%depurador%vita%abantera%',
                ],
                'must_not_contain' => ['mesa'],
            ],
            [
                'label' => 'DEPURADORA DE MESA ABANTERA',
                'patterns' => [
                    'depuradora de mesa abantera',
                    'depurador de mesa abantera',
                    '%depuradora%mesa%abantera%',
                    '%depurador%mesa%abantera%',
                ],
            ],
            [
                'label' => 'FILTROS DEPURADORA OSMOSIS',
                'patterns' => [
                    'filtros depuradora osmosis',
                    '%filtros%depuradora%osmosis%',
                ],
            ],
        ];

        $options = [];
        $usedIds = [];

        foreach ($slots as $slot) {
            $product = self::findProductoForFilterSlot($slot, $usedIds);

            if ($product) {
                $options[$product->id] = $slot['label'];
                $usedIds[] = $product->id;
            }
        }

        return $options;
    }

    protected static function findProductoForFilterSlot(array $slot, array $excludeIds): ?Producto
    {
        foreach ($slot['patterns'] as $pattern) {
            $query = Producto::query()
                ->when(!empty($excludeIds), fn (Builder $q) => $q->whereNotIn('id', $excludeIds));

            if (str_contains($pattern, '%')) {
                $query->whereRaw('LOWER(nombre) LIKE ?', [mb_strtolower($pattern)]);
            } else {
                $query->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower($pattern)]);
            }

            $product = $query->first();

            if (!$product) {
                continue;
            }

            foreach ($slot['must_not_contain'] ?? [] as $forbidden) {
                if (str_contains(mb_strtolower($product->nombre), mb_strtolower($forbidden))) {
                    $product = null;
                    break;
                }
            }

            if ($product) {
                return $product;
            }
        }

        return null;
    }

    /** Opciones del selector Filtro por producto: 2 depuradores, filtros osmosis, resto A→Z. */
    protected static function productoFilterOptions(): array
    {
        $priorities = self::resolvePriorityProductoFilterOptions();
        $usedIds = array_keys($priorities);

        $rest = Producto::query()
            ->activos()
            ->when(!empty($usedIds), fn (Builder $q) => $q->whereNotIn('id', $usedIds))
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->all();

        if (empty($priorities)) {
            return $rest;
        }

        if (empty($rest)) {
            return $priorities;
        }

        // Grupo fijo arriba (orden: depuradores → filtros) + resto alfabético
        return [
            'Principales' => $priorities,
            'Resto' => $rest,
        ];
    }

    protected static function gestionDocsStyles(): string
    {
        return <<<'HTML'
<style>
.gestion-docs,
.gestion-docs .fi-section-header{background-color:#e7f6ec !important;}
.gestion-docs .fi-section-content > .fi-fo-component-ctn > :first-child{display:none !important;}
</style>
HTML;
    }

    protected static function gestionRepartoStyles(): string
    {
        return <<<'HTML'
<style>
.gestion-reparto-compact input[type="number"]::-webkit-outer-spin-button,
.gestion-reparto-compact input[type="number"]::-webkit-inner-spin-button{-webkit-appearance:none;margin:0;}
.gestion-reparto-compact input[type="number"]{-moz-appearance:textfield;}
.gestion-reparto-compact .fi-section-content > .fi-fo-component-ctn > :first-child{display:none !important;}
.gestion-reparto-compact{background-color:#e7f3fc !important;}
.gestion-reparto-compact .fi-section-content .fi-fo-component-ctn{gap:0.65rem 0.75rem !important;}
.gestion-reparto-compact .fi-fo-field-wrp{row-gap:0.15rem;}
.gestion-reparto-compact .fi-input,
.gestion-reparto-compact .fi-input-wrp-label,
.gestion-reparto-compact textarea{font-size:16px !important;line-height:1.4 !important;}
.gestion-reparto-compact .fi-section-header-heading{color:#1e3a8a !important;font-weight:700 !important;text-decoration:none !important;width:100%;}
.gestion-reparto-heading-row{display:flex;align-items:center;justify-content:space-between;gap:0.65rem 1rem;width:100%;flex-wrap:wrap;}
.gestion-reparto-heading-text{text-decoration:underline;text-underline-offset:3px;}
.gestion-reparto-badges{display:flex;flex-wrap:wrap;gap:0.45rem;margin:0;}
.gr-badge{display:inline-flex;align-items:center;gap:0.4rem;border-radius:999px;padding:0.28rem 0.75rem;font-size:15px;font-weight:700;line-height:1.3;letter-spacing:0.01em;}
.gr-badge strong{font-weight:800;}
.gr-badge-com{background:#dbeafe;color:#1e3a8a;}
.gr-badge-rep{background:#ffedd5;color:#9a3412;}
.gr-badge-eur{background:#dcfce7;color:#166534;}
html.dark .gr-badge-com{background:#1e3a8a;color:#dbeafe;}
html.dark .gr-badge-rep{background:#7c2d12;color:#ffedd5;}
html.dark .gr-badge-eur{background:#14532d;color:#dcfce7;}
.gestion-reparto-compact .fi-fo-field-wrp-label span{color:#0f172a !important;}
.gestion-reparto-compact .fi-input,
.gestion-reparto-compact textarea{color:#0f172a !important;}
.gestion-reparto-compact .fi-input:disabled,
.gestion-reparto-compact textarea:disabled{color:#334155 !important;-webkit-text-fill-color:#334155 !important;}
.gestion-reparto-compact .fi-input-wrp-label{color:#334155 !important;}
.gestion-reparto-compact label:has(input[value="sin_cambios"]) span{font-weight:700 !important;color:#334155 !important;}
.gestion-reparto-compact label:has(input[value="entrega_completa"]) span,
.gestion-reparto-compact label:has(input[value="aumenta_oferta"]) span{font-weight:700 !important;color:#15803d !important;}
.gestion-reparto-compact label:has(input[value="disminuye_oferta"]) span{font-weight:700 !important;color:#ea580c !important;}
.gestion-reparto-compact label:has(input[value="no_entrego"]) span{font-weight:700 !important;color:#dc2626 !important;}
html.dark .gestion-reparto-compact{background-color:#0f2744 !important;--tw-ring-color:rgba(147,197,253,.35);}
html.dark .gestion-reparto-compact .fi-section-header-heading{color:#bfdbfe !important;}
html.dark .gestion-reparto-compact .fi-fo-field-wrp-label span{color:#e2e8f0 !important;}
html.dark .gestion-reparto-compact .fi-input-wrp{background-color:rgba(255,255,255,.08) !important;}
html.dark .gestion-reparto-compact .fi-input,
html.dark .gestion-reparto-compact textarea{color:#f8fafc !important;background-color:transparent !important;}
html.dark .gestion-reparto-compact .fi-input::placeholder,
html.dark .gestion-reparto-compact textarea::placeholder{color:#94a3b8 !important;-webkit-text-fill-color:#94a3b8 !important;}
html.dark .gestion-reparto-compact .fi-input:disabled,
html.dark .gestion-reparto-compact textarea:disabled{color:#cbd5e1 !important;-webkit-text-fill-color:#cbd5e1 !important;}
html.dark .gestion-reparto-compact .fi-input-wrp-label{color:#cbd5e1 !important;}
html.dark .gestion-reparto-compact label:has(input[value="sin_cambios"]) span{font-weight:700 !important;color:#e2e8f0 !important;}
html.dark .gestion-reparto-compact label:has(input[value="entrega_completa"]) span,
html.dark .gestion-reparto-compact label:has(input[value="aumenta_oferta"]) span{color:#4ade80 !important;}
html.dark .gestion-reparto-compact label:has(input[value="disminuye_oferta"]) span{color:#fdba74 !important;}
html.dark .gestion-reparto-compact label:has(input[value="no_entrego"]) span{color:#fca5a5 !important;}
.gestion-reparto-compact .ventas-a-restar .fi-fo-field-wrp-label span,
.gestion-reparto-compact .ventas-a-restar .fi-input{color:#dc2626 !important;-webkit-text-fill-color:#dc2626 !important;font-weight:800 !important;}
html.dark .gestion-reparto-compact .ventas-a-restar .fi-fo-field-wrp-label span,
html.dark .gestion-reparto-compact .ventas-a-restar .fi-input{color:#fca5a5 !important;-webkit-text-fill-color:#fca5a5 !important;}
.gestion-reparto-compact .monto-final-negrita .fi-fo-field-wrp-label span,
.gestion-reparto-compact .monto-final-negrita .fi-input,
.gestion-reparto-compact .monto-final-negrita .fi-input-wrp-prefix{font-weight:800 !important;}
@keyframes guardar-gestion-reparto-parpadeo {
  0%, 49% { opacity: 1; }
  50%, 100% { opacity: 0.35; }
}
.guardar-gestion-reparto-btn {
  background-color: #facc15 !important;
  color: #422006 !important;
  font-weight: 800 !important;
  letter-spacing: 0.03em;
  animation: guardar-gestion-reparto-parpadeo 0.9s step-end infinite;
}
.guardar-gestion-reparto-btn .fi-btn-label,
.guardar-gestion-reparto-btn svg { color: #422006 !important; }
@media (prefers-reduced-motion: reduce) {
  .guardar-gestion-reparto-btn { animation: none; }
}
</style>
HTML;
    }

    protected static function totalNroVentasComercialField(string $name, string $label = 'Total Nro Ventas Comercial'): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->disabled()
            ->dehydrated(false)
            ->formatStateUsing(fn (Get $get, ?Venta $record) => self::ventasComercialEnFormulario($get, $record));
    }

    /**
     * Suma el valor de ventas de cada oferta del formulario.
     * Ese valor está en la lista de ofertas (un doblete = 2, una sencilla = 1, etc.).
     *
     * @param  array<int|string, mixed>|null  $ventaOfertas
     */
    public static function totalVentasComercial(?array $ventaOfertas): ?string
    {
        $ids = collect($ventaOfertas ?? [])
            ->map(fn ($line) => is_array($line) ? ($line['oferta_id'] ?? null) : null)
            ->filter(fn ($id) => filled($id))
            ->values();

        if ($ids->isEmpty()) {
            return null;
        }

        $valores = Oferta::query()
            ->whereIn('id', $ids->unique()->all())
            ->pluck('valor', 'id');

        return self::sumarVentasDeLineas($ids->all(), $valores->all());
    }

    /**
     * @param  array<int, mixed>  $ofertaIds
     * @param  array<int|string, mixed>  $valorPorId
     */
    public static function sumarVentasDeLineas(array $ofertaIds, array $valorPorId): ?string
    {
        if ($ofertaIds === []) {
            return null;
        }

        $porId = [];
        foreach ($valorPorId as $id => $valor) {
            $porId[(string) $id] = $valor;
        }

        $sum = 0.0;
        foreach ($ofertaIds as $id) {
            $sum += (float) ($porId[(string) $id] ?? 0);
        }

        return self::formatVentaCount($sum);
    }

    public static function formatVentaCount(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float) str_replace(',', '.', (string) $value);

        if (abs($number - round($number)) < 0.001) {
            return (string) (int) round($number);
        }

        return number_format($number, 1, '.', '');
    }

    protected static function totalVentasComercialDesdeRecord(?Venta $record): ?string
    {
        if (! $record) {
            return null;
        }

        $record->unsetRelation('ventaOfertas');
        $record->load('ventaOfertas');

        return self::totalVentasComercial(
            $record->ventaOfertas
                ->map(fn ($vo) => ['oferta_id' => $vo->oferta_id])
                ->all()
        );
    }

    protected static function syncTotalVentasComercial(Get $get, Set $set, string $root = ''): void
    {
        $lineas = $get($root.'ventaOfertas');
        $partes = self::partirVentasDeLineas(is_array($lineas) ? $lineas : []);
        $vtasCom = $partes['comercial'];
        $vtasRep = $partes['reparto'];

        $set($root.'total_ventas_oferta', $vtasCom);
        $set($root.'total_nro_ventas_comercial_reparto', $vtasCom);
        $set($root.'total_nro_ventas_comercial_disminuye', $vtasCom);
        $set($root.'vtas_rep_parcial', $vtasRep);
        $set(
            $root.'total_vtas_com_disminuye',
            self::totalEntregaParcial($vtasCom, $get($root.'total_venta_reducida'), $vtasRep),
        );
        $set(
            $root.'total_ventas_post_aumento',
            self::sumarVtas($vtasCom, self::vtasRepartoEditadas($get, $root)),
        );
    }

    public static function ventaARestarNegativa(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float) str_replace(',', '.', (string) $value);
        if ($number > 0) {
            $number *= -1;
        }

        return self::formatVentaCount($number);
    }

    public static function sumarVtasVarias(mixed ...$valores): ?string
    {
        $total = null;
        foreach ($valores as $valor) {
            $total = self::sumarVtas($total, $valor);
        }

        return $total;
    }

    public static function totalEntregaParcial(mixed $comercial, mixed $restar, mixed $reparto): ?string
    {
        return self::sumarVtasVarias(
            $comercial,
            self::ventaARestarNegativa($restar) ?? '-1',
            ($reparto === null || $reparto === '') ? '0' : $reparto,
        );
    }

    public static function sumarVtas(mixed $comercial, mixed $reparto): ?string
    {
        if (($comercial === null || $comercial === '') && ($reparto === null || $reparto === '')) {
            return null;
        }

        $izquierda = ($comercial === null || $comercial === '')
            ? 0
            : (float) str_replace(',', '.', (string) $comercial);
        $derecha = ($reparto === null || $reparto === '')
            ? 0
            : (float) str_replace(',', '.', (string) $reparto);

        return self::formatVentaCount($izquierda + $derecha);
    }

    protected static function vtasRepartoEditadas(Get $get, string $root = ''): string
    {
        $valor = $get($root.'total_vtas_com_reparto');
        $numero = ($valor === null || $valor === '')
            ? 0
            : (float) str_replace(',', '.', (string) $valor);

        if ($numero < 1) {
            return '1';
        }

        return self::formatVentaCount($numero) ?? '1';
    }

    protected static function montoFinalRepartoField(string $name): TextInput
    {
        return TextInput::make($name)
            ->label('Monto Final €')
            ->numeric()
            ->prefix('€')
            ->disabled()
            ->dehydrated(false)
            ->extraFieldWrapperAttributes(['class' => 'monto-final-negrita'])
            ->formatStateUsing(fn (Get $get) => $get('total_final'));
    }

    protected static function montoDesdeTotalFinal(mixed $total): ?string
    {
        if ($total === null || $total === '') {
            return null;
        }

        return number_format((float) str_replace(',', '.', (string) $total), 2, '.', '');
    }

    protected static function halfStepSalesField(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->step(0.5)
            ->minValue(1)
            ->default(1)
            ->formatStateUsing(fn (mixed $state): mixed => ($state === null || $state === '') ? 1 : $state)
            ->rules([
                'nullable',
                fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $number = (float) str_replace(',', '.', (string) $value);
                    $halfSteps = $number * 2;

                    if ($number < 1 || abs($halfSteps - round($halfSteps)) > 0.001) {
                        $fail('Solo de 0,5 en 0,5, desde 1.');
                    }
                },
            ]);
    }

    protected static function gestionRepartoSection(): Section
    {
        return Section::make()
            ->id('gestion-reparto')
            ->heading(function (Get $get, ?Venta $record = null): HtmlString {
                $badges = self::gestionRepartoBadgesHtml($get, $record);

                return new HtmlString(
                    '<span class="gestion-reparto-heading-row">'
                    .'<span class="gestion-reparto-heading-text">GESTIÓN DE REPARTO - RESULTADO DE LA ENTREGA</span>'
                    .$badges
                    .'</span>'
                );
            })
            ->collapsible()
            ->collapsed()
            ->compact()
            ->extraAttributes(['class' => 'gestion-reparto-compact'])
            ->columnSpanFull()
            ->columns(2)
            ->schema([
                Placeholder::make('gestion_reparto_styles')
                    ->hiddenLabel()
                    ->content(new HtmlString(self::gestionRepartoStyles()))
                    ->extraAttributes(['style' => 'display:none'])
                    ->columnSpanFull(),

                Hidden::make('total_ventas_oferta')
                    ->dehydrated()
                    ->afterStateHydrated(function (Set $set, ?Venta $record): void {
                        $set('total_ventas_oferta', self::totalVentasComercialDesdeRecord($record));
                    }),

                Radio::make('resultado_entrega')
                    ->hiddenLabel()
                    ->options([
                        'sin_cambios' => 'Sin Cambios',
                        'entrega_completa' => 'Entrega completa',
                        'aumenta_oferta' => 'Venta Reparto',
                        'disminuye_oferta' => 'Entrega parcial',
                        'no_entrego' => 'Sin entrega',
                    ])
                    ->default('sin_cambios')
                    ->formatStateUsing(fn ($state) => filled($state) ? $state : 'sin_cambios')
                    ->inline()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set, Get $get, ?Venta $record = null): void {
                        $vtasRep = self::vtasRepartoEnFormulario($get, $record);
                        $set('vtas_rep_parcial', $vtasRep === '' ? '0' : $vtasRep);

                        if ($state !== 'aumenta_oferta') {
                            return;
                        }

                        $editadas = self::vtasRepartoEditadas($get);
                        $set('total_vtas_com_reparto', $editadas);
                        $set('total_ventas_post_aumento', self::sumarVtas(
                            self::ventasComercialEnFormulario($get, $record),
                            $editadas,
                        ));
                    })
                    ->columnSpanFull(),

                Grid::make(['default' => 1, 'lg' => 5])
                    ->schema([
                        self::totalNroVentasComercialField('total_nro_ventas_comercial_disminuye', 'Vtas Con.'),

                        TextInput::make('total_venta_reducida')
                            ->label('Vtas a descontar')
                            ->numeric()
                            ->step(0.5)
                            ->maxValue(-0.5)
                            ->required()
                            ->default(-1)
                            ->live(onBlur: true)
                            ->extraFieldWrapperAttributes(['class' => 'ventas-a-restar'])
                            ->formatStateUsing(fn (mixed $state): string => self::ventaARestarNegativa($state) ?? '-1')
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                $numero = ($state === null || $state === '')
                                    ? null
                                    : (float) str_replace(',', '.', (string) $state);

                                if ($numero !== null && $numero > 0) {
                                    $state = self::ventaARestarNegativa($numero);
                                    $set('total_venta_reducida', $state);

                                    \Filament\Notifications\Notification::make()
                                        ->title('Este número debe figurar en negativo. Ej; -1')
                                        ->warning()
                                        ->send();
                                }

                                $set('total_vtas_com_disminuye', self::totalEntregaParcial(
                                    self::ventasComercialEnFormulario($get, null),
                                    $state,
                                    self::vtasRepartoDeLineas(is_array($get('ventaOfertas')) ? $get('ventaOfertas') : []),
                                ));
                            })
                            ->rules([
                                'required',
                                fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                    if ($value === null || $value === '') {
                                        $fail('Indica las vtas a descontar en negativo.');

                                        return;
                                    }

                                    $number = (float) str_replace(',', '.', (string) $value);
                                    $halfSteps = $number * 2;

                                    if ($number >= 0 || abs($halfSteps - round($halfSteps)) > 0.001) {
                                        $fail('Debe ser negativo, de 0,5 en 0,5.');
                                    }
                                },
                            ]),

                        TextInput::make('vtas_rep_parcial')
                            ->label('Vtas Rep.')
                            ->disabled()
                            ->dehydrated(false)
                            ->default('0')
                            ->afterStateHydrated(function (TextInput $component, Get $get, ?Venta $record = null): void {
                                $valor = self::vtasRepartoEnFormulario($get, $record);
                                $component->state(($valor === null || $valor === '') ? '0' : $valor);
                            }),

                        TextInput::make('total_vtas_com_disminuye')
                            ->label('Total Vtas Com + Rep')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn (Get $get, ?Venta $record): ?string => self::totalEntregaParcial(
                                self::ventasComercialEnFormulario($get, $record),
                                $get('total_venta_reducida'),
                                self::vtasRepartoEnFormulario($get, $record),
                            )),

                        self::montoFinalRepartoField('monto_final_reducida'),
                    ])
                    ->visible(fn (Get $get): bool => $get('resultado_entrega') === 'disminuye_oferta')
                    ->columnSpanFull(),

                Grid::make(['default' => 1, 'lg' => 4])
                    ->schema([
                        self::totalNroVentasComercialField('total_nro_ventas_comercial_reparto', 'Total Ventas del Comercial'),

                        self::halfStepSalesField('total_vtas_com_reparto', 'Vtas Rep.')
                            ->live(debounce: 300)
                            ->formatStateUsing(fn (mixed $state): mixed => ((float) str_replace(',', '.', (string) ($state ?? 0)) < 1) ? 1 : $state)
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                $set('total_ventas_post_aumento', self::sumarVtas(
                                    self::ventasComercialEnFormulario($get, null),
                                    $state,
                                ));
                            }),

                        TextInput::make('total_ventas_post_aumento')
                            ->label('Total Vtas Com + Reparto')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn (Get $get, ?Venta $record) => self::sumarVtas(
                                self::ventasComercialEnFormulario($get, $record),
                                self::vtasRepartoEditadas($get),
                            )),

                        self::montoFinalRepartoField('monto_final_aumento'),
                    ])
                    ->visible(fn (Get $get): bool => $get('resultado_entrega') === 'aumenta_oferta')
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('observaciones_gestion_reparto')
                    ->label('Observaciones de Gestión/Reparto')
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('guardarGestionReparto')
                        ->label('GUARDAR CAMBIOS GESTION REPARTO')
                        ->color('warning')
                        ->extraAttributes(['class' => 'guardar-gestion-reparto-btn'])
                        ->action(function ($livewire): void {
                            if (method_exists($livewire, 'save')) {
                                $livewire->save();
                            }
                        }),
                ])
                    ->visible(fn (Get $get): bool => filled($get('resultado_entrega')) && $get('resultado_entrega') !== 'sin_cambios')
                    ->columnSpanFull(),
            ]);
    }

    protected static function gestionRepartoBadgesHtml(Get $get, ?Venta $record): string
    {
        [$vtasCom, $vtasRep] = self::badgesVentasComYRep($get, $record);
        $vtasCom = e($vtasCom);
        $vtasRep = e($vtasRep);
        $total = e(self::badgeTotalEuros($get, $record));

        return <<<HTML
<span class="gestion-reparto-badges">
    <span class="gr-badge gr-badge-com">Vtas Com <strong>{$vtasCom}</strong></span>
    <span class="gr-badge gr-badge-rep">Vtas Rep <strong>{$vtasRep}</strong></span>
    <span class="gr-badge gr-badge-eur">Total € <strong>{$total}</strong></span>
</span>
HTML;
    }

    /**
     * Vtas Com: ofertas del comercial.
     * Vtas Rep: ventas de las ofertas declaradas solo por el repartidor. Si no hay, 0.
     *
     * @return array{0: string, 1: string}
     */
    protected static function badgesVentasComYRep(Get $get, ?Venta $record): array
    {
        $lineas = self::lineasParaVentas($get, $record);
        $split = ['comercial' => null, 'reparto' => '0'];

        if ($lineas !== []) {
            $ids = collect($lineas)
                ->map(fn ($line) => is_array($line) ? ($line['oferta_id'] ?? null) : null)
                ->filter(fn ($id) => filled($id))
                ->unique()
                ->values();

            $valores = $ids->isEmpty()
                ? []
                : Oferta::query()->whereIn('id', $ids->all())->pluck('valor', 'id')->all();

            $split = self::clasificarVentasDeLineas($lineas, $valores);
        }

        $vtasCom = $split['comercial']
            ?? self::formatVentaCount($get('total_ventas_oferta'))
            ?? self::totalVentasComercialDesdeRecord($record)
            ?? '—';

        $resultado = $get('resultado_entrega') ?: $record?->resultado_entrega ?: 'sin_cambios';

        if ($resultado === 'aumenta_oferta') {
            return [$vtasCom, self::vtasRepartoEditadas($get)];
        }

        return [$vtasCom, $split['reparto']];
    }

    /**
     * @param  array<int|string, mixed>  $lineas
     * @return array<int, array<string, mixed>>
     */
    protected static function lineasParaVentas(Get $get, ?Venta $record): array
    {
        $fromForm = $get('ventaOfertas');
        if (is_array($fromForm)) {
            return array_values($fromForm);
        }

        if (! $record) {
            return [];
        }

        return self::lineasDesdeRecord($record);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function lineasDesdeRecord(Venta $record): array
    {
        $record->loadMissing('ventaOfertas.productos');

        return $record->ventaOfertas
            ->map(fn ($vo) => [
                'oferta_id' => $vo->oferta_id,
                'productos' => $vo->productos->map(fn ($producto) => [
                    'vendido_por' => $producto->vendido_por instanceof \BackedEnum
                        ? $producto->vendido_por->value
                        : $producto->vendido_por,
                ])->all(),
            ])
            ->all();
    }

    /**
     * @param  array<int|string, mixed>|null  $lineas
     * @return array{comercial: ?string, reparto: string}
     */
    protected static function partirVentasDeLineas(?array $lineas): array
    {
        if ($lineas === null || $lineas === []) {
            return ['comercial' => null, 'reparto' => '0'];
        }

        $ids = collect($lineas)
            ->map(fn ($line) => is_array($line) ? ($line['oferta_id'] ?? null) : null)
            ->filter(fn ($id) => filled($id))
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return ['comercial' => null, 'reparto' => '0'];
        }

        $valores = Oferta::query()
            ->whereIn('id', $ids->all())
            ->pluck('valor', 'id')
            ->all();

        return self::clasificarVentasDeLineas(array_values($lineas), $valores);
    }

    /**
     * @param  array<int|string, mixed>|null  $lineas
     */
    protected static function vtasRepartoDeLineas(?array $lineas): string
    {
        return self::partirVentasDeLineas($lineas)['reparto'];
    }

    protected static function ventasComercialEnFormulario(Get $get, ?Venta $record): ?string
    {
        $lineas = $get('ventaOfertas');
        if (is_array($lineas)) {
            return self::partirVentasDeLineas($lineas)['comercial'];
        }

        if (! $record) {
            return null;
        }

        $record->unsetRelation('ventaOfertas');

        return self::partirVentasDeLineas(self::lineasDesdeRecord($record))['comercial'];
    }

    protected static function vtasRepartoEnFormulario(Get $get, ?Venta $record): string
    {
        $lineas = $get('ventaOfertas');
        if (is_array($lineas)) {
            return self::vtasRepartoDeLineas($lineas);
        }

        return self::vtasRepartoDesdeRecord($record);
    }

    protected static function vtasRepartoDesdeRecord(?Venta $record): string
    {
        if (! $record) {
            return '0';
        }

        $record->unsetRelation('ventaOfertas');

        return self::vtasRepartoDeLineas(self::lineasDesdeRecord($record));
    }

    /**
     * @param  array<int|string, mixed>  $lineas
     * @param  array<int|string, mixed>  $valorPorId
     * @return array{comercial: ?string, reparto: string}
     */
    public static function clasificarVentasDeLineas(array $lineas, array $valorPorId): array
    {
        $porId = [];
        foreach ($valorPorId as $id => $valor) {
            $porId[(string) $id] = (float) $valor;
        }

        $comercial = 0.0;
        $reparto = 0.0;
        $hayOferta = false;

        foreach ($lineas as $line) {
            if (! is_array($line) || ! filled($line['oferta_id'] ?? null)) {
                continue;
            }

            $hayOferta = true;
            $valor = $porId[(string) $line['oferta_id']] ?? 0.0;

            if (self::ofertaEsVentaReparto($line)) {
                $reparto += $valor;
            } else {
                $comercial += $valor;
            }
        }

        return [
            'comercial' => $hayOferta ? (self::formatVentaCount($comercial) ?? '0') : null,
            'reparto' => $reparto > 0 ? (self::formatVentaCount($reparto) ?? '0') : '0',
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public static function ofertaEsVentaReparto(array $line): bool
    {
        $productos = collect($line['productos'] ?? []);
        $tieneRepartidor = $productos->contains(
            fn ($producto) => self::vendidoPorDeLinea($producto) === VendidoPor::Repartidor->value
        );
        $tieneComercial = $productos->contains(
            fn ($producto) => self::vendidoPorDeLinea($producto) === VendidoPor::Comercial->value
        );

        return $tieneRepartidor && ! $tieneComercial;
    }

    protected static function vendidoPorDeLinea(mixed $producto): ?string
    {
        $vendidoPor = is_array($producto) ? ($producto['vendido_por'] ?? null) : null;

        if ($vendidoPor instanceof \BackedEnum) {
            $vendidoPor = $vendidoPor->value;
        }

        return is_string($vendidoPor) ? $vendidoPor : null;
    }

    protected static function badgeTotalEuros(Get $get, ?Venta $record): string
    {
        $raw = $get('total_final');
        if ($raw === null || $raw === '') {
            $raw = $record?->total_final;
        }
        if ($raw === null || $raw === '') {
            return '—';
        }

        return number_format((float) str_replace(',', '.', (string) $raw), 2, '.', '');
    }

    public static function normalizeGestionReparto(array $data, ?Venta $record = null): array
    {
        $resultado = $data['resultado_entrega'] ?? null;

        if (! in_array($resultado, ['sin_cambios', 'entrega_completa', 'disminuye_oferta', 'aumenta_oferta', 'no_entrego'], true)) {
            $data['resultado_entrega'] = null;
            $resultado = null;
        }

        $partes = $record
            ? self::partirVentasDeLineas((function () use ($record): array {
                $record->unsetRelation('ventaOfertas');

                return self::lineasDesdeRecord($record);
            })())
            : ['comercial' => self::formatVentaCount($data['total_ventas_oferta'] ?? null), 'reparto' => '0'];
        $ventasComercial = $partes['comercial'];
        $data['total_ventas_oferta'] = $ventasComercial;

        if ($resultado === 'disminuye_oferta') {
            $restar = self::ventaARestarNegativa($data['total_venta_reducida'] ?? null) ?? '-1';
            $data['total_venta_reducida'] = $restar;
            $data['total_vtas_com_disminuye'] = self::totalEntregaParcial(
                $ventasComercial,
                $restar,
                $partes['reparto'],
            );
            $data['monto_final_reducida'] = self::montoDesdeTotalFinal($data['total_final'] ?? null);
        } else {
            $data['total_venta_reducida'] = null;
            $data['total_vtas_com_disminuye'] = null;
            $data['monto_final_reducida'] = null;
        }

        if ($resultado === 'aumenta_oferta') {
            $vtasRep = self::formatVentaCount($data['total_vtas_com_reparto'] ?? null);
            if ($vtasRep === null || (float) $vtasRep < 1) {
                $vtasRep = '1';
            }
            $data['total_vtas_com_reparto'] = $vtasRep;
            $data['monto_final_aumento'] = self::montoDesdeTotalFinal($data['total_final'] ?? null);
            $data['total_ventas_post_aumento'] = self::sumarVtas($ventasComercial, $vtasRep);
        } else {
            $data['total_ventas_post_aumento'] = null;
            $data['total_vtas_com_reparto'] = null;
            $data['monto_final_aumento'] = null;
        }

        foreach ([
            'total_ventas_oferta',
            'total_venta_reducida',
            'total_vtas_com_disminuye',
            'monto_final_reducida',
            'total_ventas_post_aumento',
            'total_vtas_com_reparto',
            'monto_final_aumento',
        ] as $key) {
            if (array_key_exists($key, $data) && $data[$key] === '') {
                $data[$key] = null;
            }
        }

        if (array_key_exists('observaciones_gestion_reparto', $data) && $data['observaciones_gestion_reparto'] === '') {
            $data['observaciones_gestion_reparto'] = null;
        }

        return $data;
    }


}
