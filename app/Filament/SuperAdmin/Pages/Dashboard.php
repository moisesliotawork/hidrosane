<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Filament\SuperAdmin\Resources\VentaResource;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationGroup = 'OTROS';

    protected static ?int $navigationSort = 101;

    public function mount(): void
    {
        $this->redirect(VentaResource::getUrl());
    }
}
