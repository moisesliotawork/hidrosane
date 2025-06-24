<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ruta al archivo JSON
        $jsonPath = database_path('seeders/data/Ciudades.json');

        if (!File::exists($jsonPath)) {
            
            $this->command->error('El archivo JSON no existe: ' . $jsonPath);
            return;
        }

        $jsonData = File::get($jsonPath);
        $cities = json_decode($jsonData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('Error al decodificar el JSON: ' . json_last_error_msg());
            return;
        }

        $count = 0;
        foreach ($cities as $cityData) {
            // Verificar si la provincia existe
            if (!\App\Models\State::where('id', $cityData['province_id'])->exists()) {
                $this->command->warn("Provincia ID {$cityData['province_id']} no existe, omitiendo ciudad ID {$cityData['id']}");
                continue;
            }

            City::updateOrCreate(
                ['id' => $cityData['id']],
                [
                    'id' => $cityData['id'],
                    'uid' => $cityData['uid'],
                    'title' => $cityData['title'],
                    'state_id' => $cityData['province_id'],
                    'created_at' => $cityData['created_at'],
                    'updated_at' => $cityData['updated_at']
                ]
            );
            $count++;
        }

        $this->command->info("Se cargaron {$count} ciudades correctamente.");
    }
}