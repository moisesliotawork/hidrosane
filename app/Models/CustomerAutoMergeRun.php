<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAutoMergeRun extends Model
{
    protected $fillable = [
        'merged_count',
        'failed_count',
        'trigger',
        'failures',
        'ran_at',
    ];

    protected $casts = [
        'merged_count' => 'integer',
        'failed_count' => 'integer',
        'failures' => 'array',
        'ran_at' => 'datetime',
    ];

    public static function latestRun(): ?self
    {
        return static::query()
            ->orderByDesc('ran_at')
            ->orderByDesc('id')
            ->first();
    }
}
