<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Reparto extends Model
{
    use HasFactory;

    protected $fillable = [
        'venta_id',
        'estado',
        'de_camino',
        'lat',
        'lng',
    ];

    // Relación con venta
    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function getRepartidor(): ?User
    {
        return $this->venta?->repartidor;
    }
}
