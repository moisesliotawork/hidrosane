<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'edadTelOp',
    ];

    protected $casts = [
        'fecha_nac' => 'date:Y-m-d',
        'age' => 'integer',
        'edadTelOp' => 'integer',
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


    /** Relación: un cliente puede tener muchas ventas */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'customer_id');
    }

    /** Retorna nro_cliente_admin de la primera venta o "-" */
    public function firstVentaClienteAdmin(): string
    {
        return $this->ventas()
            // Solo ventas con nro_cliente_admin presente
            ->whereNotNull('nro_cliente_adm')
            ->where('nro_cliente_adm', '!=', '')
            // Orden: primero las que sí tienen created_at, luego por fecha y como fallback por id
            ->orderByRaw('CASE WHEN created_at IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->value('nro_cliente_adm') ?? '-';
    }

}
