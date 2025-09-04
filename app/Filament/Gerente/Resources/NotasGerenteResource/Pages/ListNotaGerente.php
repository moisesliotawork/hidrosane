<?php

namespace App\Filament\Gerente\Resources\NotasGerenteResource\Pages;

use App\Filament\Gerente\Resources\NotasGerenteResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Gerente\Pages\ComercialesVerNotas;

class ListNotaGerente extends ListRecords
{
    protected static string $resource = NotasGerenteResource::class;

    public function mount(): void
    {
        // Redirige siempre al listado de comerciales del panel GERENTE
        $this->redirect(
            ComercialesVerNotas::getUrl(panel: 'gerente')
        );
    }

}
