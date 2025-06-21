<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Crear usuario admin
        $admin = User::create([
            'name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => Hash::make('123456'),
            'phone' => '123456789'
        ]);
        $admin->assignRole('admin');

        // Crear jefe de sala
        $headOfRoom = User::create([
            'name' => 'Jefe',
            'last_name' => 'de Sala',
            'email' => 'jefesala@example.com',
            'password' => Hash::make('123456'),
            'phone' => '987654321'
        ]);
        $headOfRoom->assignRole('head_of_room');

        // Crear teleoperador
        $teleoperator = User::create([
            'name' => 'Teleoperador',
            'last_name' => 'User',
            'email' => 'teleoperador@example.com',
            'password' => Hash::make('123456'),
            'phone' => '555555555'
        ]);
        $teleoperator->assignRole('teleoperator');

        // Crear comercial
        $commercial = User::create([
            'name' => 'Comercial',
            'last_name' => 'User',
            'email' => 'comercial@example.com',
            'password' => Hash::make('123456'),
            'phone' => '111111111'
        ]);
        $commercial->assignRole('commercial');
    }
}