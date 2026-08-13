<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\ContratosReservaResource\Pages;
use App\Models\Scopes\NotMergedScope;
use App\Models\Venta;
use App\Support\Filament\ContratosBorradosTable;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContratosReservaResource extends Resource
{
    protected static ?string $model = Venta::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'RESERVA';

    protected static ?string $modelLabel = 'Contrato en reserva';

    protected static ?string $pluralModelLabel = 'RESERVA';

    protected static ?string $breadcrumb = 'RESERVA';

    protected static ?string $slug = 'contratos-reserva';

    protected static ?string $navigationGroup = 'RECUPERACION CONTRATOS';

    protected static ?int $navigationSort = 95;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->onlyTrashed()
            ->enReserva()
            ->with([
                'customer' => fn ($query) => $query
                    ->withoutGlobalScope(NotMergedScope::class)
                    ->withTrashed(),
                'deletedBy',
                'reservadoBy',
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::onlyTrashed()->enReserva()->count();
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return static::getModel()::onlyTrashed()->enReserva()->count() > 0 ? 'warning' : 'success';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('reservado_at', 'desc')
            ->columns(ContratosBorradosTable::columns(reserva: true))
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContratosReserva::route('/'),
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

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }
}
