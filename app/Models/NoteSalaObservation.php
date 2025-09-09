<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteSalaObservation extends Model
{
    protected $fillable = ['note_id', 'author_id', 'observation'];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
