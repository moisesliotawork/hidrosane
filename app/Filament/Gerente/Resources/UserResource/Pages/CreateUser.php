<?php

namespace App\Filament\Gerente\Resources\UserResource\Pages;

use App\Filament\Gerente\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\User;
use Filament\Notifications\Notification;
use Spatie\Permission\Models\Role;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function beforeCreate(): void
    {
        // Validar que el empleado_id tenga exactamente 3 cifras
        if (!preg_match('/^\d{3}$/', $this->data['empleado_id'])) {
            Notification::make()
                ->title('Error en ID de empleado')
                ->body('El ID de empleado debe tener exactamente 3 dígitos numéricos')
                ->danger()
                ->send();

            $this->halt();
        }

        // Verificar si el empleado_id ya existe
        if (User::where('empleado_id', $this->data['empleado_id'])->exists()) {
            Notification::make()
                ->title('ID de empleado en uso')
                ->body('El ID de empleado ya está registrado en el sistema')
                ->danger()
                ->send();

            $this->halt();
        }

        // Verificar si el email ya existe
        if (User::where('email', $this->data['email'])->exists()) {
            Notification::make()
                ->title('Email en uso')
                ->body('El correo electrónico ya está registrado en el sistema')
                ->danger()
                ->send();

            $this->halt();
        }
    }

    protected function afterCreate(): void
    {
        // Asignar el rol seleccionado
        $role = Role::findByName($this->data['role']);
        $this->record->assignRole($role);

        Notification::make()
            ->title('Usuario creado')
            ->body('El usuario se ha registrado exitosamente')
            ->success()
            ->send();
    }

}
