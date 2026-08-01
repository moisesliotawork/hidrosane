<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AdditionalRolesSeeder::class,
            GeneralManagerRoleSeeder::class,
            UserSeeder::class,
            UsuariosViejos::class,
            TiposMedidaSeeder::class,
            ProductosConMedidasSeeder::class,
            ProductosSimplesSeeder::class,
            OfertaSeeder::class,
            CustomerSeeder::class,
            NoteSeeder::class,
            //VentaSeeder::class
            // BorradosDemoSeeder::class, // php artisan db:seed --class=BorradosDemoSeeder
            // ListContractDemoSeeder::class, // php artisan db:seed --class=ListContractDemoSeeder
            // ContratosMesAlertaDemoSeeder::class, // php artisan db:seed --class=ContratosMesAlertaDemoSeeder
        ]);
    }
}
