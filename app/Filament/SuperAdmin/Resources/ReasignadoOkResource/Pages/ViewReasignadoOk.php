<?php

namespace App\Filament\SuperAdmin\Resources\ReasignadoOkResource\Pages;

use App\Filament\SuperAdmin\Resources\ReasignadoOkResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;

class ViewReasignadoOk extends ViewRecord
{
    protected static string $resource = ReasignadoOkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('volver')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->url(ReasignadoOkResource::getUrl('index'))
                ->color('gray'),
        ];
    }
}
