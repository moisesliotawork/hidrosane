<?php

namespace App\Filament\SuperAdmin\Resources\TeamResource\Pages;

use App\Filament\SuperAdmin\Resources\TeamResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class CreateTeam extends CreateRecord
{
    protected static string $resource = TeamResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

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

        $leader = User::find($this->record->team_leader_id);

        if ($leader && !$leader->hasRole('team_leader')) {
            $leader->assignRole('team_leader');
        }
    }
}
