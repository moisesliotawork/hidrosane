<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $panel_id
 * @property \Illuminate\Support\Carbon $start_time
 * @property \Illuminate\Support\Carbon|null $end_time
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $ip_address
 * @property string|null $device_info
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkSession active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkSession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkSession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkSession query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkSession whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkSession whereDeviceInfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkSession whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkSession whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkSession whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkSession whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkSession whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkSession wherePanelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkSession whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkSession whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkSession whereUserId($value)
 * @mixin \Eloquent
 */
class WorkSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'panel_id',
        'start_time',
        'end_time',
        'latitude',
        'longitude',
        'ip_address',
        'device_info'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('end_time');
    }

    public function isActive()
    {
        return is_null($this->end_time);
    }
}