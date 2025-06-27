<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Team extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'sales_manager_id',
        'team_leader_id'
    ];

    public function salesManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_manager_id');
    }

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_team')
            ->withTimestamps()
            ->withPivot(['joined_at', 'is_active']);
    }
}