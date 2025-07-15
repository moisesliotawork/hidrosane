<?php

namespace App\Filament\Commercial\Resources;

use App\Filament\Commercial\Resources\VentaResource\Pages;
use App\Filament\Commercial\Resources\VentaResource\RelationManagers;
use App\Models\Venta;
use App\Models\User;
use App\Models\Producto;
use App\Models\Oferta;
use App\Models\PostalCode;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
use Illuminate\Support\Collection;

class VentaResource extends Resource
{
    protected static ?string $model = Venta::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';



    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                /* ---------- Cliente (editable) ---------- */
                Section::make('Información del cliente')
                    ->schema([
                        Hidden::make('note_id')->required(),

                        Grid::make([
                            'default' => 1,   // móviles
                            'md' => 2,   // >= 768 px
                            'xl' => 3,   // >= 1280 px
                        ])->schema([

                                    // ➊ Datos personales
                                    TextInput::make('first_names')
                                        ->label('Nombres')
                                        ->required(),

                                    TextInput::make('last_names')
                                        ->label('Apellidos')
                                        ->required(),

                                    TextInput::make('dni')
                                        ->label('DNI')
                                        ->columnSpanFull(),          // ocupa el ancho completo

                                    DatePicker::make('fecha_nac')
                                        ->label('Fec. nac.'),

                                    TextInput::make('age')
                                        ->numeric()
                                        ->label('Edad'),

                                    // ➋ Contacto
                                    TextInput::make('phone')
                                        ->label('Teléfono')
                                        ->tel()
                                        ->required(),

                                    TextInput::make('secondary_phone')
                                        ->label('Teléfono 2')
                                        ->tel(),

                                    TextInput::make('email')
                                        ->label('Email')
                                        ->email()
                                        ->columnSpanFull(),

                                    // ➌ Bancarios
                                    TextInput::make('iban')
                                        ->label('IBAN')
                                        ->columnSpanFull()

                                        // ─── Presentación → “ES12 3456 7890 …” ───────────────
                                        ->formatStateUsing(fn(?string $state) => $state
                                            ? implode(' ', str_split(strtoupper($state), 4))
                                            : null)

                                        // ─── Guardado → “ES1234567890…” ──────────────────────
                                        ->dehydrateStateUsing(fn(?string $state) => $state
                                            ? str_replace(' ', '', strtoupper($state))
                                            : null)

                                        // ─── Mientras escribe / pega ─────────────────────────
                                        ->afterStateUpdated(function ($state, callable $set) {
                                            $plain = str_replace(' ', '', strtoupper($state ?? ''));
                                            $formatted = implode(' ', str_split($plain, 4));

                                            if ($formatted !== $state) {
                                                $set($formatted);            // actualiza la vista con los espacios
                                            }
                                        }),

                                    // ➍ Dirección
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

                                    TextInput::make('primary_address')
                                        ->label('Dirección 1')
                                        ->columnSpanFull(),

                                    TextInput::make('secondary_address')
                                        ->label('Dirección 2')
                                        ->columnSpanFull(),

                                    TextInput::make('parish')
                                        ->label('Parroquia'),

                                    // ➎ Datos económicos / hogar
                                    Select::make('tipo_vivienda')
                                        ->label('Tipo de vivienda')
                                        ->required()
                                        ->options(\App\Enums\TipoVivienda::options())
                                        ->native(false),

                                    Select::make('estado_civil')
                                        ->label('Estado civil')
                                        ->required()
                                        ->options(\App\Enums\EstadoCivil::options())
                                        ->native(false),

                                    Select::make('situacion_laboral')
                                        ->label('Situación laboral')
                                        ->required()
                                        ->options(\App\Enums\SituacionLaboral::options())
                                        ->native(false),

                                    Select::make('ingresos_rango')
                                        ->label('Ingresos netos mensuales')
                                        ->required()
                                        ->options(\App\Enums\IngresosRango::options())
                                        ->native(false),

                                    Select::make('num_hab_casa')
                                        ->label('Número de habitaciones')
                                        ->options(
                                            fn() => collect(range(1, 10))
                                                ->mapWithKeys(fn($n) => [$n => (string) $n])
                                                ->toArray()
                                        )
                                        ->searchable()    
                                        ->preload()       
                                        ->default(1) 
                                        ->required()
                                        ->reactive(),


                                ]),
                    ]),

                /* ---------- Ofertas ---------- */
                Section::make('Ofertas incluidas')
                    ->schema([
                        Repeater::make('ventaOfertas')
                            ->relationship()
                            ->createItemButtonLabel('Agregar Oferta')
                            ->minItems(1)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $total = collect($get('ventaOfertas') ?? [])
                                    ->sum(function ($o) {
                                        return Oferta::find($o['oferta_id'] ?? 0)?->precio_base ?? 0;
                                    });

                                $set('importe_total', number_format($total, 2, '.', ''));
                            })
                            ->validationMessages([
                                'min' => 'Debes agregar al menos una oferta a la venta.',
                                'required' => 'Debes agregar al menos una oferta a la venta.',
                            ])
                            ->defaultItems(1)
                            ->itemLabel(function ($state) {
                                return blank($state['oferta_id'] ?? null)
                                    ? 'Nueva oferta'
                                    : Oferta::find($state['oferta_id'])?->nombre;
                            })
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
                                                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                            $producto = Producto::find($state);
                                                            $cantidad = (int) ($get('cantidad') ?? 1);

                                                            // 1. actualizar puntos de la línea
                                                            $set('puntos_linea', $cantidad * ($producto?->puntos ?? 0));

