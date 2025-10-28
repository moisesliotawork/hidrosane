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
use Filament\Tables\Actions\Action;
use Illuminate\Support\Collection;

class ProductoResource extends Resource
{
    protected static ?string $model = Producto::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->activos();
    }

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

                                $tipo = TipoMedida::query()->firstWhere('id', $tipoId);
                                if (!$value) {
                                    $fail('Debe ingresar un valor de medida.');
                                    return;
                                }
                                if (str_contains(strtolower($tipo->nombre), 'colchón') && !preg_match('/^\d+(\.\d+)?\s*x\s*\d+(\.\d+)?$/', $value)) {
                                    $fail('Para tipo colchón, el valor debe ser del tipo "135 x 190".');
                                }
                            }
                        ]),

                    Forms\Components\Toggle::make('visible_for_commercials')
                        ->label('Visible para comerciales y jefes de equipo')
                        ->default(true)
                        ->required(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')->sortable()->searchable(),
                TextColumn::make('puntos'),
                TextColumn::make('medidas.valor')
                    ->label('Medidas')
                    ->limitList(3)
                    ->badge()
                    ->default('-'),
                TextColumn::make('visible_for_commercials')
                    ->label('Visible para comerciales')
                    ->badge()
                    ->color(fn(bool $state): string => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn(bool $state): string => $state ? 'Sí' : 'No')
                    ->sortable(),

            ])
            ->defaultSort('nombre', 'asc')
            ->paginationPageOptions([100])
            ->filters([
                Tables\Filters\SelectFilter::make('visible_for_commercials')
                    ->label('Visibilidad')
                    ->options([
                        '1' => 'Visible para comerciales',
                        '0' => 'No visible',
                    ])
                    ->native(false)
                    ->placeholder('Todos')
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['value'])) {
                            return $query;
                        }
                        return $query->where('visible_for_commercials', (bool) $data['value']);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(""),
                Action::make('eliminar')
                    ->label('')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar eliminación')
                    ->modalSubheading('¿Estás seguro de que deseas eliminar este producto?')
                    ->modalButton('Sí, eliminar')
                    ->action(function (Producto $record) {
                        $record->update(['delete' => true]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('eliminarSeleccionados')
                        ->label('Eliminar seleccionados')
                        ->icon('heroicon-m-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Confirmar eliminación múltiple')
                        ->modalSubheading('¿Seguro que quieres eliminar todos los productos seleccionados?')
                        ->modalButton('Sí, eliminar seleccionados')
                        ->action(function (Collection $records) {
                            $records->each->update(['delete' => true]);
                        }),
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
