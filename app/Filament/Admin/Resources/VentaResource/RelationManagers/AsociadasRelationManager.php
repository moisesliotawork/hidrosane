<?php

namespace App\Filament\Admin\Resources\VentaResource\RelationManagers;

use App\Filament\Admin\Resources\VentaResource;
use App\Models\Venta;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class AsociadasRelationManager extends RelationManager
{
    /** nombre de la relación en el modelo */
    protected static string $relationship = 'asociadas';

    protected static ?string $title = 'Contratos -B asociados';

    public function table(Table $table): Table
    {
        return $table
            // Solo mostrar las asociadas cuyo nro_contr_adm termina en "-B"
            ->modifyQueryUsing(fn($query) => $query->where('nro_contr_adm', 'like', '%-B'))
            ->recordTitleAttribute('nro_contr_adm')
            ->columns([
                TextColumn::make('nro_contr_adm')->label('Nº Contrato')->sortable()->searchable(),
                TextColumn::make('nro_cliente_adm')->label('Nº Cliente')->sortable(),
                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->getStateUsing(fn(Venta $r) => $r->customer?->first_names . ' ' . $r->customer?->last_names)
                    ->searchable(),
                TextColumn::make('fecha_venta')->label('Fecha venta')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('estado_venta')
                    ->badge()
                    ->color(fn(\App\Enums\EstadoVenta $state) => $state->color())
                    ->formatStateUsing(fn(\App\Enums\EstadoVenta $state) => $state->label())
                    ->label('Estado'),
            ])
            ->headerActions([]) // no crear desde aquí
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn(Venta $record) => VentaResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([])
            ->paginated(false); // mostrar todos los -B
    }
}
