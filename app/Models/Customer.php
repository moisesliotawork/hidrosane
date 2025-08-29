<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_names',
        'last_names',
        'phone',
        'secondary_phone',
        'email',
        'postal_code_id',
        'primary_address',
        'secondary_address',
        'parish',
        'dni',
        'fecha_nac',
        'iban',
        'tipo_vivienda',
        'estado_civil',
        'situacion_laboral',
        'ingresos_rango',
        'num_hab_casa',
        'ayuntamiento',
    ];

    protected $casts = [
        'fecha_nac' => 'date:Y-m-d',
        'age' => 'integer',
    ];

    protected static function booted()
    {
        static::saving(function (Customer $model) {
            if ($model->fecha_nac) {
                try {
                    $age = Carbon::parse($model->fecha_nac)->age;
                    $model->age = $age >= 0 ? $age : null; // evita negativos por fechas futuras
                } catch (\Throwable $e) {
                    $model->age = null;
                }
            } else {
                $model->age = null;
            }
        });
    }

    public function name(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->first_names . ' ' . $this->last_names,
        );
    }

    public function postalCode(): BelongsTo
    {
        return $this->belongsTo(PostalCode::class, 'postal_code_id');
    }
}
