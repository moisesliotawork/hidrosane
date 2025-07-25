<?php

namespace App\Filament\Gerente\Resources\TeamResource\Pages;

use App\Filament\Gerente\Resources\TeamResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTeam extends EditRecord
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['miembros'] = $this->record->members->map(function ($user) {
            return [
                'user_id' => $user->id,
            ];
        })->toArray();

        return $data;
    }

}
