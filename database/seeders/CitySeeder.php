<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        // Evitar timeouts y logs enormes
        @set_time_limit(0);
        DB::disableQueryLog();

        $jsonPath = database_path('seeders/data/Ciudades.json');
        if (!File::exists($jsonPath)) {
            $this->command?->error('El archivo JSON no existe: ' . $jsonPath);
            return;
        }

        $jsonData = File::get($jsonPath);
        $cities = json_decode($jsonData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command?->error('Error al decodificar el JSON: ' . json_last_error_msg());
            return;
        }

        // 1) Cargar TODOS los IDs de states una vez (evita N+1)
        $stateIds = DB::table('states')->pluck('id')->all();
        $validStates = array_flip($stateIds);

        $total = count($cities);
        $bar = $this->command?->getOutput()?->createProgressBar($total);
        $bar?->start();

        $batch = [];
        $size = 1000; // lote de upsert
        $upserts = 0;
        $skipped = 0;

        foreach ($cities as $c) {
            // Saltar si la provincia no existe
            if (!isset($validStates[$c['province_id']])) {
                $skipped++;
                $bar?->advance();
                continue;
            }

            $batch[] = [
                'id' => $c['id'],
                'uid' => $c['uid'],
                'title' => $c['title'],
                'state_id' => $c['province_id'],
                'created_at' => $c['created_at'] ?? now(),
                'updated_at' => $c['updated_at'] ?? now(),
            ];

            if (count($batch) === $size) {
                DB::table('cities')->upsert(
                    $batch,
                    ['id'], // clave de conflicto
                    ['uid', 'title', 'state_id', 'created_at', 'updated_at']
                );
                $upserts += count($batch);
                $batch = [];
            }

            $bar?->advance(); // mantiene la sesión con actividad constante
        }

        // Último lote
        if ($batch) {
            DB::table('cities')->upsert(
                $batch,
                ['id'],
                ['uid', 'title', 'state_id', 'created_at', 'updated_at']
            );
            $upserts += count($batch);
        }

        $bar?->finish();
        $this->command?->newLine();
        $this->command?->info("Upserted {$upserts} ciudades; omitidas {$skipped} (provincia inexistente).");
    }
}
