<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{Note, User, TeleoperatorMonthlyStat};

class BuildTeleoperatorMonthlyStats extends Command
{
    // Todo en una sola línea para que PHP no se queje
    protected $signature = 'bi:build-teleoperator-monthly-stats {--year= : Solo un año concreto} {--truncate : Vaciar tabla antes de recalcular}';

    protected $description = 'Recalcula las estadísticas mensuales de las teleoperadoras (producidas, ventas, confirmadas, nulas).';

    public function handle(): int
    {
        $yearFilter = $this->option('year') ? (int) $this->option('year') : null;

        if ($this->option('truncate')) {
            $this->info('Truncando tabla teleoperator_monthly_stats...');
            TeleoperatorMonthlyStat::truncate();
        }

        // 1) Obtener IDs de usuarios con rol teleoperator o head_of_room
        $teleoperatorIds = User::role(['teleoperator', 'head_of_room'])->pluck('id')->all();

        if (empty($teleoperatorIds)) {
            $this->warn('No hay usuarios con rol teleoperator ni head_of_room.');
            return self::SUCCESS;
        }

        // Estructura interna: [teleoperator_id][year][month] => array métricas
        $stats = [];

        /**
         * 2) PRODUCIDAS:
         * Notas por user_id, agrupadas por YEAR(created_at), MONTH(created_at)
         */
        $this->info('Calculando PRODUCIDAS por teleoperadora / mes...');

        $producidasQuery = Note::query()
            ->whereIn('user_id', $teleoperatorIds);

        if ($yearFilter) {
            $producidasQuery->whereYear('created_at', $yearFilter);
        }

        $producidasRows = $producidasQuery
            ->selectRaw('user_id as teleoperator_id, YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as producidas')
            ->groupBy('teleoperator_id', 'year', 'month')
            ->get();

        foreach ($producidasRows as $row) {
            $tId = (int) $row->teleoperator_id;
            $year = (int) $row->year;
            $month = (int) $row->month;
            $q = (int) floor(($month - 1) / 3) + 1;

            $stats[$tId][$year][$month] ??= [
                'teleoperator_id' => $tId,
                'year' => $year,
                'month' => $month,
                'quarter' => $q,
                'producidas' => 0,
                'confirmadas' => 0,
                'ventas' => 0,
                'nulas' => 0,
            ];

            $stats[$tId][$year][$month]['producidas'] = (int) $row->producidas;
        }

        /**
         * 3) CONFIRMADAS / VENTAS / NULAS:
         * Se basan en fecha_declaracion y estado_terminal
         */
        $this->info('Calculando CONFIRMADAS / VENTAS / NULAS por teleoperadora / mes...');

        $declaracionesQuery = Note::query()
            ->whereIn('user_id', $teleoperatorIds)
            ->whereNotNull('fecha_declaracion');

        if ($yearFilter) {
            $declaracionesQuery->whereYear('fecha_declaracion', $yearFilter);
        }

        $declaracionesRows = $declaracionesQuery
            ->selectRaw('
                user_id as teleoperator_id,
                YEAR(fecha_declaracion) as year,
                MONTH(fecha_declaracion) as month,
                SUM(CASE WHEN estado_terminal = "confirmado" THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN estado_terminal = "venta"      THEN 1 ELSE 0 END) as ventas,
                SUM(CASE WHEN estado_terminal = "nulo"       THEN 1 ELSE 0 END) as nulas
            ')
            ->groupBy('teleoperator_id', 'year', 'month')
            ->get();

        foreach ($declaracionesRows as $row) {
            $tId = (int) $row->teleoperator_id;
            $year = (int) $row->year;
            $month = (int) $row->month;
            $q = (int) floor(($month - 1) / 3) + 1;

            $stats[$tId][$year][$month] ??= [
                'teleoperator_id' => $tId,
                'year' => $year,
                'month' => $month,
                'quarter' => $q,
                'producidas' => 0,
                'confirmadas' => 0,
                'ventas' => 0,
                'nulas' => 0,
            ];

            $stats[$tId][$year][$month]['confirmadas'] = (int) $row->confirmadas;
            $stats[$tId][$year][$month]['ventas'] = (int) $row->ventas;
            $stats[$tId][$year][$month]['nulas'] = (int) $row->nulas;
        }

        /**
         * 3.5) RELLENAR MESES VACÍOS CON CEROS PARA CADA TELEOPERADOR
         */
        $this->info('Rellenando meses vacíos con ceros...');

        if ($yearFilter) {
            // Si pasas --year, forzamos ese año aunque no haya stats previos
            $years = [$yearFilter];
        } else {
            // Sacar todos los años que aparezcan en $stats
            $years = [];
            foreach ($stats as $teleId => $yearsData) {
                $years = array_unique(array_merge($years, array_keys($yearsData)));
            }
            sort($years);
        }

        // Si no hay ningún año (por ejemplo no hubo notas pero quieres histórico completo),
        // no rellenamos nada; en el uso normal con --year esto no pasará.
        foreach ($teleoperatorIds as $tId) {
            foreach ($years as $year) {
                for ($month = 1; $month <= 12; $month++) {
                    if (!isset($stats[$tId][$year][$month])) {
                        $q = (int) floor(($month - 1) / 3) + 1;

                        $stats[$tId][$year][$month] = [
                            'teleoperator_id' => $tId,
                            'year' => $year,
                            'month' => $month,
                            'quarter' => $q,
                            'producidas' => 0,
                            'confirmadas' => 0,
                            'ventas' => 0,
                            'nulas' => 0,
                        ];
                    }
                }
            }
        }

        /**
         * 4) UPSERT en la tabla de BI
         */
        $this->info('Guardando estadísticas en teleoperator_monthly_stats...');

        $payload = [];

        foreach ($stats as $tId => $yearsData) {
            foreach ($yearsData as $year => $monthsData) {
                foreach ($monthsData as $month => $data) {
                    $payload[] = [
                        'teleoperator_id' => $data['teleoperator_id'],
                        'year' => $data['year'],
                        'month' => $data['month'],
                        'quarter' => $data['quarter'],
                        'producidas' => $data['producidas'],
                        'confirmadas' => $data['confirmadas'],
                        'ventas' => $data['ventas'],
                        'nulas' => $data['nulas'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        if (!empty($payload)) {
            TeleoperatorMonthlyStat::upsert(
                $payload,
                ['teleoperator_id', 'year', 'month'], // claves únicas
                ['quarter', 'producidas', 'confirmadas', 'ventas', 'nulas', 'updated_at']
            );
        }

        $this->info('Proceso completado. Registros generados/actualizados: ' . count($payload));

        return self::SUCCESS;
    }
}
