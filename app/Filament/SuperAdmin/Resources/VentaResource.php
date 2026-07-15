<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\Admin\Resources\VentaResource as AdminVentaResource;
use App\Filament\Admin\Resources\VentaResource\RelationManagers\AsociadasRelationManager;
use App\Filament\Support\SuperAdminVentaCustomerId;
use App\Filament\SuperAdmin\Resources\VentaResource\Pages;
use App\Models\Venta;
use App\Support\Filament\VentaSoftDeleteTableAction;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VentaResource extends Resource
{
    protected static ?string $model = Venta::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Contratos';
    protected static ?string $modelLabel = 'Contrato';
    protected static ?string $pluralModelLabel = 'Contratos';
    protected static ?string $breadcrumb = 'Contratos';
    protected static ?string $slug = 'ventas-admin';

    public static function form(Form $form): Form
    {
        return AdminVentaResource::form($form);
    }

    public static function table(Table $table): Table
    {
        $table = AdminVentaResource::table($table);

        return $table
            ->columns([
                SuperAdminVentaCustomerId::tableColumn(),
                ...array_values($table->getColumns()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                VentaSoftDeleteTableAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AsociadasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVentas::route('/'),
            'edit' => Pages\EditVenta::route('/{record}/edit'),
            'create-b' => Pages\CreateContratoBPage::route('/{record}/create-b'),
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
}
