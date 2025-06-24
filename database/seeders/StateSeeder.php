<?php

namespace Database\Seeders;

use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class StateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ruta al archivo JSON (puedes ajustarla)
        $jsonPath = database_path('seeders/data/Provincias.json');

        // Verificar si el archivo existe
        if (!File::exists($jsonPath)) {
            $this->command->error('El archivo JSON no existe: ' . $jsonPath);
            return;
        }

        // Leer el archivo JSON
        $jsonData = File::get($jsonPath);
        $states = json_decode($jsonData, true);

        // Validar si el JSON es válido
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('Error al decodificar el JSON: ' . json_last_error_msg());
            return;
        }

        // Insertar los datos
        foreach ($states as $stateData) {
            State::updateOrCreate(
                ['id' => $stateData['id']],
                [
                    'id' => $stateData['id'],
                    'uid' => $stateData['uid'],
                    'title' => $stateData['title'],
                    'iso' => $stateData['iso'],
                    'country_id' => $stateData['country_id'],
                    'created_at' => $stateData['created_at'],
                    'updated_at' => $stateData['updated_at']
                ]
            );
        }

        $this->command->info('Se cargaron ' . count($states) . ' estados/provincias correctamente.');
    }
}