<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'uid',
        'title',
        'state_id'
    ];

    protected $casts = [
        'uid' => 'string',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uid = $model->uid ?? (string) \Illuminate\Support\Str::uuid();
        });
    }

    /**
     * Get the state that owns the city.
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }
}