                                                            // 2. recalcular total puntos oferta
                                                            $total = collect($get('../../productos') ?? [])
                                                                ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0));

                                                            $set('../../puntos', $total);
                                                        }),

                                                    /* ─── Cantidad ─── */
                                                    TextInput::make('cantidad')
                                                        ->numeric()
                                                        ->minValue(1)
                                                        ->reactive()
                                                        ->default(1)
                                                        ->required()
                                                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                            // 1. Si queda vacío o por debajo de 1, lo reiniciamos a 1
                                                            $cantidad = (int) $state;
                                                            if ($cantidad < 1) {
                                                                $cantidad = 1;
                                                                $set('cantidad', 1);
                                                            }

                                                            // 2. Actualizamos los puntos de la línea según la cantidad
                                                            $producto = Producto::find($get('producto_id'));
                                                            $set('puntos_linea', $cantidad * ($producto?->puntos ?? 0));

                                                            // 3. Recalculamos el total de puntos de la oferta
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
                                                fn($state) =>
                                                blank($state['producto_id'] ?? null)
                                                ? 'Nuevo producto'
                                                : Producto::find($state['producto_id'])->nombre
                                            ),
                                    ])
                                    ->columns(1),

                            ])
                            ->columns(1)
                            ->collapsible(),
                    ]),

                /* ---------- Productos externos ---------- */
                Section::make('Productos externos')
                    ->visible(function (Get $get) {
                        // ¿Hay alguna oferta con “Producto Externo”?
                        return collect($get('ventaOfertas') ?? [])
                            ->filter(function ($oferta) {
                            return collect($oferta['productos'] ?? [])
                                ->contains(function ($linea) {
                                    $id = $linea['producto_id'] ?? null;
                                    return optional(Producto::find($id))->nombre === 'Producto Externo';
                                });
                        })
                            ->isNotEmpty();
                    })
                    ->schema(function (Get $get) {
                        // Una entrada por oferta que tenga al menos un «Producto Externo»
                        $externas = collect($get('ventaOfertas') ?? [])
                            ->filter(function ($oferta) {
                            return collect($oferta['productos'] ?? [])
                                ->contains(function ($linea) {
                                    $id = $linea['producto_id'] ?? null;
                                    return optional(Producto::find($id))->nombre === 'Producto Externo';
                                });
                        })
                            ->values();                 // re-indexa 0,1,2…
            
                        return $externas->map(function ($_, $idx) {
                            return TextInput::make("productos_externos.$idx")
                                ->label("Nombre producto externo #" . ($idx + 1))
                                ->required()
                                ->dehydrated();         // guarda el valor
                        })->all();
                    })
                    ->columns(1)
                    ->collapsible()
                    ->reactive(),                     // necesario para refrescar al cambiar ofertas

                /* ---------- Datos de la venta ---------- */
                Section::make('Datos de la venta')->schema([
                    DatePicker::make('fecha_venta')
                        ->label('Fecha')
                        ->default(now())
                        ->required(),



                    /* … dentro del schema … */
                    Select::make('companion_id')
                        ->label('Compañero/a')
                        ->native(false)
                        ->searchable()
                        ->nullable()                         // permite null en la columna
                        ->options(function (): Collection {
                            // lista de comerciales EXCLUYENDO al autenticado
                            $comerciales = User::role('commercial')
                                ->whereKeyNot(auth()->id())
                                ->select('id', 'empleado_id', 'name', 'last_name')
                                ->get()
                                ->mapWithKeys(fn($u) => [
                                    $u->id => "{$u->empleado_id} - {$u->name} {$u->last_name}",
                                ]);

                            // añadimos “SIN COMPAÑERO” con clave vacía
                            return collect(['' => 'SIN COMPAÑERO'])->merge($comerciales);
                        })
                        ->default('')                        // al abrir el formulario se ve la opción
                        ->dehydrateStateUsing(               // ← aquí la magia
                            fn(mixed $state) => blank($state) ? null : $state
                        )
                        ->formatStateUsing(                  // ← para ediciones futuras
                            fn(mixed $state) => $state ?? ''
                        ),

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


                    TextInput::make('num_cuotas')
                        ->label('Nº de cuotas')
                        ->numeric()
                        ->reactive()
                        ->required()
                        ->rules([
                            'integer',
                            Rule::in(array_merge([1], range(6, 39))),   // 1 ó 6–39
                        ])
                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            $importe = (float) ($get('importe_total') ?? 0);
                            $cuotas = (int) $state ?: 1;

                            $set('cuota_mensual', number_format($importe / $cuotas, 2, '.', ''));
                        }),

                    TextInput::make('accesorio_entregado')
                        ->label('¿Haz entregado algun ACCESORIO AL CLIENTE?')
                        ->placeholder('Ej.: Almohada viscoelástica'),

                    TextInput::make('cuota_mensual')
                        ->label('Cuota mensual (€)')
                        ->numeric()
                        ->prefix('€')
                        ->disabled()
                        ->dehydrated()
                        ->reactive(),
                ])->columns(2),

                Section::make('Información hoja de Incidencias')->schema([
                    TextInput::make('motivo_venta')
                        ->label('¿Por qué pusiste le vendiste?')
                        ->placeholder('Razón principal de la compra'),

                    TextInput::make('motivo_horario')
                        ->label('¿Por qué pusiste ese horario?')
                        ->placeholder('Por qué se eligió esa franja'),

                    Toggle::make('interes_art')
                        ->label('¿Al cliente le ha interesado más artículos que no le has vendido?'),

                    Forms\Components\Textarea::make('observaciones_repartidor')
                        ->label('Observaciones adicionales para el repartidor')
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVentas::route('/'),
            'create' => Pages\CreateVenta::route('/create/{note?}'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canEdited(): bool
    {
        return false;
    }

}
