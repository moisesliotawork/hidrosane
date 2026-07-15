<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\ClientesBorradosResource\Pages;
use App\Models\Customer;
use App\Models\Scopes\NotMergedScope;
use App\Support\CustomerSoftRestore;
use App\Support\Filament\ClientesBorradosTable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientesBorradosResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-x-mark';

    protected static ?string $navigationLabel = 'Clientes borrados';

    protected static ?string $modelLabel = 'Cliente borrado';

    protected static ?string $pluralModelLabel = 'Clientes borrados';

    protected static ?string $breadcrumb = 'Clientes borrados';

    protected static ?string $slug = 'clientes-borrados';

    protected static ?int $navigationSort = 95;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScope(NotMergedScope::class)
            ->onlyTrashed()
            ->with(['deletedBy']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('deleted_at', 'desc')
            ->columns(ClientesBorradosTable::columns())
            ->actions([
                Tables\Actions\RestoreAction::make()
                    ->label('Restaurar cliente')
                    ->modalHeading('Restaurar cliente')
                    ->modalDescription('El cliente volverá a aparecer en Posición Global y demás listados.')
                    ->modalSubmitActionLabel('Sí, restaurar')
                    ->successNotificationTitle('Cliente restaurado')
                    ->using(fn (Customer $record) => CustomerSoftRestore::restore($record)),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientesBorrados::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
