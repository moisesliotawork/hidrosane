<?php

namespace App\Filament\Admin\Resources\TipoMedidaResource\Pages;

use App\Filament\Admin\Resources\TipoMedidaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTipoMedida extends EditRecord
{
    protected static string $resource = TipoMedidaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
