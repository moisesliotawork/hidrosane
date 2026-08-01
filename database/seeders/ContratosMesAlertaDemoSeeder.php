<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Fuerza datos demo con VARIACIÓN ≠ 0 para probar Contratos/MES (local).
 * El banner global rojo solo aparece si hay meses con menos contratos.
 *
 * Uso:
 *   php artisan db:seed --class=ContratosMesAlertaDemoSeeder
 *
 * Internamente reutiliza ListContractDemoSeeder (casos +2 / -2 / 0).
 */
class ContratosMesAlertaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ListContractDemoSeeder::class);

        $changes = \App\Support\ContratosPorMesStats::monthsWithChanges();
        $negatives = \App\Support\ContratosPorMesStats::monthsWithNegativeChanges();

        $this->command?->info('');
        $this->command?->info('Alerta global SuperAdmin:');
        if ($changes->isEmpty()) {
            $this->command?->warn('  · No hay variaciones (el aviso no se mostrará).');
            $this->command?->warn('  · Revisa baselines / migraciones.');

            return;
        }

        $this->command?->info('  · Meses con cambio: ' . $changes->count());
        foreach ($changes as $row) {
            $var = (int) $row->variacion;
            $sign = $var > 0 ? "+{$var}" : (string) $var;
            $this->command?->info("  · {$row->mes_key}: variación {$sign}");
        }

        if ($negatives->isEmpty()) {
            $this->command?->warn('  · Solo hay aumentos: el banner rojo NO se mostrará.');
        } else {
            $this->command?->info('  · Banner rojo activo: ' . $negatives->count() . ' mes(es) con menos contratos.');
            $this->command?->info('  · Abre cualquier recurso de SuperAdmin y verás el banner rojo arriba.');
        }
        $this->command?->info('  · URL directa: /superAdmin/contratos-por-mes');
    }
}
