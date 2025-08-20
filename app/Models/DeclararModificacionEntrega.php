<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeclararModificacionEntrega extends Model
{
    use HasFactory;

    protected $table = 'declarar_modificacion_entregas';

    protected $fillable = [
        'venta_id',
        'user_id',
        'fecha',
        'observacion',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function venta()
    {
        return $this->belongsTo(\App\Models\Venta::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
