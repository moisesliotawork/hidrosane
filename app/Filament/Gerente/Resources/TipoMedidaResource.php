<?php

namespace App\Filament\Gerente\Resources;

use App\Filament\Gerente\Resources\TipoMedidaResource\Pages;
use App\Filament\Gerente\Resources\TipoMedidaResource\RelationManagers;
use App\Models\TipoMedida;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

class TipoMedidaResource extends Resource
{
    protected static ?string $model = TipoMedida::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationLabel = 'Tipos de medida';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(255),

            TextInput::make('unidad')
                ->label('Unidad (ej: cm)')
                ->required()
                ->maxLength(50),
        ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('unidad')
                    ->sortable(),
            ])
            ->defaultSort('nombre', 'asc')
            ->paginationPageOptions([50, 100])
            ->actions([
                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\DeleteAction::make()->label(''),
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
