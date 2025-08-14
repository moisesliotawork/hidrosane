<?php

namespace App\Filament\Gerente\Resources\UserResource\Pages;

use App\Filament\Gerente\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterSave(): void
    {
        // Sincronizar el rol (remover todos y asignar el nuevo)
        $role = Role::findByName($this->data['role']);
        $this->record->syncRoles([$role]);
    }

    protected function beforeSave(): void
    {
        // Actualizar la contraseña si se proporcionó una nueva
        if (!empty($this->data['new_password'])) {
            $this->record->password = Hash::make($this->data['new_password']);
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {

        // Obtener el primer rol del usuario y asignarlo al campo 'role'
        $data['role'] = $this->record->roles->first()?->name;
        $data['new_password'] = '';

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
