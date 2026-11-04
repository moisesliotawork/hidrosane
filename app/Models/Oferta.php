<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{HasMany, BelongsToMany};

class Oferta extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre',
        'valor',
        'puntos_base',
        'precio_base',
        'descripcion',
        'visible',
    ];

    protected $casts = [
        'visible' => 'boolean',
        'valor' => 'decimal:2',
        'puntos_base' => 'integer',
        'precio_base' => 'decimal:2',
        'deleted_at' => 'datetime',
    ];

    /* ---------- Relaciones ---------- */

    public function ventas(): BelongsToMany
    {
        return $this->belongsToMany(Venta::class, 'venta_ofertas')
            ->using(VentaOferta::class)
            ->withPivot(['precio_cerrado', 'puntos'])
            ->withTimestamps();
    }

    public function ventaOfertas(): HasMany
    {
        return $this->hasMany(VentaOferta::class);
    }

    public function productos()
    {
        return $this->belongsToMany(\App\Models\Producto::class, 'oferta_productos')
            ->withPivot('cantidad', 'puntos')
            ->withTimestamps();
    }
}
