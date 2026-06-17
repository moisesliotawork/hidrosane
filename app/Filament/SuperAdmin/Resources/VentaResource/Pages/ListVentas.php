<?php

namespace App\Filament\SuperAdmin\Resources\VentaResource\Pages;

use App\Filament\Admin\Resources\VentaResource\Pages\ListVentas as BaseListVentas;
use App\Filament\SuperAdmin\Resources\VentaResource;

class ListVentas extends BaseListVentas
{
    protected static string $resource = VentaResource::class;
}
