<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'medida1',
        'medida2',
        'puntos'
    ];

    protected $casts = [
        'puntos' => 'integer',
        'medida2' => 'integer',
        'medida1' => 'integer'
    ];
}