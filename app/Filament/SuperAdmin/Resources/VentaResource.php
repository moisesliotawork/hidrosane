<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Models\Venta;
use App\Filament\SuperAdmin\Resources\VentaResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Actions\DeleteBulkAction;

class VentaResource extends Resource
{
    protected static ?string $model = Venta::class;

    protected static ?string $navigationIcon   = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel  = 'Contratos';
    protected static ?string $modelLabel       = 'Contrato';
    protected static ?string $pluralModelLabel = 'Contratos';
    protected static ?string $breadcrumb       = 'Contratos';
    protected static ?string $slug             = 'ventas-admin';

    public static function form(Form $form): Form
    {
        return \App\Filament\Admin\Resources\VentaResource::form($form);
    }

    public static function table(Table $table): Table
    {
        $table = \App\Filament\Admin\Resources\VentaResource::table($table);

        return $table->bulkActions([
            DeleteBulkAction::make()
                ->label('Eliminar seleccionados')
                ->requiresConfirmation()
                ->modalHeading('Eliminar contratos')
                ->modalDescription('Se eliminarán los contratos seleccionados y sus notas asociadas. Esta acción no se puede deshacer.')
                ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                    foreach ($records as $venta) {
                        $noteId = $venta->note_id;
                        $venta->delete();
                        if ($noteId) {
                            \App\Models\Note::find($noteId)?->delete();
                        }
                    }
                }),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'    => Pages\ListVentas::route('/'),
            'edit'     => Pages\EditVenta::route('/{record}/edit'),
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
