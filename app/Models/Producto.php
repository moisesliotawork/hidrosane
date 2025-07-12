<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'puntos',
    ];

    protected $casts = [
        'puntos' => 'integer',
    ];

    public function medidas()
    {
        return $this->hasMany(ProductoMedida::class);
    }

}