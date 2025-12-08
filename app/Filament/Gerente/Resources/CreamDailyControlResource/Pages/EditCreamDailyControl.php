<?php

namespace App\Filament\Gerente\Resources\CreamDailyControlResource\Pages;

use App\Filament\Gerente\Resources\CreamDailyControlResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCreamDailyControl extends EditRecord
{
    protected static string $resource = CreamDailyControlResource::class;

    protected function getHeaderActions(): array
    {
        return [
          
        ];
    }
}
