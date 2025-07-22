<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $note_id
 * @property int $author_id
 * @property string|null $observation
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $author
 * @property-read \App\Models\Note $note
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Observation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Observation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Observation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Observation whereAuthorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Observation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Observation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Observation whereNoteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Observation whereObservation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Observation whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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