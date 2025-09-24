<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteConfirmation extends Model
{
    protected $fillable = ['note_id', 'author_id', 'dio_crema', 'observation'];
    protected $casts = ['dio_crema' => 'boolean'];

    public function note(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Note::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'author_id');
    }
}
