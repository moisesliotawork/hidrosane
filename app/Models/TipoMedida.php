<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nombre
 * @property string $unidad
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductoMedida> $productoMedidas
 * @property-read int|null $producto_medidas_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TipoMedida newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TipoMedida newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TipoMedida query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TipoMedida whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TipoMedida whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TipoMedida whereNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TipoMedida whereUnidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TipoMedida whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TipoMedida extends Model
{
    protected $table = 'tipos_medida';

    protected $fillable = ['nombre', 'unidad'];

    public function productoMedidas()
    {
        return $this->hasMany(ProductoMedida::class);
    }
}
