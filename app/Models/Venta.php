<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Facades\Storage;

class Venta extends Model
{
    use HasFactory;

    protected $fillable = [
        'note_id',
        'customer_id',
        'comercial_id',
        'companion_id',
        'fecha_venta',
        'importe_total',
        'modalidad_pago',
        'num_cuotas',
        'accesorio_entregado',
        'motivo_venta',
        'motivo_horario',
        'interes_art',
        'interes_art_detalle',
        'observaciones_repartidor',
        'cuota_mensual',
        'fecha_entrega',
        'horario_entrega',
        'productos_externos',
        'precontractual',
        'dni_anverso',
        'dni_reverso',
        'documento_titularidad',
        'nomina',
        'pension',
        'contrato_firmado',
        'interes_art_detalle',
    ];

    protected $casts = [
        'fecha_venta' => 'datetime',
        'importe_total' => 'decimal:2',
        'num_cuotas' => 'integer',
        'interes_art' => 'boolean',
        'cuota_mensual' => 'decimal:2',
        'productos_externos' => 'array',

    ];

    protected $appends = [
        'precontractual_url',
        'dni_anverso_url',
        'dni_reverso_url',
        'documento_titularidad_url',
        'nomina_url',
        'pension_url',
        'contrato_firmado_url',
    ];

    public function getPrecontractualUrlAttribute()
    {
        return $this->urlFor('precontractual');
    }
    public function getDniAnversoUrlAttribute()
    {
        return $this->urlFor('dni_anverso');
    }
    public function getDniReversoUrlAttribute()
    {
        return $this->urlFor('dni_reverso');
    }
    public function getDocumentoTitularidadUrlAttribute()
    {
        return $this->urlFor('documento_titularidad');
    }
    public function getNominaUrlAttribute()
    {
        return $this->urlFor('nomina');
    }
    public function getPensionUrlAttribute()
    {
        return $this->urlFor('pension');
    }
    public function getContratoFirmadoUrlAttribute()
    {
        return $this->urlFor('contrato_firmado');
    }

    /* ---------- Helper ---------- */
    protected function urlFor(string $field): ?string
    {
        return $this->$field
            ? Storage::disk('public')->url($this->$field)
            : null;
    }

    /* ---------- Relaciones ---------- */

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function comercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comercial_id');
    }

    public function companion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'companion_id');
    }

    public function ventaOfertas(): HasMany   // alias “ofertas()” si lo prefieres
    {
        return $this->hasMany(VentaOferta::class);
    }
}
