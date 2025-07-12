<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\VentaResource\Pages;
use App\Filament\Admin\Resources\VentaResource\RelationManagers;
use App\Models\Venta;
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

                        Select::make('tipo_vivienda')->options([
                            'propia' => 'Propia',
                            'alquilada' => 'Alquilada',
                            'otros' => 'Otros',
                        ])->label('Tipo vivienda'),

                        Select::make('estado_civil')->options([
                            'soltero' => 'Soltero',
                            'casado' => 'Casado',
                            'pareja' => 'Pareja',
                            'otros' => 'Otros',
                        ])->label('Estado civil'),

                        TextInput::make('situacion_laboral')->label('Situación laboral'),
                        TextInput::make('ingresos_rango')->label('Rango ingresos'),
                        TextInput::make('num_hab_casa')->label('Nº habitantes casa')->numeric(),
                    ])->columns(3),
                ]),

                /* ---------- Datos de la venta ---------- */
                Section::make('Datos de la venta')->schema([
                    DatePicker::make('fecha_venta')
                        ->label('Fecha')
                        ->default(now())
                        ->required(),

                    TextInput::make('importe_total')
                        ->label('Importe total (€)')
                        ->numeric()
                        ->prefix('€')
                        ->required(),

                    TextInput::make('num_cuotas')
                        ->label('Nº de cuotas')
                        ->numeric()
                        ->minValue(1),

                    Toggle::make('interes_art')
                        ->label('Interés en artículo de regalo'),
                ])->columns(2),

                /* ---------- Ofertas ---------- */
                Section::make('Ofertas incluidas')->schema([
                    Repeater::make('ventaOfertas')
                        ->relationship()
                        ->label('Ofertas')
                        ->minItems(1)
                        ->defaultItems(1)
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

                            Repeater::make('productos')
                                ->relationship()
                                ->label('Productos')
                                ->minItems(1)
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
                                ])
                                ->columns(4),
                        ])
                        ->columns(3)
                        ->collapsible(),
                ]),
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
            'edit' => Pages\EditVenta::route('/{record}/edit'),
        ];
    }
}
