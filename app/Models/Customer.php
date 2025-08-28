<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $first_names
 * @property string $last_names
 * @property string $phone
 * @property string|null $secondary_phone
 * @property string|null $email
 * @property string|null $dni
 * @property string|null $fecha_nac
 * @property string|null $iban
 * @property string|null $tipo_vivienda
 * @property string|null $estado_civil
 * @property string|null $situacion_laboral
 * @property string|null $ingresos_rango
 * @property int|null $num_hab_casa
 * @property int|null $age
 * @property int|null $postal_code_id
 * @property string $primary_address
 * @property string|null $secondary_address
 * @property string|null $parish
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $name
 * @property-read \App\Models\PostalCode|null $postalCode
 * @method static \Database\Factories\CustomerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereAge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereDni($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereEstadoCivil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereFechaNac($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereFirstNames($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereIban($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereIngresosRango($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereLastNames($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereNumHabCasa($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereParish($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer wherePostalCodeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer wherePrimaryAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereSecondaryAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereSecondaryPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereSituacionLaboral($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereTipoVivienda($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_names',
        'last_names',
        'phone',
        'secondary_phone',
        'email',
        'age',
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
    ];

    protected static function booted(): void
    {
        static::created(function (Customer $customer) {
            // Si ya viene seteado (p.ej., import), no lo recalculamos
            if (!empty($customer->nro_cliente)) {
                return;
            }

            // Crea el string de 5 cifras: id + 525 con ceros a la izquierda
            $nro = str_pad($customer->id + 525, 5, '0', STR_PAD_LEFT);

            // Guardar sin disparar eventos
            $customer->forceFill(['nro_cliente' => $nro])->saveQuietly();
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
