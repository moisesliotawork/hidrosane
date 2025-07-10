<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProductoResource\Pages;
use App\Filament\Admin\Resources\ProductoResource\RelationManagers;
use App\Models\Producto;
use App\Models\TipoMedida;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Group;
use Filament\Tables\Columns\TextColumn;

class ProductoResource extends Resource
{
    protected static ?string $model = Producto::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Group::make([
                    TextInput::make('nombre')->required()->label('Nombre'),
                    TextInput::make('puntos')->numeric()->required(),

                    Select::make('tipo_medida_id')
                        ->label('Tipo de medida')
                        ->options(TipoMedida::all()->pluck('nombre', 'id'))
                        ->searchable()
                        ->preload()
                        ->reactive()
                        ->afterStateUpdated(fn($set) => $set('valor', '')),

                    TextInput::make('valor')
                        ->label('Valor de la medida')
                        ->helperText('Opcional. Requerido si el producto tiene medida')
                        ->visible(fn($get) => $get('tipo_medida_id'))
                        ->rules([
                            fn(Forms\Get $get) => function ($attribute, $value, $fail) use ($get) {
                                $tipoId = $get('tipo_medida_id');
                                if (!$tipoId)
                                    return;

                                $tipo = TipoMedida::find($tipoId);
                                if (!$value) {
                                    $fail('Debe ingresar un valor de medida.');
                                    return;
                                }
                                if (str_contains(strtolower($tipo->nombre), 'colchón') && !preg_match('/^\d+(\.\d+)?\s*x\s*\d+(\.\d+)?$/', $value)) {
                                    $fail('Para tipo colchón, el valor debe ser del tipo "135 x 190".');
                                }
                            }
                        ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')->searchable(),
                TextColumn::make('puntos'),
                TextColumn::make('medidas.valor')
                    ->label('Medidas')
                    ->limitList(3)
                    ->badge()
                    ->default('-'),
            ])
            ->defaultSort('nombre', 'asc')
            ->paginationPageOptions([100])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(""),
                Tables\Actions\DeleteAction::make()->label(""),
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
            'index' => Pages\ListProductos::route('/'),
            'create' => Pages\CreateProducto::route('/create'),
            'edit' => Pages\EditProducto::route('/{record}/edit'),
        ];
    }
}
