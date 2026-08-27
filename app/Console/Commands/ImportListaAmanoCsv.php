<?php

namespace App\Console\Commands;

use App\Models\ListaAmano;
use Illuminate\Console\Command;

class ImportListaAmanoCsv extends Command
{
    protected $signature = 'lista-amano:import
        {file : Ruta al CSV (MES;pagina;nro;cliente;comercial_1;comercial_2;detalle)}
        {--truncate : Vaciar la tabla antes de importar}';

    protected $description = 'Importa el Excel/CSV master del listado a mano a lista_amano';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $alt = base_path($file);
            if (is_file($alt)) {
                $file = $alt;
            } else {
                $this->error("No existe el archivo: {$file}");

                return self::FAILURE;
            }
        }

        if ($this->option('truncate')) {
            ListaAmano::query()->delete();
            $this->warn('Tabla lista_amano vaciada.');
        }

        $fh = fopen($file, 'r');
        if ($fh === false) {
            $this->error('No se pudo abrir el CSV.');

            return self::FAILURE;
        }

        $header = fgetcsv($fh, 0, ';');
        if ($header === false) {
            fclose($fh);
            $this->error('CSV vacío.');

            return self::FAILURE;
        }

        // Quitar BOM del primer header
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? (string) $header[0];
        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $header);

        $inserted = 0;
        $skipped = 0;

        while (($row = fgetcsv($fh, 0, ';')) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }

            $data = [];
            foreach ($header as $i => $key) {
                $data[$key] = trim((string) ($row[$i] ?? ''));
            }

            $mesRaw = $data['mes'] ?? '';
            $parsed = ListaAmano::parseMesCodigo($mesRaw);
            if ($parsed === null) {
                $skipped++;

                continue;
            }

            $cliente = trim((string) ($data['cliente'] ?? ''));
            if ($cliente === '') {
                $skipped++;

                continue;
            }

            ListaAmano::query()->create([
                'mes_codigo' => $parsed['codigo'],
                'mes' => $parsed['mes'],
                'anio' => $parsed['anio'],
                'pagina' => filled($data['pagina'] ?? null) ? (int) $data['pagina'] : null,
                'nro' => filled($data['nro'] ?? null) ? (int) $data['nro'] : null,
                'cliente' => $cliente,
                'comercial_1' => ($data['comercial_1'] ?? '') !== '' ? $data['comercial_1'] : null,
                'comercial_2' => ($data['comercial_2'] ?? '') !== '' ? $data['comercial_2'] : null,
                'detalle' => ($data['detalle'] ?? '') !== '' ? $data['detalle'] : null,
                'observaciones' => null,
            ]);
            $inserted++;
        }

        fclose($fh);

        $this->info("Importados: {$inserted} · Omitidos: {$skipped}");

        return self::SUCCESS;
    }
}
