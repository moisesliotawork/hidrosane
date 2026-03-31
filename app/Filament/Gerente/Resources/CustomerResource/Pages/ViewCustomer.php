<?php

namespace App\Filament\Gerente\Resources\CustomerResource\Pages;

use App\Filament\Gerente\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Gerente\Resources\CustomerResource\Widgets\CustomerNotesTable;
use App\Filament\Gerente\Resources\CustomerResource\Widgets\CustomerSalesTable;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    public function getTitle(): string
    {
        return 'Posición Global del Cliente';
    }

    protected function getFooterWidgets(): array
    {
        return [
            CustomerNotesTable::class,
            CustomerSalesTable::class,
        ];
    }
}
