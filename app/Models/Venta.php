<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Venta extends Model
{
    use HasFactory;

    protected $fillable = [
        'note_id',
        'customer_id',
        'comercial_id',
        'companion_id',
        'fecha_venta',
        'importe_total',
        'num_cuotas',
        'accesorio_entregado',
        'motivo_venta',
        'motivo_horario',
        'interes_art',
        'interes_art_detalle',
        'observaciones_repartidor',
        'cuota_mensual',
        'fecha_entrega',
        'horario_entrega',
        'productos_externos'
    ];

    protected $casts = [
        'fecha_venta' => 'datetime',
        'importe_total' => 'decimal:2',
        'num_cuotas' => 'integer',
        'interes_art' => 'boolean',
        'cuota_mensual' => 'decimal:2',
        'productos_externos' => 'array',

    ];

    /* ---------- Relaciones ---------- */

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function comercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comercial_id');
    }

    public function companion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'companion_id');
    }

    public function ventaOfertas(): HasMany   // alias “ofertas()” si lo prefieres
    {
        return $this->hasMany(VentaOferta::class);
    }
}
