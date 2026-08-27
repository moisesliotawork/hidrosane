<?php

namespace App\Filament\SuperAdmin\Resources\ListaAmanoResource\Pages;

use App\Filament\SuperAdmin\Resources\ListaAmanoResource;
use App\Models\ListaAmano;
use Filament\Resources\Pages\CreateRecord;

class CreateListaAmano extends CreateRecord
{
    protected static string $resource = ListaAmanoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (filled($data['mes_codigo'] ?? null)) {
            $parsed = ListaAmano::parseMesCodigo((string) $data['mes_codigo']);
            if ($parsed !== null) {
                $data['mes'] = $parsed['mes'];
                $data['anio'] = $parsed['anio'];
                $data['mes_codigo'] = $parsed['codigo'];
            }
        }

        return $data;
    }
}
