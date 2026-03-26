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
        'third_phone',
        'email',
        'nro_piso',
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

        'postal_code',
        'ciudad',
        'provincia',
        //3 campos nuevos//
        'antiguedad',
        'nombre_empresa',
        'oficio',
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
            get: function () {
                $full = trim(($this->first_names ?? '') . ' ' . ($this->last_names ?? ''));
                // por si acaso, colapsa espacios y recorta
                $full = preg_replace('/\s+/u', ' ', $full);
                return $full;
            },
        );
    }


    /** Relación: un cliente puede tener muchas ventas */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'customer_id');
    }

    /** Retorna nro_cliente_admin de la primera venta o "-" */

/** Relación: un cliente puede tener muchas notas */
public function notes(): HasMany
{
    return $this->hasMany(Note::class, 'customer_id');
}





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

    protected function firstNames(): Attribute
    {
        return Attribute::set(fn($v) => self::properCase($v));
    }

    protected function lastNames(): Attribute
    {
        return Attribute::set(fn($v) => self::properCase($v));
    }

    /**
     * Normaliza mayúsculas/minúsculas en nombres
     * con reglas castellanas.
     */
    protected static function properCase(?string $s): ?string
    {
        if ($s === null)
            return null;

        // Limpiar espacios de más
        $s = preg_replace('/\s+/u', ' ', trim($s));

        // Poner todo en "Title Case"
        $t = mb_convert_case(mb_strtolower($s, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');

        // Palabras que deben quedar minúsculas salvo si son la primera
        $particles = [
            'De',
            'Del',
            'La',
            'Las',
            'Los',
            'Y',
            'Da',
            'Do',
            'Dos',
            'Das',
            'Di',
            'Du',
            'Von',
            'Van'
        ];

        $words = explode(' ', $t);
        foreach ($words as $i => &$w) {
            if ($i > 0 && in_array($w, $particles, true)) {
                $w = mb_strtolower($w, 'UTF-8');
            }
        }

        return implode(' ', $words);
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn($value) => $value === null ? null : preg_replace('/\D+/', '', (string) $value),
        );
    }

    protected function secondaryPhone(): Attribute
    {
        return Attribute::make(
            set: fn($value) => ($value === null || $value === '') ? null : preg_replace('/\D+/', '', (string) $value),
        );
    }

    protected function thirdPhone(): Attribute
    {
        return Attribute::make(
            set: fn($value) => ($value === null || $value === '')
            ? null
            : preg_replace('/\D+/', '', (string) $value),
        );
    }


}
