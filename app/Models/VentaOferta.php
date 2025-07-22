<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * @property int $id
 * @property int $venta_id
 * @property int $oferta_id
 * @property int $puntos
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Oferta $oferta
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VentaOfertaProducto> $productos
 * @property-read int|null $productos_count
 * @property-read \App\Models\Venta $venta
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VentaOferta newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VentaOferta newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VentaOferta query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VentaOferta whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VentaOferta whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VentaOferta whereOfertaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VentaOferta wherePuntos($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VentaOferta whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VentaOferta whereVentaId($value)
 * @mixin \Eloquent
 */
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
