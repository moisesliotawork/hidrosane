<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteReassignmentLog extends Model
{
    protected $fillable = [
        'batch_id',
        'note_id',
        'from_comercial_id',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(NoteReassignmentBatch::class, 'batch_id');
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class, 'note_id');
    }

    public function fromComercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_comercial_id');
    }
}
