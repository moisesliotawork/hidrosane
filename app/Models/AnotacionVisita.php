<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnotacionVisita extends Model
{
    use HasFactory;

    protected $table = 'anotaciones_visitas';

    protected $fillable = [
        'nota_id',
        'author_id',
        'asunto',
        'cuerpo'
    ];

    /**
     * Obtiene la nota asociada a esta anotación
     */
    public function nota()
    {
        return $this->belongsTo(Note::class, 'nota_id');
    }

    /**
     * Obtiene el autor de la anotación
     */
    public function autor()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}