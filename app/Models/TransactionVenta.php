<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionVenta extends Pivot
{
    use SoftDeletes;

    protected $table = 'transaction_venta';
    public $timestamps = true;

    protected $fillable = [
        'id_contrato',
        'id_contrato_asoc',
    ];
}
