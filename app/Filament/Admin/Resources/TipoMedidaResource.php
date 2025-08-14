<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TipoMedidaResource\Pages;
use App\Models\TipoMedida;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

class TipoMedidaResource extends Resource
{
    protected static ?string $model = TipoMedida::class;

    protected static ?string $pluralModelLabel = 'Productos con Medida';
    protected static ?string $modelLabel = 'Producto con Medida';

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255)
                    ->label('Nombre'),

                TextInput::make('unidad')
                    ->required()
                    ->maxLength(50)
                    ->label('Unidad (ej: cm)'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')->searchable()->sortable(),
                TextColumn::make('unidad')->sortable(),
            ])
            ->defaultSort('nombre', 'asc')
            ->paginationPageOptions([50, 100])
            ->actions([
                Tables\Actions\EditAction::make()->label(""),
                Tables\Actions\DeleteAction::make()->label(""),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTipoMedidas::route('/'),
            'create' => Pages\CreateTipoMedida::route('/create'),
            'edit' => Pages\EditTipoMedida::route('/{record}/edit'),
        ];
    }
}
