<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\EstadoEntrega; 

class Reparto extends Model
{
    use HasFactory;

    protected $fillable = [
        'venta_id',
        'estado',
        'de_camino',
        'lat',
        'lng',
        'estado_entrega',
    ];

    protected $casts = [
        'estado_entrega' => EstadoEntrega::class,
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
