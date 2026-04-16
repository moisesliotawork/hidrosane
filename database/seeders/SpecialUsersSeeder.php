<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class SpecialUsersSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::query()
            ->orderBy('name')
            ->get();

        if ($roles->isEmpty()) {
            $this->command?->warn('No hay roles registrados. Ejecuta primero los seeders de roles.');

            return;
        }

        $usedEmployeeIds = User::query()
            ->whereNotNull('empleado_id')
            ->pluck('empleado_id')
            ->map(fn ($id) => str_pad((string) $id, 3, '0', STR_PAD_LEFT))
            ->all();

        $nextEmployeeId = 900;

        foreach ($roles as $role) {
            $roleSlug = Str::slug($role->name);

            for ($i = 1; $i <= 2; $i++) {
                $email = "{$roleSlug}{$i}@example.com";

                $user = User::firstOrNew(['email' => $email]);

                if (! $user->exists || blank($user->empleado_id)) {
                    $user->empleado_id = $this->nextAvailableEmployeeId($usedEmployeeIds, $nextEmployeeId);
                }

                $user->fill([
                    'name' => 'Especial ' . Str::headline($role->name),
                    'last_name' => "Usuario {$i}",
                    'phone' => null,
                    'password' => Hash::make('123456'),
                    'is_active' => true,
                ]);

                $user->email_verified_at = now();
                $user->save();
                $user->syncRoles([$role->name]);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function nextAvailableEmployeeId(array &$usedEmployeeIds, int &$nextEmployeeId): string
    {
        while (in_array((string) $nextEmployeeId, $usedEmployeeIds, true)) {
            $nextEmployeeId++;
        }

        $employeeId = (string) $nextEmployeeId;
        $usedEmployeeIds[] = $employeeId;
        $nextEmployeeId++;

        return $employeeId;
    }
}
