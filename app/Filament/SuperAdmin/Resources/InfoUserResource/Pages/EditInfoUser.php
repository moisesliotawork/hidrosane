<?php

namespace App\Filament\SuperAdmin\Resources\InfoUserResource\Pages;

use App\Filament\SuperAdmin\Resources\InfoUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInfoUser extends EditRecord
{
    protected static string $resource = InfoUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
