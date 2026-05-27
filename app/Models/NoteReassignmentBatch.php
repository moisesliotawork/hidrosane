<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NoteReassignmentBatch extends Model
{
    protected $fillable = [
        'author_id',
        'to_comercial_id',
        'to_reten',
        'reassigned_at',
    ];

    protected $casts = [
        'to_reten'      => 'boolean',
        'reassigned_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function toComercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_comercial_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(NoteReassignmentLog::class, 'batch_id');
    }
}
