<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AnotacionVisita;
use App\Models\NoteReassignmentBatch;
use App\Models\NoteReassignmentLog;
use App\Models\Note;
use App\Models\User;
use Carbon\Carbon;

class BackfillReasignaciones extends Command
{
    protected $signature = 'reasignaciones:backfill
                            {--dry-run : Mostrar lo que se insertaría sin guardar nada}
                            {--force  : Ejecutar aunque ya existan registros en la tabla}';

    protected $description = 'Rellena note_reassignment_batches/logs desde anotaciones_visitas históricas (REASIGNACIÓN y REASIGNACIÓN MASIVA)';

    public function handle(): int
    {
        // Protección contra doble ejecución
        if (!$this->option('force') && NoteReassignmentBatch::exists()) {
            $existing = NoteReassignmentBatch::count();
            $this->warn("Ya existen {$existing} registros en note_reassignment_batches.");
            $this->warn('Usa --force si quieres añadir igualmente los históricos que falten.');
            return 1;
        }

        $dryRun = $this->option('dry-run');

        $this->info('Cargando anotaciones históricas...');

        // Solo notas que todavía existen (FK constraint)
        $notesExistentes = Note::pluck('id')->flip()->toArray();

        $anotaciones = AnotacionVisita::whereIn('asunto', ['REASIGNACIÓN', 'REASIGNACIÓN MASIVA'])
            ->whereNotNull('author_id')
            ->whereNotNull('nota_id')
            ->whereIn('nota_id', array_keys($notesExistentes))
            ->orderBy('author_id')
            ->orderBy('created_at')
            ->get();

        $total = $anotaciones->count();

        if ($total === 0) {
            $this->warn('No se encontraron anotaciones de reasignación para procesar.');
            return 0;
        }

        $this->info("Anotaciones encontradas: {$total}");

        // Mapa empleado_id → user.id para extraer el comercial receptor
        $empleadoMap = User::whereNotNull('empleado_id')
            ->pluck('id', 'empleado_id')
            ->toArray();

        // Agrupar: mismo autor + mismo minuto + mismo destino = mismo batch
        $groups = $anotaciones->groupBy(function (AnotacionVisita $a) {
            $minuto = Carbon::parse($a->created_at)->format('Y-m-d H:i');
            $destino = $this->claveDestino($a->cuerpo);
            return "{$a->author_id}|{$minuto}|{$destino}";
        });

        $this->info("Batches a crear: {$groups->count()}");

        if ($dryRun) {
            $this->newLine();
            $this->line('── DRY RUN ─────────────────────────────────────────────');
        }

        $batchCount = 0;
        $logCount   = 0;

        $bar = $this->output->createProgressBar($groups->count());
        $bar->start();

        foreach ($groups as $group) {
            /** @var AnotacionVisita $first */
            $first    = $group->first();
            $esReten  = $this->esDestinacionReten($first->cuerpo);
            $toComId  = null;

            if (!$esReten) {
                $empId   = $this->extraerEmpleadoId($first->cuerpo);
                $toComId = ($empId && isset($empleadoMap[$empId])) ? $empleadoMap[$empId] : null;
            }

            if ($dryRun) {
                $bar->clear();
                $dest = $esReten ? 'RETÉN' : ($toComId ? "COM:{$toComId}" : 'DESCONOCIDO');
                $this->line("  author={$first->author_id} | {$first->created_at} | {$dest} | notas={$group->count()}");
                $bar->display();
            } else {
                $batch = NoteReassignmentBatch::create([
                    'author_id'       => $first->author_id,
                    'to_comercial_id' => $toComId,
                    'to_reten'        => $esReten,
                    'reassigned_at'   => $first->created_at,
                ]);

                foreach ($group as $anotacion) {
                    NoteReassignmentLog::create([
                        'batch_id'          => $batch->id,
                        'note_id'           => $anotacion->nota_id,
                        'from_comercial_id' => null, // no disponible en histórico
                    ]);
                    $logCount++;
                }
            }

            $batchCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $logsEstimados = $batchCount > 0 ? $total : 0;
            $this->info("DRY RUN — se crearían {$batchCount} batches y {$logsEstimados} logs. Sin cambios en BD.");
        } else {
            $this->info("✅ Backfill completado: {$batchCount} batches y {$logCount} logs creados.");
        }

        return 0;
    }

    /** Clave de agrupación para identificar el destino (reten o empleado_id) */
    private function claveDestino(string $cuerpo): string
    {
        if ($this->esDestinacionReten($cuerpo)) {
            return 'reten';
        }
        return $this->extraerEmpleadoId($cuerpo) ?? 'desconocido';
    }

    private function esDestinacionReten(string $cuerpo): bool
    {
        return (bool) preg_match('/ret[eé]n/i', $cuerpo);
    }

    /**
     * Extrae el empleado_id del texto.
     * Patrones esperados:
     *   "... al comercial Nombre Apellido - EMP001. Se reasignó..."
     *   "... al comercial Nombre Apellido - EMP001."
     */
    private function extraerEmpleadoId(string $cuerpo): ?string
    {
        // Busca " - XXXX." donde XXXX es el empleado_id (alfanum + guiones)
        if (preg_match('/ - ([A-Z0-9][A-Z0-9\-]*)\.(\s|$)/i', $cuerpo, $m)) {
            return strtoupper(trim($m[1]));
        }
        return null;
    }
}
