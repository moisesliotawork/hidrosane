<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class CreamDailyControl extends Model
{
    use HasFactory;

    protected $table = 'cream_daily_controls';

    protected $fillable = [
        'comercial_id',
        'date',
        'assigned',           // cremas que se LE ENTREGAN ese día (info)
        'delivered',          // cremas que él entrega a clientes
        'remaining',          // cuántas le quedan EN LA MANO al final del día
        'next_day_to_assign', // cuántas hay que darle MAÑANA
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (CreamDailyControl $control) {
            $dailyQuota = 5;

            // 1) remaining = 5 - delivered (nunca negativo)
            $delivered = (int) $control->delivered;
            $control->remaining = max(0, $dailyQuota - $delivered);

            // 2) next_day_to_assign = 5 - remaining  (= delivered)
            $control->next_day_to_assign = max(0, $dailyQuota - (int) $control->remaining);

            // (Opcional) clamp de assigned solo informativo, por si acaso:
            if ($control->assigned === null) {
                $control->assigned = 0;
            }
        });
    }

    public function comercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comercial_id');
    }
}
