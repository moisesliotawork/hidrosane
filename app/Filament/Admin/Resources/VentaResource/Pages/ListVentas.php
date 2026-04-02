<?php

namespace App\Filament\Admin\Resources\VentaResource\Pages;

use App\Filament\Admin\Resources\VentaResource;
use Closure;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;

class ListVentas extends ListRecords
{
    protected static string $resource = VentaResource::class;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function getTableRecordUrlUsing(): ?Closure
    {
        return fn (Model $record): string => VentaResource::getUrl('edit', ['record' => $record]);
    }

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
