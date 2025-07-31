<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            //  nombre          apellido      email                           rol
            ['Gerente',        'Ventas',      'gerente.ventas@example.com',    'sales_manager'],
            ['Líder',          'Equipo',      'lider.equipo@example.com',      'team_leader'],
            ['Delegado',       'Comercial',   'delegado.comercial@example.com','delegate'],
            ['Repartidor',     'Logística',   'repartidor@example.com',        'delivery'],
            ['Soporte',        'Aplicación',  'soporte.app@example.com',       'app_support'],
            ['Administrador',  'General',     'admin@example.com',             'admin'],
            ['Jefe',           'Sala',        'jefesala@example.com',         'head_of_room'],
            ['Teleoperador',   'CallCenter',  'teleoperador@example.com',      'teleoperator'],
            ['Comercial',      'Ventas',      'comercial@example.com',         'commercial'],
        ];

        foreach ($usuarios as $i => [$nombre, $apellido, $email, $rol]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'        => $nombre,
                    'last_name'   => $apellido,
                    'phone'       => '6000000' . ($i + 1),
                    'empleado_id' => 101 + $i,
                    'password'    => Hash::make('123456'),
                ]
            );

            $user->syncRoles($rol);
        }
    }
}
