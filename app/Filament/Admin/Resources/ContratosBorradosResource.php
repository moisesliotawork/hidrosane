<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ContratosBorradosResource\Pages;
use App\Models\Venta;
use App\Support\VentaSoftRestore;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->onlyTrashed()
            ->with(['customer', 'deletedBy']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('deleted_at', 'desc')
            ->columns([
                TextColumn::make('deleted_at')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('deleted_at_time')
                    ->label('Hora')
                    ->state(fn (Venta $record): string => optional($record->deleted_at)?->format('H:i') ?? '—'),

                TextColumn::make('deletedBy.display_name')
                    ->label('Usuario')
                    ->state(fn (Venta $record): string => $record->deletedBy?->display_name ?? '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('deletedBy', function (Builder $userQuery) use ($search) {
                            $userQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('empleado_id', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable(['first_names', 'last_names'])
                    ->sortable(),

                TextColumn::make('nro_contr_adm')
                    ->label('Nº Contrato')
                    ->badge()
                    ->color('danger')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.dni')
                    ->label('DNI')
                    ->searchable()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\RestoreAction::make()
                    ->label('Restaurar contrato')
                    ->modalHeading('Restaurar contrato')
                    ->modalDescription('El contrato volverá a aparecer en Contratos.')
                    ->modalSubmitActionLabel('Sí, restaurar')
                    ->successNotificationTitle('Contrato restaurado')
                    ->using(fn (Venta $record) => VentaSoftRestore::restore($record)),
            ])
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
