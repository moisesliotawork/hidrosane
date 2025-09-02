<?php
// app/Filament/Gerente/Resources/TeamResource/Pages/EditTeam.php

namespace App\Filament\Gerente\Resources\TeamResource\Pages;

use App\Filament\Gerente\Resources\TeamResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\{User, Team, Note};

class EditTeam extends EditRecord
{
    protected static string $resource = TeamResource::class;

    protected array $miembros = [];
    protected ?int $oldLeaderId = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('delete')
                ->label('Borrar')
                ->icon('heroicon-o-trash')
                ->action(fn() => $this->record->delete())
                ->requiresConfirmation()
                ->after(fn() => $this->redirect($this->getResource()::getUrl('index')))
                ->color('danger'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['miembros'] = $this->record->members->map(fn($u) => [
            'user_id' => $u->id,
        ])->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Guardamos miembros
        $this->miembros = $data['miembros'] ?? [];
        unset($data['miembros']);

        // Líder actual (antes del cambio)
        $this->oldLeaderId = $this->record->team_leader_id;

        // Si el líder CAMBIA, APAGAMOS show_phone del líder anterior ANTES de guardar
        if ($this->oldLeaderId && isset($data['team_leader_id']) && (int) $data['team_leader_id'] !== (int) $this->oldLeaderId) {
            Note::where('comercial_id', $this->oldLeaderId)
                // ->whereNull('venta_id') // ← si quieres excluir notas con venta
                ->update(['show_phone' => false]);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // 1) Sincronizar miembros
        $ids = collect($this->miembros)->pluck('user_id')->all();
        $this->record->members()->sync(
            collect($ids)->mapWithKeys(fn($id) => [
                $id => ['joined_at' => now(), 'is_active' => true],
            ])->toArray()
        );

        // 2) Roles del líder
        $newLeaderId = $this->record->team_leader_id;
        $newLeader = User::find($newLeaderId);

        if ($newLeader && !$newLeader->hasRole('team_leader')) {
            $newLeader->assignRole('team_leader');
        }

        if ($this->oldLeaderId && $this->oldLeaderId !== $newLeaderId) {
            $oldLeader = User::find($this->oldLeaderId);
            if (
                $oldLeader &&
                $oldLeader->hasRole('team_leader') &&
                !Team::where('team_leader_id', $oldLeader->id)->exists()
            ) {
                $oldLeader->removeRole('team_leader');
            }
        }

        // 3) ENCENDER show_phone SOLO para el NUEVO líder (después de guardar)
        if ($newLeader) {
            Note::where('comercial_id', $newLeader->id)
                // ->whereNull('venta_id') // ← si quieres excluir notas con venta
                ->update(['show_phone' => true]);
        }
    }
}
