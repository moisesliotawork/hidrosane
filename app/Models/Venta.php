<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Facades\Storage;
use App\Enums\EstadoEntrega;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $note_id
 * @property int $customer_id
 * @property int $comercial_id
 * @property int|null $companion_id
 * @property \Illuminate\Support\Carbon $fecha_venta
 * @property string|null $fecha_entrega
 * @property string|null $horario_entrega
 * @property numeric $importe_total
 * @property string $modalidad_pago
 * @property string|null $forma_pago
 * @property numeric|null $cuota_mensual
 * @property int $num_cuotas
 * @property string|null $accesorio_entregado
 * @property string|null $motivo_venta
 * @property string|null $motivo_horario
 * @property bool $interes_art
 * @property array<array-key, mixed>|null $productos_externos
 * @property string|null $precontractual
 * @property string|null $interes_art_detalle
 * @property string|null $observaciones_repartidor
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $dni_anverso
 * @property string|null $dni_reverso
 * @property string|null $documento_titularidad
 * @property string|null $nomina
 * @property string|null $pension
 * @property string|null $contrato_firmado
 * @property-read \App\Models\User $comercial
 * @property-read \App\Models\User|null $companion
 * @property-read \App\Models\Customer $customer
 * @property-read mixed $contrato_firmado_url
 * @property-read mixed $dni_anverso_url
 * @property-read mixed $dni_reverso_url
 * @property-read mixed $documento_titularidad_url
 * @property-read mixed $nomina_url
 * @property-read mixed $pension_url
 * @property-read mixed $precontractual_url
 * @property-read \App\Models\Note $note
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VentaOferta> $ventaOfertas
 * @property-read int|null $venta_ofertas_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereAccesorioEntregado($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereComercialId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereCompanionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereContratoFirmado($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereCuotaMensual($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereDniAnverso($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereDniReverso($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereDocumentoTitularidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereFechaEntrega($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereFechaVenta($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereFormaPago($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereHorarioEntrega($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereImporteTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereInteresArt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereInteresArtDetalle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereModalidadPago($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereMotivoHorario($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereMotivoVenta($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereNomina($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereNoteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereNumCuotas($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereObservacionesRepartidor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta wherePension($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta wherePrecontractual($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereProductosExternos($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
        'forma_pago',
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
        'repartidor_id',
        'de_camino',
        'lat',
        'lng',
    ];

    protected $casts = [
        'fecha_venta' => 'datetime',
        'importe_total' => 'decimal:2',
        'num_cuotas' => 'integer',
        'interes_art' => 'boolean',
        'cuota_mensual' => 'decimal:2',
        'productos_externos' => 'array',
        'de_camino' => 'boolean',

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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($venta) {
            if (!$venta->nro_contrato) {
                // Buscar el nro_contrato más alto
                $max = self::max('nro_contrato');

                // Convertir a número entero (si existe) y sumar 1
                $next = $max ? (int) ltrim($max, '0') + 1 : 1;

                // Rellenar con ceros hasta 5 caracteres
                $venta->nro_contrato = str_pad($next, 5, '0', STR_PAD_LEFT);
            }
        });
    }

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

    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'repartidor_id');
    }

    public function reparto()
    {
        return $this->hasOne(Reparto::class);
    }

    public function refreshEstadoEntrega(): void
    {
        // 1️⃣  Reúne todas las líneas de todos los packs/ofertas
        /** @var Collection<int,array{cantidad:int,cantidad_entregada:int|null}> $lineas */
        $lineas = $this->ventaOfertas()
            ->with('productos')               // eager-load
            ->get()
            ->flatMap->productos;             // cada producto tiene ‘cantidad’ y ‘cantidad_entregada’

        if ($lineas->isEmpty()) {
            return; // sin detalle ⇒ no tocamos nada
        }

        // 2️⃣  Sumas globales
        $vendidas   = $lineas->sum('cantidad');
        $entregadas = $lineas->sum(fn ($l) => (int) $l['cantidad_entregada']);

        // 3️⃣  Determinar nuevo estado
        $estado = match (true) {
            $entregadas === 0              => EstadoEntrega::NO_ENTREGADO,
            $entregadas <  $vendidas       => EstadoEntrega::PARCIAL,
            default                        => EstadoEntrega::COMPLETO,
        };

        // 4️⃣  Actualizar el reparto (si existe y ha cambiado)
        if ($this->reparto && $this->reparto->estado_entrega !== $estado) {
            $this->reparto->update(['estado_entrega' => $estado]);
        }
    }

}
