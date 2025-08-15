<?php

namespace App\Filament\Admin\Resources\CustomerResource\Pages;

use App\Filament\Admin\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Admin\Resources\CustomerResource\Widgets\CustomerNotesTable;
use App\Filament\Admin\Resources\CustomerResource\Widgets\CustomerSalesTable;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    public function getTitle(): string
    {
        return 'Vision Global del Cliente';
    }

    protected function getFooterWidgets(): array
    {
        return [
            CustomerNotesTable::class,
            CustomerSalesTable::class,
        ];
    }
}
