<?php

namespace App\Filament\Gerente\Resources\VentaDesdeCeroResource\Pages;

use App\Filament\Gerente\Resources\VentaDesdeCeroResource;
use Filament\Resources\Pages\ListRecords;

class ListVentaDesdeCero extends ListRecords
{
    protected static string $resource = VentaDesdeCeroResource::class;

    protected function getHeaderActions(): array
    {
        return []; // sin acciones
    }

    public function mount(): void
    {
        // Redirige inmediatamente al Create
        $this->redirect(VentaDesdeCeroResource::getUrl('create'));
        // Si tu Livewire no tiene ->redirect(), usa:
        // redirect()->to(VentaDesdeCeroResource::getUrl('create'));
    }
}
