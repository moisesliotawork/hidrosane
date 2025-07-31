<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $venta_oferta_id
 * @property int $producto_id
 * @property int $cantidad
 * @property int $cantidad_entregada   Cantidad que entregó el repartidor
 * @property int|null $puntos_linea
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Producto $producto
 * @property-read \App\Models\VentaOferta $ventaOferta
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VentaOfertaProducto newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VentaOfertaProducto newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VentaOfertaProducto query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VentaOfertaProducto whereCantidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VentaOfertaProducto whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VentaOfertaProducto whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VentaOfertaProducto whereProductoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VentaOfertaProducto wherePuntosLinea($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VentaOfertaProducto whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VentaOfertaProducto whereVentaOfertaId($value)
 * @mixin \Eloquent
 */
class VentaOfertaProducto extends Model
{
    use HasFactory;

    protected $table = 'venta_oferta_productos';

    protected $fillable = [
        'venta_oferta_id',
        'producto_id',
        'cantidad',
        'cantidad_entregada',
        'puntos_linea',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'puntos_linea' => 'integer',
        'cantidad_entregada' => 'integer',
    ];

    /* ---------- Relaciones ---------- */

    public function ventaOferta(): BelongsTo
    {
        return $this->belongsTo(VentaOferta::class);
    }

    public function producto(): BelongsTo
    {
        // Ajusta el namespace si tu modelo es App\Models\Producto
        return $this->belongsTo(Producto::class);
    }
}
