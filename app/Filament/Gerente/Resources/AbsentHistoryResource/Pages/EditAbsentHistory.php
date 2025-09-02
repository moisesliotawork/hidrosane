<?php

namespace App\Filament\Gerente\Resources\AbsentHistoryResource\Pages;

use App\Filament\Gerente\Resources\AbsentHistoryResource;
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
