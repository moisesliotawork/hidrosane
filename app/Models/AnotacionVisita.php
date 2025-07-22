<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $nota_id
 * @property int $author_id
 * @property string $asunto
 * @property string $cuerpo
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $autor
 * @property-read \App\Models\Note $nota
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnotacionVisita newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnotacionVisita newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnotacionVisita query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnotacionVisita whereAsunto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnotacionVisita whereAuthorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnotacionVisita whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnotacionVisita whereCuerpo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnotacionVisita whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnotacionVisita whereNotaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnotacionVisita whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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