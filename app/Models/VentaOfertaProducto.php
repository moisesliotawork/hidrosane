<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaOfertaProducto extends Model
{
    use HasFactory;

    protected $table = 'venta_oferta_productos';

    protected $fillable = [
        'venta_oferta_id',
        'producto_id',
        'cantidad',
        'puntos_linea',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'puntos_linea' => 'integer',
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
