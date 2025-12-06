<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreamTransfer extends Model
{
    protected $fillable = [
        'from_comercial_id',
        'to_comercial_id',
        'date',
        'amount',
        'status',
        'responded_at',
    ];

    protected $casts = [
        'date' => 'date',
        'responded_at' => 'datetime',
    ];

    public function fromComercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_comercial_id');
    }

    public function toComercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_comercial_id');
    }
}
