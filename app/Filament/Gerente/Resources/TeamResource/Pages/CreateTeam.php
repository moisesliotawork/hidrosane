<?php

namespace App\Filament\Gerente\Resources\TeamResource\Pages;

use App\Filament\Gerente\Resources\TeamResource;
use Filament\Actions;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\CreateRecord;

class CreateTeam extends CreateRecord
{
    protected static string $resource = TeamResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->miembros = $data['miembros'] ?? []; // guarda temporalmente para usar después
        unset($data['miembros']); // importante: evita error por campo no existente en la tabla
        $data['sales_manager_id'] = Auth::id();
        return $data;
    }

    protected function afterCreate(): void
    {
        foreach ($this->miembros as $miembro) {
            $this->record->members()->attach($miembro['user_id'], [
                'joined_at' => now(),
                'is_active' => true,
            ]);
        }
    }

}
