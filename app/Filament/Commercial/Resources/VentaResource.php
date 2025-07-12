<?php

namespace App\Filament\Commercial\Resources;

use App\Filament\Commercial\Resources\VentaResource\Pages;
use App\Filament\Commercial\Resources\VentaResource\RelationManagers;
use App\Models\Venta;
use App\Models\User;
use App\Models\Producto;
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
use Filament\Tables\Columns\{TextColumn, BadgeColumn};

class VentaResource extends Resource
{
    protected static ?string $model = Venta::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                /* ---------- Cliente (editable) ---------- */
                Section::make('Información del cliente')->schema([
                    Hidden::make('note_id')->required(),

                    Grid::make(3)->schema([
                        TextInput::make('first_names')->label('Nombres')->required(),
                        TextInput::make('last_names')->label('Apellidos')->required(),
                        TextInput::make('dni')->label('DNI'),

                        TextInput::make('phone')->label('Teléfono')->tel()->required(),
                        TextInput::make('secondary_phone')->label('Teléfono 2')->tel(),
                        TextInput::make('email')->label('Email')->email(),

                        DatePicker::make('fecha_nac')->label('Fec. nac.'),
                        TextInput::make('age')->numeric()->label('Edad'),

                        TextInput::make('iban')->label('IBAN')->columnSpanFull(),

                        Select::make('postal_code_id')
                            ->label('Código postal')
                            ->required()
                            ->options(function () {
                                // Igual que en NoteResource: juntamos código + ciudad
                                return PostalCode::query()
                                    ->select('postal_codes.id', 'postal_codes.code', 'cities.title as city_title')
                                    ->join('cities', 'cities.id', '=', 'postal_codes.city_id')
                                    ->orderBy('cities.title')
                                    ->orderBy('postal_codes.code')
                                    ->limit(500) // evita traer 20 000 CP si no hace falta
                                    ->get()
                                    ->mapWithKeys(fn($item) => [
                                        $item->id => "{$item->city_title} - {$item->code}"
                                    ]);
                            })
                            ->searchable(['code', 'city.title'])   // permite buscar por CP o ciudad
                            ->preload()
                            ->native(false)
                            ->validationMessages([
                                'required' => 'El código postal es obligatorio',
                            ]),


                        TextInput::make('primary_address')->label('Dirección 1')->columnSpan(2),
                        TextInput::make('secondary_address')->label('Dirección 2')->columnSpan(2),
                        TextInput::make('parish')->label('Parroquia'),

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

                        Select::make('ingresos_rango')
                            ->label('Ingresos netos mensuales')
                            ->required()
                            ->options(\App\Enums\IngresosRango::options())
                            ->native(false),

                        Select::make('situacion_laboral')
                            ->label('Situación laboral')
                            ->required()
                            ->options(\App\Enums\SituacionLaboral::options())
                            ->native(false),

                    ])->columns(3),
                ]),

                /* ---------- Datos de la venta ---------- */
                Section::make('Datos de la venta')->schema([
                    DatePicker::make('fecha_venta')
                        ->label('Fecha')
                        ->default(now())
                        ->required(),

                    Select::make('companion_id')
                        ->label('Compañero/a')
                        ->native(false)
                        ->searchable()
                        ->placeholder('Sin compañero')
                        ->options(function () {
                            return User::role('commercial')
                                ->select('id', 'empleado_id', 'name', 'last_name')
                                ->get()
                                ->mapWithKeys(fn($u) => [
                                    $u->id => "{$u->empleado_id} - {$u->name} {$u->last_name}",
                                ]);
                        }),

                    TextInput::make('importe_total')
                        ->label('Importe total (€)')
                        ->numeric()
                        ->prefix('€')
                        ->required(),

                    TextInput::make('num_cuotas')
                        ->label('Nº de cuotas')
                        ->numeric()
                        ->minValue(1),

                    TextInput::make('accesorio_entregado')
                        ->label('Accesorio entregado')
                        ->placeholder('Ej.: Almohada viscoelástica'),

                    TextInput::make('motivo_venta')
                        ->label('Motivo de la venta')
                        ->placeholder('Razón principal de la compra'),

                    TextInput::make('motivo_horario')
                        ->label('Motivo del horario')
                        ->placeholder('Por qué se eligió esa franja'),

                    Forms\Components\Textarea::make('observaciones_repartidor')
                        ->label('Observaciones')
                        ->rows(3)
                        ->columnSpanFull(),

                    Toggle::make('interes_art')
                        ->label('Interés en artículo de regalo'),
                ])->columns(2),

                /* ---------- Ofertas ---------- */
                Section::make('Ofertas incluidas')
                    ->schema([
                        Repeater::make('ventaOfertas')
                            ->relationship()
                            ->label('Ofertas')
                            ->minItems(1)
                            ->defaultItems(1)
                            ->schema([

                                /* ─────────── Campos de la oferta ─────────── */
                                Grid::make(3)
                                    ->schema([
                                        Select::make('oferta_id')
                                            ->label('Oferta')
                                            ->relationship('oferta', 'nombre')
                                            ->searchable()
                                            ->required(),

                                        TextInput::make('precio_cerrado')
                                            ->label('Precio cerrado (€)')
                                            ->numeric()
                                            ->required()
                                            ->prefix('€'),

                                        TextInput::make('puntos')
                                            ->numeric()
                                            ->label('Puntos')
                                            ->required(),
                                    ]),

                                /* ─────────── Bloque de productos ─────────── */
                                Section::make('Productos de la oferta')
                                    ->collapsed()          // opcional: empieza plegado
                                    ->schema([
                                        Repeater::make('productos')
                                            ->relationship()
                                            ->label('Productos')
                                            ->minItems(1)
                                            ->defaultItems(1)
                                            ->schema([
                                                Grid::make(4)    // 4 columnas exactas
                                                    ->schema([
                                                        Select::make('producto_id')
                                                            ->label('Producto')
                                                            ->relationship('producto', 'nombre')
                                                            ->searchable()
                                                            ->required(),

                                                        TextInput::make('cantidad')
                                                            ->numeric()
                                                            ->minValue(1)
                                                            ->required(),

                                                        TextInput::make('precio_unitario')
                                                            ->numeric()
                                                            ->required()
                                                            ->prefix('€'),

                                                        TextInput::make('puntos_linea')
                                                            ->numeric()
                                                            ->label('Puntos línea')
                                                            ->default(0),
                                                    ]),
                                            ])
                                            ->columns(1)  // un bloque “producto” por fila
                                            ->itemLabel(
                                                fn($state) =>
                                                $state['producto_id']
                                                ? Producto::find($state['producto_id'])->nombre
                                                : 'Nuevo producto'
                                            ),
                                    ])
                                    ->columns(1), // el Section muestra solo su repeater

                            ])
                            ->columns(1)      // un bloque “oferta” por fila
                            ->collapsible(),  // la oferta entera se puede plegar
                    ])

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
