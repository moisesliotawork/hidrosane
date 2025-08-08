<?php
// app/Filament/Gerente/Resources/TeamResource/Pages/EditTeam.php

namespace App\Filament\Gerente\Resources\TeamResource\Pages;

use App\Filament\Gerente\Resources\TeamResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\{User, Team};

class EditTeam extends EditRecord
{
    protected static string $resource = TeamResource::class;

    /** Miembros que vienen del formulario */
    protected array $miembros = [];

    /** Para saber si cambió el líder */
    protected ?int $oldLeaderId = null;

    /*───────────────── Actions de cabecera ─────────────────*/
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('delete')
                ->label('Borrar')
                ->icon('heroicon-o-trash')
                ->action(fn() => $this->record->delete())
                ->requiresConfirmation()
                ->after(function (): void {
                    // Redirige al index después del borrado lógico
                    $this->redirect($this->getResource()::getUrl('index'));
                })
                ->color('danger'),
        ];
    }

    /*───────────────── Poblar el formulario ────────────────*/
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['miembros'] = $this->record->members->map(fn($u) => [
            'user_id' => $u->id,
        ])->toArray();

        return $data;
    }

    /*───────────────── Antes de guardar ────────────────────*/
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->miembros = $data['miembros'] ?? [];
        unset($data['miembros']);

        $this->oldLeaderId = $this->record->team_leader_id;   // líder previo
        return $data;
    }

    /*───────────────── Después de guardar ──────────────────*/
    protected function afterSave(): void
    {
        /* 1 ▸ Sincronizar miembros en la tabla pivote ---------- */
        $ids = collect($this->miembros)->pluck('user_id')->all();

        // syncWithoutDetaching + detach de los que salieron
        $this->record->members()->sync(
            collect($ids)->mapWithKeys(fn($id) => [
                $id => [
                    'joined_at' => now(),
                    'is_active' => true,
                ]
            ])->toArray()
        );

        /* 2 ▸ Gestionar el rol team_leader --------------------- */
        $newLeaderId = $this->record->team_leader_id;

        // (a) Asignar el rol al nuevo líder si no lo tiene
        $newLeader = User::find($newLeaderId);
        if ($newLeader && !$newLeader->hasRole('team_leader')) {
            $newLeader->assignRole('team_leader');
        }

        // (b) Si cambió el líder, retirar el rol al anterior
        if ($this->oldLeaderId && $this->oldLeaderId !== $newLeaderId) {
            $oldLeader = User::find($this->oldLeaderId);

            if (
                $oldLeader &&
                $oldLeader->hasRole('team_leader') &&
                !Team::where('team_leader_id', $oldLeader->id)->exists()  // ya no lidera ningún equipo
            ) {
                $oldLeader->removeRole('team_leader');
            }
        }
    }
}
