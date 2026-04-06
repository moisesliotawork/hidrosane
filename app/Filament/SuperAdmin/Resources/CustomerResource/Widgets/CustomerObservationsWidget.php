<?php

namespace App\Filament\SuperAdmin\Resources\CustomerResource\Widgets;

use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use App\Models\{Customer, CustomerObservation};

class CustomerObservationsWidget extends BaseWidget
{
    protected static ?string $heading = 'OBSERVACIONES DEL CLIENTE';
    protected int|string|array $columnSpan = 'full';

    public ?Customer $record = null;

    protected function getTableQuery(): Builder
    {
        return CustomerObservation::query()
            ->with('author')
            ->where('customer_id', $this->record?->id)
            ->latest('created_at');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('created_at')
                ->label('Fecha')
                ->dateTime('d/m/Y H:i')
                ->sortable(),

            TextColumn::make('author.name')
                ->label('Autor'),

            TextColumn::make('observation')
                ->label('Observación')
                ->wrap()
                ->columnSpan(3),
        ];
    }

    protected function isTablePaginationEnabled(): bool
    {
        return true;
    }
}
