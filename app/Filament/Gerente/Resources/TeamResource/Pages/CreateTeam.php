<?php

namespace App\Filament\Gerente\Resources\TeamResource\Pages;

use App\Filament\Gerente\Resources\TeamResource;
use Filament\Actions;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\CreateRecord;
use App\Models\User;
use App\Models\Note;

class CreateTeam extends CreateRecord
{
    protected static string $resource = TeamResource::class;

    protected array $miembros = [];

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->miembros = $data['miembros'] ?? [];
        unset($data['miembros']);
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

        // Asegurar rol del líder (opcional, como ya lo tenías)
        $leader = User::find($this->record->team_leader_id);
        if ($leader && !$leader->hasRole('team_leader')) {
            $leader->assignRole('team_leader');
        }

        // ✅ Habilitar teléfonos SOLO para el líder
        if ($leader) {
            Note::where('comercial_id', $leader->id)
                // ->whereNull('venta_id') // ← opcional, si quieres excluir notas con venta
                ->update(['show_phone' => true]);
        }
    }

}
