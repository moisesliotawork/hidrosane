<?php

namespace App\Models;

use App\Casts\SafeDateCast;
use App\Filament\Support\CustomerPhoneForm;
use App\Support\Filament\FechaNacimientoField;
use App\Models\Scopes\NotMergedScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_names',
        'last_names',
        'phone',
        'secondary_phone',
        'third_phone',
        'phone1_commercial',
        'phone2_commercial',
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
        'merged_into_id',
        'merged_at',
        'merged_by_user_id',

        'postal_code',
        'ciudad',
        'provincia',
        //3 campos nuevos//
        'antiguedad',
        'nombre_empresa',
        'oficio',

        'inhabilitado',
    ];

    protected $casts = [
        'fecha_nac' => SafeDateCast::class,
        'age' => 'integer',
        'edadTelOp' => 'integer',
        'merged_at' => 'datetime',
        'inhabilitado' => 'boolean',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new NotMergedScope());

        static::saving(function (Customer $model) {
            foreach (CustomerPhoneForm::CLIENT_FIELDS as $phoneField) {
                if (! $model->isDirty($phoneField) && ! $model->wasRecentlyCreated) {
                    continue;
                }

                $model->{$phoneField} = CustomerPhoneForm::normalizeDigits($model->{$phoneField});
            }

            if ($model->fecha_nac) {
                $model->age = $model->fecha_nac->age >= 0 ? $model->fecha_nac->age : null;
            } else {
                $model->age = null;
            }
        });

        static::saved(function (Customer $model) {
            $userId = Auth::id();
            if (!$userId) {
                return;
            }

            $phone1Changed = $model->wasChanged('phone1_commercial') || ($model->wasRecentlyCreated && !is_null($model->phone1_commercial));
            $phone2Changed = $model->wasChanged('phone2_commercial') || ($model->wasRecentlyCreated && !is_null($model->phone2_commercial));

            if ($phone1Changed && !is_null($model->phone1_commercial)) {
                CommercialPhoneLog::create([
                    'user_id'     => $userId,
                    'customer_id' => $model->id,
                    'phone_slot'  => 1,
                    'phone_value' => $model->phone1_commercial,
                ]);
            }

            if ($phone2Changed && !is_null($model->phone2_commercial)) {
                CommercialPhoneLog::create([
                    'user_id'     => $userId,
                    'customer_id' => $model->id,
                    'phone_slot'  => 2,
                    'phone_value' => $model->phone2_commercial,
                ]);
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

    /** Evita excepciones al leer fechas corruptas en BD (ej. 19/47/1019). */
    public function safeFechaNac(): ?Carbon
    {
        return FechaNacimientoField::parse($this->getRawOriginal('fecha_nac'));
    }

    /** @return array<string, mixed> */
    public function formFillableAttributes(): array
    {
        $attributes = array_intersect_key(
            $this->getAttributes(),
            array_flip($this->getFillable()),
        );

        $attributes['fecha_nac'] = $this->safeFechaNac()?->format('Y-m-d');

        return $attributes;
    }


    /** Relación: un cliente puede tener muchas ventas */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'customer_id');
    }

    /** Relación: última venta del cliente (para eager loading eficiente) */
    public function latestVenta(): HasOne
    {
        return $this->hasOne(Venta::class, 'customer_id')->latestOfMany();
    }

    /** Retorna nro_cliente_admin de la primera venta o "-" */

    /** Relación: un cliente puede tener muchas notas */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'customer_id');
    }

    /** Relación: última nota del cliente (para eager loading eficiente) */
    public function latestNote(): HasOne
    {
        return $this->hasOne(Note::class, 'customer_id')->latestOfMany();
    }

    /** Última nota del cliente con ubicación GPS del botón DENTRO. */
    public function latestNoteWithDentroGps(): HasOne
    {
        return $this->hasOne(Note::class, 'customer_id')
            ->ofMany(['id' => 'max'], function (Builder $query): void {
                $query->whereNotNull('lat_dentro')
                    ->whereNotNull('lng_dentro')
                    ->where('lat_dentro', '!=', '')
                    ->where('lng_dentro', '!=', '');
            });
    }

    /** Última nota del cliente con ubicación GPS (botón GPS / DE CAMINO). */
    public function latestNoteWithGps(): HasOne
    {
        return $this->hasOne(Note::class, 'customer_id')
            ->ofMany(['id' => 'max'], function (Builder $query): void {
                $query->whereNotNull('lat')
                    ->whereNotNull('lng')
                    ->where('lat', '!=', '')
                    ->where('lng', '!=', '');
            });
    }

    public function hasDentroGps(): bool
    {
        if ($this->relationLoaded('latestNoteWithDentroGps')) {
            return $this->latestNoteWithDentroGps?->hasCoordinatesDentro() ?? false;
        }

        return $this->latestNoteWithDentroGps()->exists();
    }

    public function dentroGpsMapsUrl(): ?string
    {
        $note = $this->relationLoaded('latestNoteWithDentroGps')
            ? $this->latestNoteWithDentroGps
            : $this->latestNoteWithDentroGps()->first();

        if (! $note?->hasCoordinatesDentro()) {
            return null;
        }

        return 'https://www.google.com/maps?q=' . urlencode("{$note->lat_dentro},{$note->lng_dentro}");
    }

    public function hasAnyGps(): bool
    {
        return filled($this->anyGpsMapsUrl());
    }

    public function anyGpsMapsUrl(): ?string
    {
        $dentroUrl = $this->dentroGpsMapsUrl();
        if (filled($dentroUrl)) {
            return $dentroUrl;
        }

        $note = $this->relationLoaded('latestNoteWithGps')
            ? $this->latestNoteWithGps
            : $this->latestNoteWithGps()->first();

        if ($note?->hasCoordinates()) {
            return 'https://www.google.com/maps?q=' . urlencode("{$note->lat},{$note->lng}");
        }

        return null;
    }

    /** Relación: un cliente puede tener muchas observaciones */
    public function customerObservations(): HasMany
    {
        return $this->hasMany(CustomerObservation::class, 'customer_id');
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

    protected function phone1Commercial(): Attribute
    {
        return Attribute::make(
            set: fn($value) => ($value === null || $value === '')
            ? null
            : preg_replace('/\D+/', '', (string) $value),
        );
    }

    protected function phone2Commercial(): Attribute
    {
        return Attribute::make(
            set: fn($value) => ($value === null || $value === '')
            ? null
            : preg_replace('/\D+/', '', (string) $value),
        );
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'merged_into_id');
    }

    public function mergedChildren(): HasMany
    {
        return $this->hasMany(Customer::class, 'merged_into_id')
            ->withoutGlobalScope(NotMergedScope::class);
    }

    public function mergedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'merged_by_user_id');
    }

    public function scopeNotMerged($query)
    {
        return $query->whereNull('merged_into_id');
    }

    public function scopeMerged($query)
    {
        return $query->whereNotNull('merged_into_id');
    }

    public function getIsMergedAttribute(): bool
    {
        return !is_null($this->merged_into_id);
    }


}
