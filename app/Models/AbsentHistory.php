<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsentHistory extends Model
{
    protected $table = 'historial_ausentes';

    protected $fillable = [
        'note_id',
        'fecha',
        'hora',
        'latitud',
        'longitud',
    ];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }
}

