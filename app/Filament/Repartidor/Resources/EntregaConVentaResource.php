<?php

namespace App\Filament\Repartidor\Resources;

use App\Filament\Repartidor\Resources\EntregaConVentaResource\Pages;
use App\Filament\Repartidor\Resources\EntregaConVentaResource\RelationManagers;
use App\Models\Venta;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EntregaConVentaResource extends Resource
{
    protected static ?string $model = Venta::class;
    protected static ?string $navigationLabel = 'Entregas con Venta';
    protected static ?string $pluralModelLabel = 'Entregas con Venta';
    protected static ?string $modelLabel = 'Entrega con Venta';
    protected static ?string $breadcrumb = 'Entregas con Venta';
    protected static ?string $navigationIcon = 'heroicon-o-document-check';

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
            'index' => Pages\ListEntregaConVentas::route('/'),
            'edit' => Pages\EditEntregaConVenta::route('/{record}/edit'),
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

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
