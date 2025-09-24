<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\NoteStatus;
use App\Enums\FuenteNotas;
use App\Enums\EstadoTerminal;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property NoteStatus $status
 * @property \Illuminate\Support\Carbon|null $assignment_date
 * @property bool $de_camino
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\Observation> $observations
 * @property \Illuminate\Support\Carbon|null $visit_date
 * @property string|null $visit_schedule
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $customer_id
 * @property int|null $comercial_id
 * @property FuenteNotas $fuente
 * @property string $nro_nota
 * @property string|null $lat
 * @property string|null $lng
 * @property bool $show_phone
 * @property EstadoTerminal $estado_terminal
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AnotacionVisita> $anotacionesVisitas
 * @property-read int|null $anotaciones_visitas_count
 * @property-read \App\Models\User|null $comercial
 * @property-read \App\Models\Customer $customer
 * @property-read mixed $comercial_empleado
 * @property-read array|null $coordinates
 * @property-read string $fecha_asig
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Observation> $myObservations
 * @property-read int|null $my_observations_count
 * @property-read int|null $observations_count
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Venta|null $venta
 * @method static \Database\Factories\NoteFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereAssignmentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereComercialId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereDeCamino($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereEstadoTerminal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereFuente($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereLat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereLng($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereNroNota($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereObservations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereShowPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereVisitDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Note whereVisitSchedule($value)
 * @mixin \Eloquent
 */
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
        'lat_dentro',
        'lng_dentro',
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
        'show_phone' => false,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($note) {
            if (empty($note->nro_nota)) {
                // Buscar el nro_nota más alto
                $max = self::max('nro_nota');

                if ($max) {
                    // Si ya hay notas en BD, tomamos el último +1
                    $next = (int) ltrim($max, '0') + 1;
                } else {
                    // 🚀 Primer número en producción
                    $next = 4204;
                }

                // Guardar con padding a 5 dígitos
                $note->nro_nota = str_pad($next, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    protected static function booted()
    {
        static::saving(function (Note $note) {
            // Consideramos "reasignación" si cambia el comercial o la fecha de asignación
            $reasignacion = $note->isDirty('comercial_id') || $note->isDirty('assignment_date');

            if ($reasignacion && $note->estado_terminal === \App\Enums\EstadoTerminal::SALA) {
                $note->estado_terminal = \App\Enums\EstadoTerminal::SIN_ESTADO; // ''
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


    public function getCoordinatesDentroAttribute()
    {
        if ($this->lat_dentro && $this->lng_dentro) {
            return [
                'lat' => $this->lat_dentro,
                'lng' => $this->lng_dentro,
            ];
        }
        return null;
    }

    public function hasCoordinatesDentro(): bool
    {
        return !empty($this->lat_dentro) && !empty($this->lng_dentro);
    }

    /**
     * Determina si se puede mostrar el teléfono del cliente
     *
     * @return bool
     */
    public function canShowPhone(): bool
    {
        $user = auth()->user();

        // Si es jefe de equipo, siempre puede ver teléfonos
        if ($user && $user->hasRole('team_leader')) {
            return true;
        }

        // Comportamiento normal: respeta el flag de la nota
        return (bool) $this->show_phone;
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

    public function ausencias()
    {
        return $this->hasMany(\App\Models\AbsentHistory::class, 'note_id');
    }

    public function nullReasons()
    {
        return $this->hasMany(\App\Models\NoteNullReason::class);
    }

    public function getObservacionesEnTextoAttribute(): string
    {
        $items = [];

        // Tabla
        foreach ($this->observations()->orderBy('created_at')->get() as $ob) {
            $t = trim((string) ($ob->observation ?? ''));
            if ($t !== '')
                $items[] = $t;
        }

        // JSON legacy
        $legacy = $this->getAttribute('observations');
        if (is_array($legacy)) {
            foreach ($legacy as $row) {
                $t = is_array($row) ? ($row['observation'] ?? null) : $row;
                $t = trim((string) $t);
                if ($t !== '')
                    $items[] = $t;
            }
        }

        return count($items) ? '• ' . implode("\n• ", $items) : '';
    }

    public function observacionesSala()
    {
        return $this->hasMany(\App\Models\NoteSalaObservation::class, 'note_id');
    }

    public function confirmations()
    {
        return $this->hasMany(\App\Models\NoteConfirmation::class, 'note_id');
    }
}