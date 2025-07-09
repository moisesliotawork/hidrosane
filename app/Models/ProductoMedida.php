<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
