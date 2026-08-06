<?php

namespace App\Filament\SuperAdmin\Resources\ListaAmanoResource\Pages;

use App\Filament\SuperAdmin\Resources\ListaAmanoResource;
use App\Models\ListaAmano;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditListaAmano extends EditRecord
{
    protected static string $resource = ListaAmanoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
