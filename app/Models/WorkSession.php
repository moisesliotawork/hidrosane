<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    public function scopeLatestPerUser($q, ?string $panelId = null)
    {
        $sub = DB::table('work_sessions')
            ->when($panelId, fn($qq) => $qq->where('panel_id', $panelId))
            ->selectRaw(
                $panelId
                ? 'user_id, panel_id, MAX(start_time) AS max_start'
                : 'user_id, MAX(start_time) AS max_start'
            )
            ->groupBy($panelId ? ['user_id', 'panel_id'] : ['user_id']);

        return $q->joinSub($sub, 'last', function ($join) use ($panelId) {
            $join->on('work_sessions.user_id', '=', 'last.user_id');
            if ($panelId) {
                $join->on('work_sessions.panel_id', '=', 'last.panel_id');
            }
            $join->on('work_sessions.start_time', '=', 'last.max_start');
        })
            ->when($panelId, fn($qq) => $qq->where('work_sessions.panel_id', $panelId))
            ->select('work_sessions.*');
    }


}