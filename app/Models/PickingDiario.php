<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\PickingDiario
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $fecha
 * @property int $producto_id
 * @property int $cantidad_total
 * @property bool $entregado
 * @property \Illuminate\Support\Carbon|null $entregado_at
 * @property int|null $entregado_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Producto $producto
 * @property-read \App\Models\User|null $entregadoPor
 * @method static \Illuminate\Database\Eloquent\Builder|PickingDiario forDate($date)
 * @method static \Illuminate\Database\Eloquent\Builder|PickingDiario between($from, $to)
 * @method static \Illuminate\Database\Eloquent\Builder|PickingDiario pendientes()
 * @method static \Illuminate\Database\Eloquent\Builder|PickingDiario delProducto(int $productoId)
 * @mixin \Eloquent
 */
class PickingDiario extends Model
{
    use HasFactory;

    // Tabla
    protected $table = 'picking_diario';

    // Asignación masiva
    protected $fillable = [
        'fecha',
        'producto_id',
        'cantidad_total',
        'entregado',
        'entregado_at',
        'entregado_by',
    ];

    // Casts
    protected $casts = [
        'fecha' => 'date',
        'cantidad_total' => 'integer',
        'entregado' => 'boolean',
        'entregado_at' => 'datetime',
    ];

    /* ===================== Relaciones ===================== */

    public function producto(): BelongsTo
    {
        // Ajusta el namespace si tu modelo de producto es otro
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function entregadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregado_by');
    }

    /* ===================== Scopes útiles ===================== */

    // Un día exacto (string 'YYYY-MM-DD' o Carbon)
    public function scopeForDate($q, $date)
    {
        return $q->whereDate('fecha', \Illuminate\Support\Carbon::parse($date)->toDateString());
    }

    // Rango de fechas (incluyente)
    public function scopeBetween($q, $from, $to)
    {
        $from = \Illuminate\Support\Carbon::parse($from)->toDateString();
        $to = \Illuminate\Support\Carbon::parse($to)->toDateString();

        return $q->whereBetween('fecha', [$from, $to]);
    }

    // Solo pendientes (no entregados)
    public function scopePendientes($q)
    {
        return $q->where('entregado', false);
    }

    // Filtrar por producto
    public function scopeDelProducto($q, int $productoId)
    {
        return $q->where('producto_id', $productoId);
    }

    /* ===================== Helpers de dominio ===================== */

    // Marcar como entregado (picking listo)
    public function marcarEntregado(?int $userId = null): self
    {
        $this->entregado = true;
        $this->entregado_at = now();
        $this->entregado_by = $userId ?? (auth()->id() ?: null);
        $this->save();

        return $this;
    }

    // Deshacer entregado
    public function desmarcarEntregado(): self
    {
        $this->entregado = false;
        $this->entregado_at = null;
        $this->entregado_by = null;
        $this->save();

        return $this;
    }

    // ¿Está completamente listo?
    public function getEstaListoAttribute(): bool
    {
        return (bool) $this->entregado;
    }
}
