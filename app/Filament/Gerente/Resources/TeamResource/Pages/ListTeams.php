<?php

namespace App\Filament\Gerente\Resources\TeamResource\Pages;

use App\Filament\Gerente\Resources\TeamResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTeams extends ListRecords
{
    protected static string $resource = TeamResource::class;

    protected function getTableQuery(): Builder
    {
        // parte de la query original de Filament + nuestro where
        return parent::getTableQuery()->where('deleted', false);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
