<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\ClientesBorradosResource\Pages;
use App\Models\Customer;
use App\Models\Scopes\NotMergedScope;
use App\Support\Filament\ClientesBorradosTable;
use Filament\Resources\Resource;
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

    protected static ?string $navigationGroup = 'RECUPERACION CONTRATOS';

    protected static ?int $navigationSort = 95;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScope(NotMergedScope::class)
            ->onlyTrashed()
            ->with(['deletedBy']);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::withoutGlobalScope(NotMergedScope::class)->onlyTrashed()->count();
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return static::getModel()::withoutGlobalScope(NotMergedScope::class)->onlyTrashed()->count() > 0
            ? 'danger'
            : 'success';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('deleted_at', 'desc')
            ->columns(ClientesBorradosTable::columns())
            ->actions([])
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
