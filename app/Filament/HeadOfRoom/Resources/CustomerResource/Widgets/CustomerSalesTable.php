<?php

namespace App\Filament\HeadOfRoom\Resources\CustomerResource\Widgets;

use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use App\Models\{Customer, Venta};

class CustomerSalesTable extends BaseWidget
{
    protected static ?string $heading = 'Ventas del Cliente';
    protected int|string|array $columnSpan = 'full';

    /** Filament inyecta el registro actual del ViewRecord */
    public ?Customer $record = null;

    protected function getTableQuery(): Builder
    {
        return Venta::query()
            ->with(['note', 'comercial'])
            ->where('customer_id', $this->record?->id)
            ->latest('fecha_venta');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('nro_contrato')
                ->label('Contrato')
                ->searchable()
                ->sortable()
                ->toggleable(),

            TextColumn::make('note.nro_nota')
                ->label('# Nota')
                ->formatStateUsing(fn($state) => $state && strlen($state) === 5
                    ? substr($state, 0, 3) . ' ' . substr($state, 3, 2)
                    : $state)
                ->sortable(),

            TextColumn::make('fecha_venta')
                ->label('F. Venta')
                ->date('d/m/Y')
                ->sortable(),

            TextColumn::make('importe_total')
                ->label('Importe')
                ->money('EUR', true) // cambia la moneda si corresponde
                ->sortable(),

            TextColumn::make('modalidad_pago')
                ->label('Modalidad')
                ->badge()
                ->toggleable(),

            TextColumn::make('estado_venta_label')
                ->label('Estado')
                ->badge()
                ->color(fn(Venta $r) => $r->estado_venta_color ?? 'gray')
                ->sortable(),

            TextColumn::make('fecha_entrega')
                ->label('F. Entrega')
                ->date('d/m/Y')
                ->sortable()
                ->toggleable(),

            TextColumn::make('horario_entrega')
                ->label('Horario')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('comercial.empleado_id')
                ->label('Com.')
                ->badge()
                ->color('success')
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    //protected function getTableActions(): array
    //{
    //    return [
    //        Tables\Actions\Action::make('editar')
    //            ->label('Editar')
    //            ->icon('heroicon-o-pencil-square')
    //            ->url(fn(Venta $record) => VentaResource::getUrl('edit', ['record' => $record]))
    //            ->visible(fn() => class_exists(VentaResource::class))
    //            ->openUrlInNewTab(),
//
    //        Tables\Actions\Action::make('contrato')
    //            ->label('Contrato')
    //            ->icon('heroicon-o-document-text')
    //            ->url(fn(Venta $record) => $record->contrato_firmado_url)
    //            ->visible(fn(Venta $record) => filled($record->contrato_firmado_url))
    //            ->openUrlInNewTab(),
    //    ];
    //}

    protected function isTablePaginationEnabled(): bool
    {
        return true;
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'Sin ventas registradas';
    }
}
