<?php

namespace Database\Seeders;

use App\Models\PostalCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class PostalCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ruta al archivo JSON
        $jsonPath = database_path('seeders/data/Codigos_Postales.json');

        if (!File::exists($jsonPath)) {
            $this->command->error('El archivo JSON no existe: ' . $jsonPath);
            return;
        }

        $jsonData = File::get($jsonPath);
        $postalCodes = json_decode($jsonData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('Error al decodificar el JSON: ' . json_last_error_msg());
            return;
        }

        $count = 0;
        foreach ($postalCodes as $postalCodeData) {
            // Verificar si la ciudad existe
            if (!\App\Models\City::where('id', $postalCodeData['city_id'])->exists()) {
                $this->command->warn("Ciudad ID {$postalCodeData['city_id']} no existe, omitiendo código postal ID {$postalCodeData['id']}");
                continue;
            }

            PostalCode::updateOrCreate(
                ['id' => $postalCodeData['id']],
                [
                    'id' => $postalCodeData['id'],
                    'uid' => $postalCodeData['uid'],
                    'code' => $postalCodeData['code'],
                    'city_id' => $postalCodeData['city_id'],
                    'created_at' => $postalCodeData['created_at'],
                    'updated_at' => $postalCodeData['updated_at']
                ]
            );
            $count++;
        }

        $this->command->info("Se cargaron {$count} códigos postales correctamente.");
    }
}