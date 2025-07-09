<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoMedida extends Model
{
    protected $table = 'tipos_medida';

    protected $fillable = ['nombre', 'unidad'];

    public function productoMedidas()
    {
        return $this->hasMany(ProductoMedida::class);
    }
}
