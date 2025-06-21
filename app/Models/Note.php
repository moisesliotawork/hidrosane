<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\NoteStatus;

class Note extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'first_names',
        'last_names',
        'phone',
        'secondary_phone',
        'email',
        'postal_code',
        'primary_address',
        'secondary_address',
        'parish',
        'status',
        'observations',
        'reschedule_date',
        'reschedule_notes',
        'visit_date',
        'visit_schedule'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'reschedule_date' => 'date',
        'visit_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'status' => NoteStatus::class,
    ];

    /**
     * Get the user that owns the note.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}