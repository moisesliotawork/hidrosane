<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class VentaOferta extends Model
{
    use HasFactory;

    // esta tabla NO es un simple pivot: guardamos precio y puntos
    protected $table = 'venta_ofertas';

    protected $fillable = [
        'venta_id',
        'oferta_id',
        'puntos',
    ];

    protected $casts = [
        'puntos' => 'integer',
    ];

    /* ---------- Relaciones ---------- */

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function oferta(): BelongsTo
    {
        return $this->belongsTo(Oferta::class);
    }

    // líneas de productos personalizadas para ESTA oferta en ESTA venta
    public function productos(): HasMany
    {
        return $this->hasMany(VentaOfertaProducto::class);
    }
}
