<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteNullReason extends Model
{
    protected $fillable = [
        'note_id',
        'comercial_id',
        'companion_id',
        'reason',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function comercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comercial_id');
    }
    public function companion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'companion_id');
    }
}
