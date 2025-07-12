<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{HasMany, BelongsToMany};

class Oferta extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'puntos_base',
        'precio_base',
        'descripcion',
    ];

    /* ---------- Relaciones ---------- */

    // una oferta puede estar en muchas ventas (a través del pivot VentaOferta)
    public function ventas(): BelongsToMany
    {
        // de paso accedemos a precio_cerrado y puntos desde el pivot
        return $this->belongsToMany(Venta::class, 'venta_ofertas')
                    ->using(VentaOferta::class)   // “custom pivot” = el modelo intermedio
                    ->withPivot(['precio_cerrado', 'puntos'])
                    ->withTimestamps();
    }

    // relación directa al modelo intermedio
    public function ventaOfertas(): HasMany
    {
        return $this->hasMany(VentaOferta::class);
    }
}
