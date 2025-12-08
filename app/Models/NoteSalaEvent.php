<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NoteSalaEvent extends Model
{
    use HasFactory;

    protected $table = 'note_sala_events';

    protected $fillable = [
        'note_id',
        'sent_by_user_id',
        'via',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function note()
    {
        return $this->belongsTo(Note::class);
    }

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
