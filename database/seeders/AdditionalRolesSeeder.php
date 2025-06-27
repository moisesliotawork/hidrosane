<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AdditionalRolesSeeder extends Seeder
{
    public function run()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::create(['name' => 'sales_manager']);
        Role::create(['name' => 'team_leader']);
        Role::create(['name' => 'delegate']);
        Role::create(['name' => 'delivery']);
        Role::create(['name' => 'app_support']);
    }
}