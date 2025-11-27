<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeleoperatorMonthlyStat extends Model
{
    protected $table = 'teleoperator_monthly_stats';

    protected $fillable = [
        'teleoperator_id',
        'year',
        'month',
        'quarter',
        'producidas',
        'confirmadas',
        'ventas',
        'nulas',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'quarter' => 'integer',
        'producidas' => 'integer',
        'confirmadas' => 'integer',
        'ventas' => 'integer',
        'nulas' => 'integer',
        'vta_conf' => 'integer',
        'pct_conf' => 'float',
    ];

    public function teleoperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teleoperator_id');
    }

    public function getPeriodLabelAttribute(): string
    {
        return sprintf('%04d-%02d (Q%d)', $this->year, $this->month, $this->quarter);
    }
}
