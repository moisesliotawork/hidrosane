<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreamDailyControl extends Model
{
    use HasFactory;

    protected $table = 'cream_daily_controls';

    protected $fillable = [
        'comercial_id',
        'date',
        'assigned',
        'delivered',
        'remaining',
    ];

    protected $casts = [
        'date' => 'date',
        'assigned' => 'integer',
        'delivered' => 'integer',
        'remaining' => 'integer',
    ];

    protected static function booted()
    {
        // Antes de guardar, recalcula siempre el total por día
        static::saving(function (CreamDailyControl $control) {
            $control->remaining = max(0, (int) $control->assigned - (int) $control->delivered);
        });
    }

    public function comercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comercial_id');
    }

    /**
     * Recalcula delivered desde las ventas (por si prefieres sincronizarlo).
     */
    public function refreshDeliveredFromVentas(bool $save = true): self
    {
        $this->delivered = Venta::query()
            ->where('comercial_id', $this->comercial_id)
            ->whereDate('fecha_venta', $this->date)
            ->where('crema', true)
            ->count();

        if ($save) {
            $this->save();
        }

        return $this;
    }
}
