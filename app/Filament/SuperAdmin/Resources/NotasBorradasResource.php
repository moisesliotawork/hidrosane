<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\NotasBorradasResource\Pages;
use App\Models\Note;
use App\Models\Scopes\NotMergedScope;
use App\Support\Filament\NotasBorradasTable;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NotasBorradasResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-x-mark';

    protected static ?string $navigationLabel = 'Notas borradas';

    protected static ?string $modelLabel = 'Nota borrada';

    protected static ?string $pluralModelLabel = 'Notas borradas';

    protected static ?string $breadcrumb = 'Notas borradas';

    protected static ?string $slug = 'notas-borradas';

    protected static ?string $navigationGroup = 'RECUPERACION CONTRATOS';

    protected static ?int $navigationSort = 93;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->onlyTrashed()
            ->with([
                'customer' => fn ($query) => $query
                    ->withoutGlobalScope(NotMergedScope::class)
                    ->withTrashed(),
                'user',
                'comercial',
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
            ->columns(NotasBorradasTable::columns())
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotasBorradas::route('/'),
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
