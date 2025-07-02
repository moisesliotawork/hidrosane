<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;

class GeneralManagerRoleSeeder extends Seeder
{
    public function run()
    {
        try {
            // Limpiar caché de permisos
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            // Crear el rol (usando firstOrCreate para evitar duplicados)
            Role::firstOrCreate([
                'name' => 'gerente_general',
                'guard_name' => 'web' // Asegúrate de especificar el guard
            ]);

            Log::info('Rol gerente_general creado exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al crear rol gerente_general: ' . $e->getMessage());
        }
    }
}