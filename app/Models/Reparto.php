<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\EstadoEntrega;
use App\Enums\EstadoReparto;

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
        'cliente_firma_garantias',
        'cliente_comentario_goodwork',
        'cliente_firma_digital',
    ];

    protected $casts = [
        'estado_entrega' => EstadoEntrega::class,
        'cliente_firma_garantias' => 'boolean',
        'cliente_comentario_goodwork' => 'boolean',
        'cliente_firma_digital' => 'boolean',
        
        'estado' => EstadoReparto::class,
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
