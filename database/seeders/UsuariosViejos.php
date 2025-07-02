<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UsuariosViejos extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $usuarios = [
            ['id' => 0, 'nombre' => 'Rafael Perez', 'cargo' => 'soporte', 'email' => 'soporte@ohana.es', 'clave' => null, 'telefono' => null],
            ['id' => 1, 'nombre' => 'Jonna', 'cargo' => 'gerente', 'email' => 'direccion@ohanadistribucion.com', 'clave' => null, 'telefono' => null],
            ['id' => 2, 'nombre' => 'Abby', 'cargo' => 'jefe administracion', 'email' => 'abbyvj@gmail.com', 'clave' => null, 'telefono' => null],
            ['id' => 3, 'nombre' => 'Cristian', 'cargo' => 'reparto', 'email' => 'cristianlorenzocarballido1990@gmail.com', 'clave' => null, 'telefono' => null],
            ['id' => 4, 'nombre' => 'Alba Alvarez', 'cargo' => 'jefe equipo', 'email' => 'albiiux1505@gmail.com', 'clave' => null, 'telefono' => null],
            ['id' => 5, 'nombre' => 'Brais Lorenzo', 'cargo' => 'repartidor', 'email' => 'braislorenzotroncoso@gmail.com', 'clave' => 'bl-0303', 'telefono' => null],
            ['id' => 6, 'nombre' => 'Candido Rodriguez', 'cargo' => 'jefe de equipo', 'email' => 'delegadovigo20211@gmail.com', 'clave' => 'cr-8787', 'telefono' => null],
            ['id' => 7, 'nombre' => 'Ismael Lorenzo', 'cargo' => 'jefe de equipo', 'email' => 'lorenzoaraujoismael@gmail.com', 'clave' => 'rafacor', 'telefono' => null],
            ['id' => 8, 'nombre' => 'Fervi Ferreira', 'cargo' => 'jefe de equipo', 'email' => 'carlosfervi123@outlook.es', 'clave' => 'rafacor', 'telefono' => null],
            ['id' => 9, 'nombre' => 'joel lorenzo', 'cargo' => 'comercial', 'email' => 'joellorenzoaraujo@gmail.com', 'clave' => 'jo8585', 'telefono' => null],
            ['id' => 10, 'nombre' => 'zarait delgado', 'cargo' => 'comercial', 'email' => 'zarait@gmail.com', 'clave' => 'zd-2025', 'telefono' => null],
            ['id' => 11, 'nombre' => 'lucia taboada', 'cargo' => 'comercial', 'email' => 'villanuevataboadalucia@gmail.com', 'clave' => 'tabo2310', 'telefono' => null],
            ['id' => 12, 'nombre' => 'nerea', 'cargo' => 'comercial', 'email' => 'nereaportocasillas@gmail.com', 'clave' => 'ne-2030', 'telefono' => null],
            ['id' => 13, 'nombre' => 'nuria gonzalez', 'cargo' => 'comercial', 'email' => 'lulialex9@hotmai.es', 'clave' => 'ng-1010', 'telefono' => null],
            ['id' => 14, 'nombre' => 'Carla Baz', 'cargo' => 'comercial', 'email' => 'carlabazlomba@hotmail.com', 'clave' => 'cb-1234', 'telefono' => null],
            ['id' => 15, 'nombre' => 'Martin Daponte', 'cargo' => 'comercial', 'email' => null, 'clave' => null, 'telefono' => null],
            ['id' => 16, 'nombre' => 'pol joel rivero', 'cargo' => 'comercial', 'email' => 'joelriveirodominguez@gmail.com', 'clave' => 'pol-1010', 'telefono' => null],
            ['id' => 17, 'nombre' => 'Luna', 'cargo' => 'comercial', 'email' => 'ribaslagoluna4@gmail.com', 'clave' => 'lr-1012', 'telefono' => null],
            ['id' => 18, 'nombre' => 'Iago', 'cargo' => 'comercial', 'email' => 'iagobarle@gmail.com', 'clave' => 'ia-1234', 'telefono' => null],
            ['id' => 19, 'nombre' => 'Carmen Bello', 'cargo' => 'jefa de sala', 'email' => 'tricarmenbello@gmail.com', 'clave' => null, 'telefono' => '650927869'],
            ['id' => 20, 'nombre' => 'Olga', 'cargo' => 'teleoperadora', 'email' => 'olgalorenzomiguez@gmail.com', 'clave' => 'ol-9898', 'telefono' => null],
            ['id' => 21, 'nombre' => 'Ionela Neicu', 'cargo' => 'teleoperadora', 'email' => 'ioneicu@hotmail.com', 'clave' => 'in-9325', 'telefono' => null],
            ['id' => 22, 'nombre' => 'CONCHI', 'cargo' => 'teleoperadora', 'email' => 'conchipeleteiro@gmail.com', 'clave' => 'cp-5040', 'telefono' => null],
            ['id' => 23, 'nombre' => 'Antia Pappillo Dominguez', 'cargo' => 'teleoperadora', 'email' => 'antiapampillon@gmail.com', 'clave' => 'ap-2840', 'telefono' => null],
            ['id' => 24, 'nombre' => 'arnae segarra', 'cargo' => 'comercial', 'email' => 'nauaresegarra@gmail.com', 'clave' => 'as-2030', 'telefono' => null],
            ['id' => 25, 'nombre' => 'ivan domene', 'cargo' => 'comercial', 'email' => 'jamaicaterrasa@gmail.com', 'clave' => 'id-2050', 'telefono' => null],
            ['id' => 26, 'nombre' => 'arnau', 'cargo' => 'comercial', 'email' => null, 'clave' => null, 'telefono' => null],
            ['id' => 99, 'nombre' => 'DEMO SOPORTE rafa', 'cargo' => 'comercial', 'email' => 'ventas@rafaelpianomusic.com', 'clave' => null, 'telefono' => null],
        ];

        foreach ($usuarios as $usuario) {
            if (!$usuario['email']) {
                continue; // saltar usuarios sin email
            }

            $user = User::create([
                'name' => $usuario['nombre'],
                'last_name' => "",
                'email' => $usuario['email'],
                'password' => Hash::make($usuario['clave'] ?? '123456'),
                'phone' => $usuario['telefono'] ?? '000000000',
                'empleado_id' => str_pad($usuario['id'], 3, '0', STR_PAD_LEFT)
            ]);

            // Asignar roles basado en el cargo
            $cargo = strtolower(trim($usuario['cargo']));

            match (true) {
                str_contains($cargo, 'admin') => $user->assignRole('admin'),
                str_contains($cargo, 'jefe de equipo'), str_contains($cargo, 'jefe equipo') => $user->assignRole('team_leader'),
                str_contains($cargo, 'gerente') => $user->assignRole('gerente_general'),
                str_contains($cargo, 'soporte') => $user->assignRole('app_support'),
                str_contains($cargo, 'repart') => $user->assignRole('delivery'),
                str_contains($cargo, 'teleoperador') => $user->assignRole('teleoperator'),
                str_contains($cargo, 'sala') => $user->assignRole('head_of_room'),
                str_contains($cargo, 'comercial') => $user->assignRole('commercial'),
                default => $user->assignRole('commercial'),
            };
        }
    }
}
