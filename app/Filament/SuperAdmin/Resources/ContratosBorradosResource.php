<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\ContratosBorradosResource\Pages;
use App\Models\Scopes\NotMergedScope;
use App\Models\Venta;
use App\Support\Filament\ContratosBorradosTable;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContratosBorradosResource extends Resource
{
    protected static ?string $model = Venta::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-x-mark';

    protected static ?string $navigationLabel = 'Contratos borrados';

    protected static ?string $modelLabel = 'Contrato borrado';

    protected static ?string $pluralModelLabel = 'Contratos borrados';

    protected static ?string $breadcrumb = 'Contratos borrados';

    protected static ?string $slug = 'contratos-borrados';

    protected static ?string $navigationGroup = 'RECUPERACION CONTRATOS';

    protected static ?int $navigationSort = 94;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->onlyTrashed()
            ->with([
                'customer' => fn ($query) => $query
                    ->withoutGlobalScope(NotMergedScope::class)
                    ->withTrashed(),
                'deletedBy',
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::onlyTrashed()->count();
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return static::getModel()::onlyTrashed()->count() > 0 ? 'danger' : 'success';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('deleted_at', 'desc')
            ->columns(ContratosBorradosTable::columns())
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContratosBorrados::route('/'),
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
