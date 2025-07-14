<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\NoteStatus;
use App\Enums\FuenteNotas;
use App\Enums\EstadoTerminal;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Note extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'customer_id',
        'comercial_id',
        'fuente',
        'nro_nota',
        'status',
        'observations',
        'visit_date',
        'de_camino',
        'visit_schedule',
        'assignment_date',
        'lat',
        'lng',
        'show_phone',
        'estado_terminal',
        'productos_externos',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'visit_date' => 'datetime',
        'assignment_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'status' => NoteStatus::class,
        'fuente' => FuenteNotas::class,
        'de_camino' => 'boolean',
        'show_phone' => 'boolean',
        'observations' => 'array',
        'productos_externos' => 'string',
    ];

    protected $attributes = [
        'fuente' => 'CALLE',
        'estado_terminal' => null,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($note) {
            // Generar número de nota automáticamente
            if (empty($note->nro_nota)) {
                do {
                    $nroNota = str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
                } while (self::where('nro_nota', $nroNota)->exists());

                $note->nro_nota = $nroNota;
            }
        });
    }

    /**
     * Get the user that owns the note.
     */
    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function comercial()
    {
        return $this->belongsTo(User::class, 'comercial_id')->withDefault([
            'name' => 'Sin Asignar'
        ]);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Obtiene la fecha de asignación formateada o "Sin fecha" si es null
     *
     * @return string
     */
    public function getFechaAsigAttribute()
    {
        return $this->assignment_date
            ? $this->assignment_date->format('d/m/Y')
            : 'Sin fecha';
    }


    public function getComercialEmpleadoAttribute()
    {
        if (!$this->comercial_id) {
            return 'Sin Com.';
        }

        return $this->comercial->empleado_id ?? 'Comercial no encontrado';
    }

    public function postalCode()
    {
        return $this->through('customer')->has('postalCode');
    }

    /**
     * Obtiene las coordenadas como array [lat, lng]
     *
     * @return array|null
     */
    public function getCoordinatesAttribute()
    {
        if ($this->lat && $this->lng) {
            return [
                'lat' => $this->lat,
                'lng' => $this->lng
            ];
        }
        return null;
    }

    /**
     * Verifica si la nota tiene coordenadas
     *
     * @return bool
     */
    public function hasCoordinates()
    {
        return !empty($this->lat) && !empty($this->lng);
    }

    /**
     * Determina si se puede mostrar el teléfono del cliente
     *
     * @return bool
     */
    public function canShowPhone()
    {
        return $this->show_phone;
    }

    public function observations()
    {
        return $this->hasMany(Observation::class);
    }

    public function myObservations()
    {
        return $this->hasMany(Observation::class)
            ->where('author_id', auth()->id());
    }

    public function anotacionesVisitas()
    {
        return $this->hasMany(AnotacionVisita::class, 'nota_id');
    }

    // En tu modelo Note
    public function getEstadoTerminalAttribute($value): EstadoTerminal
    {
        // Si el valor es una instancia del Enum, lo retornamos directamente
        if ($value instanceof EstadoTerminal) {
            return $value;
        }

        // Si es null o string vacío, retornamos SIN_ESTADO
        if ($value === null || $value === '') {
            return EstadoTerminal::SIN_ESTADO;
        }

        // Intentamos convertir el string al Enum
        try {
            return EstadoTerminal::from($value);
        } catch (\ValueError $e) {
            return EstadoTerminal::SIN_ESTADO;
        }
    }

    public function setEstadoTerminalAttribute($value): void
    {
        // Si es una instancia del Enum, guardamos su valor
        if ($value instanceof EstadoTerminal) {
            $this->attributes['estado_terminal'] = $value->value;
            return;
        }

        // Si es null o string vacío, guardamos como string vacío
        if ($value === null || $value === '') {
            $this->attributes['estado_terminal'] = '';
            return;
        }

        // Guardamos el valor directamente (asumimos que es un string válido)
        $this->attributes['estado_terminal'] = $value;
    }
    public function venta()
    {
        return $this->hasOne(\App\Models\Venta::class, 'note_id');
    }


}