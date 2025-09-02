<?php

namespace App\Filament\Admin\Resources\AbsentHistoryResource\Pages;

use App\Filament\Admin\Resources\AbsentHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAbsentHistories extends ListRecords
{
    protected static string $resource = AbsentHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
