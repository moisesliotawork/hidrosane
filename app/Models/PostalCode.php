<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uid
 * @property string $code
 * @property int $city_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\City $city
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostalCode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostalCode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostalCode query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostalCode whereCityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostalCode whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostalCode whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostalCode whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostalCode whereUid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostalCode whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PostalCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'uid',
        'code',
        'city_id'
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
     * Get the city that owns the postal code.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}