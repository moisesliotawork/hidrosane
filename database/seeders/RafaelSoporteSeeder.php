<?php

namespace Database\Seeders;

use App\Enums\FuenteNotas;
use App\Enums\NoteStatus;
use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RafaelSoporteSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'commercial', 'guard_name' => 'web']);

        $usedEmployeeIds = User::query()
            ->whereNotNull('empleado_id')
            ->pluck('empleado_id')
            ->map(fn ($id) => str_pad((string) $id, 3, '0', STR_PAD_LEFT))
            ->all();

        $user = User::firstOrNew(['email' => 'rafa@gmail.com']);

        if (! $user->exists || blank($user->empleado_id)) {
            $employeeId = '988';
            while (in_array($employeeId, $usedEmployeeIds, true)) {
                $employeeId = str_pad((string) (((int) $employeeId) + 1), 3, '0', STR_PAD_LEFT);
            }
            $user->empleado_id = $employeeId;
        }

        $user->fill([
            'name' => 'Rafael',
            'last_name' => 'Soporte',
            'phone' => '600988988',
            'password' => Hash::make('123456'),
            'is_active' => true,
        ]);
        $user->email_verified_at = now();
        $user->save();
        $user->syncRoles(['commercial']);

        $examples = [
            ['Ana', 'Martínez Cruz', '922111001', '922111002', 'Calle El Castillo 12', '2º B', '38001', 'Santa Cruz de Tenerife', FuenteNotas::CALLE, true],
            ['Luis', 'García Pérez', '922222001', '922222002', 'Avenida de Anaga 45', '3º A', '38003', 'Santa Cruz de Tenerife', FuenteNotas::VIP_INT, true],
            ['Carmen', 'Rodríguez Díaz', '922333001', null, 'Calle de la Marina 8', 'Bajo', '38002', 'Santa Cruz de Tenerife', FuenteNotas::VIP_EXT, true],
            ['Pedro', 'Hernández Soto', '922444001', '922444002', 'Calle Bethencourt Alfonso 19', '1º C', '38002', 'Santa Cruz de Tenerife', FuenteNotas::CALLE, true],
            ['María', 'López Suárez', '922555001', '922555002', 'Rambla de Pulido 27', '4º D', '38004', 'Santa Cruz de Tenerife', FuenteNotas::PTA_FRIA, false],
            ['José', 'González Ramos', '922666001', null, 'Calle San Sebastián 6', 'Ático', '38003', 'Santa Cruz de Tenerife', FuenteNotas::CALLE, true],
            ['Lucía', 'Fernández Mora', '922777001', '922777002', 'Avenida Tres de Mayo 90', '5º A', '38005', 'Santa Cruz de Tenerife', FuenteNotas::VIP_INT, true],
            ['Miguel', 'Santana León', '922888001', '922888002', 'Calle Imeldo Serís 33', '2º A', '38003', 'Santa Cruz de Tenerife', FuenteNotas::VIP_EXT, false],
            ['Elena', 'Cabrera Nieto', '922999001', null, 'Plaza Weyler 4', '1º B', '38004', 'Santa Cruz de Tenerife', FuenteNotas::CALLE, true],
            ['Pablo', 'Alonso Reyes', '922000001', '922000002', 'Calle del Pilar 15', 'Bajo A', '38002', 'Santa Cruz de Tenerife', FuenteNotas::VIP_INT, true],
        ];

        foreach ($examples as $index => [$first, $last, $phone, $phone2, $street, $floor, $cp, $city, $fuente, $showPhone]) {
            $customer = Customer::updateOrCreate(
                ['email' => sprintf('rafa.soporte.demo.%02d@hidrosane.local', $index + 1)],
                [
                    'first_names' => $first,
                    'last_names' => $last,
                    'phone' => $phone,
                    'secondary_phone' => $phone2,
                    'primary_address' => $street,
                    'nro_piso' => $floor,
                    'postal_code' => $cp,
                    'ciudad' => $city,
                    'provincia' => 'Santa Cruz de Tenerife',
                    'ayuntamiento' => 'Santa Cruz de Tenerife',
                    'dni' => sprintf('1000000%dX', $index),
                ]
            );

            Note::updateOrCreate(
                [
                    'comercial_id' => $user->id,
                    'customer_id' => $customer->id,
                ],
                [
                    'user_id' => $user->id,
                    'fuente' => $fuente,
                    'status' => NoteStatus::CONTACTED,
                    'observations' => null,
                    'visit_date' => today(),
                    'visit_schedule' => $index % 2 === 0 ? 'Mañana' : 'Tarde',
                    'assignment_date' => now(),
                    'de_camino' => false,
                    'show_phone' => $showPhone,
                    'estado_terminal' => null,
                    'reten' => false,
                    'printed' => false,
                    'lat' => 28.4636,
                    'lng' => -16.2518,
                ]
            );
        }

        $this->command?->info("Comercial Rafael Soporte listo: rafa@gmail.com / 123456 (empleado {$user->empleado_id})");
    }
}
