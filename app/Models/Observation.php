<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Observation extends Model
{
    use HasFactory;

    protected $fillable = [
        'note_id',
        'author_id',
        'observation'
    ];

    public function note()
    {
        return $this->belongsTo(Note::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}