<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\NoteStatus;
use App\Enums\FuenteNotas;
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
        'reschedule_date',
        'reschedule_notes',
        'visit_date',
        'visit_schedule'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'reschedule_date' => 'date',
        'visit_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'status' => NoteStatus::class,
        'fuente' => FuenteNotas::class,
    ];

    protected $attributes = [
        'fuente' => 'CALLE',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($note) {
            // Generar número de nota automáticamente
            if (empty($note->nro_nota)) {
                do {
                    $nroNota = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
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
}