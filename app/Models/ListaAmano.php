<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro del listado a mano (papel / Excel master).
 *
 * @property int $id
 * @property string $mes_codigo
 * @property int $mes
 * @property int $anio
 * @property int|null $pagina
 * @property int|null $nro
 * @property string $cliente
 * @property string|null $comercial_1
 * @property string|null $comercial_2
 * @property string|null $detalle
 * @property string|null $observaciones
 */
class ListaAmano extends Model
{
    protected $table = 'lista_amano';

    protected $fillable = [
        'mes_codigo',
        'mes',
        'anio',
        'pagina',
        'nro',
        'cliente',
        'comercial_1',
        'comercial_2',
        'detalle',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'mes' => 'integer',
            'anio' => 'integer',
            'pagina' => 'integer',
            'nro' => 'integer',
        ];
    }

    /**
     * Parsea códigos tipo Mayo25 / Sept25 / Enero26 → [mes, anio, codigo].
     *
     * @return array{mes: int, anio: int, codigo: string}|null
     */
    public static function parseMesCodigo(string $raw): ?array
    {
        $codigo = trim($raw);
        if ($codigo === '') {
            return null;
        }

        $map = [
            'enero' => 1,
            'febrero' => 2,
            'marzo' => 3,
            'abril' => 4,
            'mayo' => 5,
            'junio' => 6,
            'julio' => 7,
            'agosto' => 8,
            'sept' => 9,
            'septiembre' => 9,
            'octubre' => 10,
            'noviembre' => 11,
            'diciembre' => 12,
        ];

        if (! preg_match('/^([A-Za-zÁÉÍÓÚáéíóúñÑ]+)\s*(\d{2})$/u', $codigo, $m)) {
            return null;
        }

        $name = mb_strtolower($m[1]);
        $name = strtr($name, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        ]);
        $yy = (int) $m[2];
        $mes = $map[$name] ?? null;
        if ($mes === null) {
            return null;
        }

        $anio = $yy >= 70 ? 1900 + $yy : 2000 + $yy;

        return [
            'mes' => $mes,
            'anio' => $anio,
            'codigo' => $codigo,
        ];
    }
}
