<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Deja en Hidrosane los mismos números de usuario que en Ohana:
 * 00 superadmin, 001 gerente, 002 Abby (admin) y 004 Alba (comercial).
 *
 * El botón Ohana → Hidrosane entra en los tres primeros (config/demo.php).
 * Solo cambia la contraseña cuando la ficha trae clave (Alba: 1079).
 */
class UsuariosOperativosSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('demo.operativos', []) as $ficha) {
            $this->asegurar($ficha);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function asegurar(array $ficha): void
    {
        $email = trim((string) $ficha['email']);
        $numero = trim((string) $ficha['empleado_id']);
        $rol = (string) $ficha['rol'];

        Role::findOrCreate($rol, 'web');

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            foreach ((array) ($ficha['emails_anteriores'] ?? []) as $anterior) {
                $anterior = trim((string) $anterior);
                if ($anterior === '') {
                    continue;
                }
                $user = User::query()->where('email', $anterior)->first();
                if ($user) {
                    break;
                }
            }
        }

        $clave = trim((string) ($ficha['clave'] ?? ''));

        if (! $user) {
            $user = new User([
                'name' => $ficha['name'],
                'last_name' => '',
                'email' => $email,
                'password' => $clave !== '' ? $clave : Hash::make(Str::random(40)),
                'phone' => '000000000',
            ]);
            if ($clave === '') {
                $this->command?->warn("{$email} no estaba. Se crea sin clave usable: hay que asignarle una o entrar por el botón de Ohana.");
            }
        }

        $user->email = $email;
        if ($clave !== '') {
            $user->password = $clave;
            if (Schema::hasColumn('users', 'clave')) {
                $user->clave = $clave;
            }
        }

        $ocupante = User::query()
            ->where('empleado_id', $numero)
            ->when($user->exists, fn ($q) => $q->where('id', '!=', $user->id))
            ->first();

        if ($ocupante) {
            $ocupante->empleado_id = null;
            $ocupante->save();
            $this->command?->warn("El número {$numero} estaba en {$ocupante->email}. Se lo quito para dejárselo a {$email}.");
        }

        $user->empleado_id = $numero;
        $user->can_login = true;
        $user->save();
        $user->assignRole($rol);

        $this->command?->info("{$numero} {$user->name} ({$email}) → {$rol}");
    }
}
