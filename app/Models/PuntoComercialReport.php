<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PuntoComercialReport extends Model
{
    protected $fillable = [
        'team_leader_id',
        'report_date',
        'texto',
        'lat',
        'lng',
        'submitted_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'submitted_at' => 'datetime',
    ];

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function mapsUrl(): ?string
    {
        if (blank($this->lat) || blank($this->lng)) {
            return null;
        }

        return 'https://www.google.com/maps?q=' . urlencode("{$this->lat},{$this->lng}");
    }
}
