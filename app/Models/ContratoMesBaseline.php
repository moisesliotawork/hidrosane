<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Total de referencia por mes para detectar variaciones en Contratos/MES.
 *
 * @property int $id
 * @property string $mes_key
 * @property int $baseline_total
 */
class ContratoMesBaseline extends Model
{
    protected $table = 'contratos_mes_baselines';

    protected $fillable = [
        'mes_key',
        'baseline_total',
    ];

    protected $casts = [
        'baseline_total' => 'integer',
    ];
}
