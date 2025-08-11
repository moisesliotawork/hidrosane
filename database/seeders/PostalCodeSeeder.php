<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PostalCodeSeeder extends Seeder
{
    public function run(): void
    {
        @set_time_limit(0);
        DB::disableQueryLog();

        $jsonPath = database_path('seeders/data/Codigos_Postales.json');
        if (!File::exists($jsonPath)) {
            $this->command?->error('El archivo JSON no existe: ' . $jsonPath);
            return;
        }

        $jsonData = File::get($jsonPath);
        $rows = json_decode($jsonData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command?->error('Error al decodificar el JSON: ' . json_last_error_msg());
            return;
        }

        // 1) Cargar TODOS los IDs de cities (evita N+1)
        $cityIds = DB::table('cities')->pluck('id')->all();
        $validCities = array_flip($cityIds);

        $total = count($rows);
        $bar = $this->command?->getOutput()?->createProgressBar($total);
        $bar?->start();

        $batch = [];
        $size = 5000; // ajusta 1000–10000 según tu server
        $upserts = 0;
        $skipped = 0;

        foreach ($rows as $r) {
            if (!isset($validCities[$r['city_id']])) {
                $skipped++;
                $bar?->advance();
                continue;
            }

            $batch[] = [
                'id'         => $r['id'],
                'uid'        => $r['uid'],
                'code'       => $r['code'],
                'city_id'    => $r['city_id'],
                'created_at' => $r['created_at'] ?? now(),
                'updated_at' => $r['updated_at'] ?? now(),
            ];

            if (count($batch) === $size) {
                DB::transaction(function () use (&$batch, &$upserts) {
                    DB::table('postal_codes')->upsert(
                        $batch,
                        ['id'], // clave de conflicto
                        ['uid', 'code', 'city_id', 'created_at', 'updated_at']
                    );
                    $upserts += count($batch);
                    $batch = [];
                });
            }

            $bar?->advance();
        }

        if ($batch) {
            DB::transaction(function () use (&$batch, &$upserts) {
                DB::table('postal_codes')->upsert(
                    $batch,
                    ['id'],
                    ['uid', 'code', 'city_id', 'created_at', 'updated_at']
                );
                $upserts += count($batch);
                $batch = [];
            });
        }

        $bar?->finish();
        $this->command?->newLine();
        $this->command?->info("Upserted {$upserts} códigos postales; omitidos {$skipped} (ciudad inexistente).");
    }
}
