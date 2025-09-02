<?php

namespace App\Filament\SuperAdmin\Resources\AbsentHistoryResource\Pages;

use App\Filament\SuperAdmin\Resources\AbsentHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAbsentHistory extends EditRecord
{
    protected static string $resource = AbsentHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
