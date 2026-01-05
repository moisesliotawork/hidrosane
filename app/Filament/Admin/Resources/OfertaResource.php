<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OfertaResource\Pages;
use App\Filament\Admin\Resources\OfertaResource\RelationManagers;
use App\Models\Oferta;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Notifications\Notification;

class OfertaResource extends Resource
{
    protected static ?string $model = Oferta::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Ofertas';
    protected static ?string $modelLabel = 'Oferta';
    protected static ?string $pluralModelLabel = 'Ofertas';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la oferta')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('nombre')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Toggle::make('visible')
                            ->label('Visible en la app')
                            ->default(true)
                            ->inline(false),

                        Forms\Components\TextInput::make('puntos_base')
                            ->numeric()
                            ->required()
                            ->minValue(0),

                        Forms\Components\TextInput::make('precio_base')
                            ->numeric()
                            ->required()
                            ->prefix('$')
                            ->minValue(0),

                        Forms\Components\Textarea::make('descripcion')
                            ->columnSpanFull()
                            ->rows(4),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->formatStateUsing(fn(string $state) => str_replace(' - ', "\n", $state))
                    ->wrap(),


                Tables\Columns\IconColumn::make('visible')
                    ->label('Visible')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('puntos_base')
                    ->label('Pts')
                    ->sortable(),

                Tables\Columns\TextColumn::make('precio_base')
                    ->label('Precio')
                    ->money('EUR') // cambia moneda si aplica
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('visible')
                    ->label('Visibilidad')
                    ->trueLabel('Solo visibles')
                    ->falseLabel('Solo no visibles')
                    ->placeholder('Todos'),

                // Este filtro habilita vistas: Activos / Eliminados / Todos
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(""),

                Tables\Actions\DeleteAction::make()
                    ->label(""),

                // Para poder restaurar si te equivocas
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([

                    Tables\Actions\BulkAction::make('marcar_visible')
                        ->label('Marcar visibles')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            // Solo actualiza los que realmente cambian
                            $updated = Oferta::query()
                                ->whereIn('id', $records->pluck('id'))
                                ->where('visible', false)
                                ->update(['visible' => true]);

                            Notification::make()
                                ->title('Ofertas actualizadas')
                                ->body("Se marcaron como VISIBLES: {$updated}.")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('marcar_no_visible')
                        ->label('Marcar NO visibles')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $updated = Oferta::query()
                                ->whereIn('id', $records->pluck('id'))
                                ->where('visible', true)
                                ->update(['visible' => false]);

                            Notification::make()
                                ->title('Ofertas actualizadas')
                                ->body("Se marcaron como NO visibles: {$updated}.")
                                ->warning()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])

            ->defaultSort('id', 'desc');
    }

    /**
     * Importantísimo para que Filament no aplique el SoftDeletingScope
     * y el TrashedFilter funcione correctamente.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOfertas::route('/'),
            'create' => Pages\CreateOferta::route('/create'),
            'edit' => Pages\EditOferta::route('/{record}/edit'),
        ];
    }
}
