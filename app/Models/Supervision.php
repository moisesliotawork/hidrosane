<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supervision extends Model
{
    use HasFactory;

    protected $table = 'supervisiones';

    protected $fillable = [
        'supervisor_id',
        'supervisado_id',
        'author_id',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    // ========== Relaciones ==========
    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function supervisado()
    {
        return $this->belongsTo(User::class, 'supervisado_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
