<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $producto_id
 * @property int $tipo_medida_id
 * @property string $valor
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Producto $producto
 * @property-read \App\Models\TipoMedida $tipoMedida
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductoMedida newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductoMedida newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductoMedida query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductoMedida whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductoMedida whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductoMedida whereProductoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductoMedida whereTipoMedidaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductoMedida whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductoMedida whereValor($value)
 * @mixin \Eloquent
 */
class ProductoMedida extends Model
{
    protected $table = 'producto_medida';

    protected $fillable = ['producto_id', 'tipo_medida_id', 'valor'];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function tipoMedida()
    {
        return $this->belongsTo(TipoMedida::class);
    }
}
