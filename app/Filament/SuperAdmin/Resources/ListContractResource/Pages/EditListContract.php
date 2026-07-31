<?php

namespace App\Filament\SuperAdmin\Resources\ListContractResource\Pages;

use App\Filament\SuperAdmin\Resources\ListContractResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditListContract extends EditRecord
{
    protected static string $resource = ListContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Volver')
                ->url(ListContractResource::getUrl('index'))
                ->color('gray'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
