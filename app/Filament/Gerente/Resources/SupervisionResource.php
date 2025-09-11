<?php

namespace App\Filament\Gerente\Resources;

use App\Filament\Gerente\Resources\SupervisionResource\Pages;
use App\Filament\Gerente\Resources\SupervisionResource\RelationManagers;
use App\Models\Supervision;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SupervisionResource extends Resource
{
    protected static ?string $model = Supervision::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Supervisiones';
    protected static ?string $pluralModelLabel = 'Supervisiones';
    protected static ?string $modelLabel = 'Supervisión';
    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),

                Tables\Columns\TextColumn::make('supervisor.empleado_id')
                    ->label('Supervisor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('supervisado.empleado_id')
                    ->label('Supervisado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('author.name')
                    ->label('Creado por')
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListSupervisions::route('/'),
            'create' => Pages\CreateSupervision::route('/create'),
            'edit' => Pages\EditSupervision::route('/{record}/edit'),
        ];
    }
}
