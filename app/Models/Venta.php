<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Facades\Storage;
use App\Enums\{EstadoEntrega, EstadoVenta, Financiera};
use Illuminate\Support\Collection;
use App\Enums\VendidoPor;
use Illuminate\Support\Facades\DB;


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
        'estado_venta',
        'financiera',
        'importe_comercial',
        'importe_repartidor',
        'vta_rep',
        'vta_esp',
        'vta_ac',
        'com_venta',
        'com_entrega',
        'com_conpago',
        'pas_comercial',
        'pas_repartidor',
        'repartidor_2',
        'crema',
        'monto_extra',
        'total_final',
        'cuota_final',
    ];

    protected $casts = [
        'fecha_venta' => 'datetime',
        'importe_total' => 'decimal:2',
        'num_cuotas' => 'integer',
        'interes_art' => 'boolean',
        'cuota_mensual' => 'decimal:2',
        'productos_externos' => 'array',
        'de_camino' => 'boolean',
        'importe_comercial' => 'decimal:2',
        'importe_repartidor' => 'decimal:2',
        'vta_rep' => 'integer',
        'vta_esp' => 'integer',
        'vta_ac' => 'integer',
        'pas_comercial' => 'integer',
        'pas_repartidor' => 'integer',
        'com_venta' => 'decimal:2',
        'com_entrega' => 'decimal:2',
        'com_conpago' => 'decimal:2',
        'crema' => 'boolean',
        'monto_extra' => 'decimal:2',
        'total_final' => 'decimal:2',
        'cuota_final' => 'decimal:2',

        'estado_venta' => EstadoVenta::class,
        'financiera' => Financiera::class,

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

    protected $attributes = [
        'crema' => false,
        'monto_extra' => 0,
        'total_final' => 0,
        'cuota_final' => 0,
    ];


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($venta) {
            if (!$venta->nro_contrato) {
                // Buscar el nro_contrato más alto
                $max = self::max('nro_contrato');

                // Convertir a número entero (si existe) y sumar 1
                if ($max) {
                    $next = (int) ltrim($max, '0') + 1;
                } else {
                    $next = 1023; // 🚀 primer contrato en producción
                }

                // Rellenar con ceros hasta 5 caracteres
                $venta->nro_contrato = str_pad($next, 5, '0', STR_PAD_LEFT);
            }
        });

        static::created(function (Venta $venta) {
            if (!$venta->customer_id) {
                return;
            }

            DB::transaction(function () use ($venta) {
                // Bloquea el customer para evitar asignaciones dobles en concurrencia
                /** @var \App\Models\Customer|null $customer */
                $customer = Customer::whereKey($venta->customer_id)->lockForUpdate()->first();

                // Si ya tiene nro_cliente, no hacemos nada
                if (!$customer || !empty($customer->nro_cliente)) {
                    return;
                }

                $next = null;

                // Si tienes la tabla app_counters, úsala (más auditable)
                if (DB::getSchemaBuilder()->hasTable('app_counters')) {
                    $row = DB::table('app_counters')
                        ->where('name', 'nro_cliente')
                        ->lockForUpdate()
                        ->first();

                    $current = $row ? (int) $row->value : 526;

                    // Por si alguien asignó números manuales o quedó un backfill previo
                    $tableMax = (int) (DB::table('customers')
                        ->whereNotNull('nro_cliente')
                        ->max(DB::raw('CAST(nro_cliente AS UNSIGNED)')) ?? 0);

                    $base = max(526, $current, $tableMax);
                    $next = $base + 1;

                    if ($row) {
                        DB::table('app_counters')
                            ->where('name', 'nro_cliente')
                            ->update(['value' => $next, 'updated_at' => now()]);
                    } else {
                        DB::table('app_counters')->insert([
                            'name' => 'nro_cliente',
                            'value' => $next,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } else {
                    // ✅ Sin tabla extra: usa un candado global para evitar carreras
                    DB::statement("SELECT GET_LOCK('nro_cliente_lock', 5)");
                    try {
                        $max = (int) (DB::table('customers')
                            ->whereNotNull('nro_cliente')
                            ->max(DB::raw('CAST(nro_cliente AS UNSIGNED)')) ?? 0);
                        $base = max(526, $max);
                        $next = $base + 1;
                    } finally {
                        DB::statement("SELECT RELEASE_LOCK('nro_cliente_lock')");
                    }
                }

                // Formato 5 cifras con ceros a la izquierda
                $nro = str_pad($next, 5, '0', STR_PAD_LEFT);

                // Guarda sin disparar eventos
                $customer->forceFill(['nro_cliente' => $nro])->saveQuietly();
            });
        });

    }

    public function getEstadoVentaLabelAttribute(): string
    {
        return $this->estado_venta?->label() ?? '';
    }

    public function getEstadoVentaColorAttribute(): ?string
    {
        return $this->estado_venta?->color();
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

    public function repartidor2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'repartidor_2');
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
        $vendidas = $lineas->sum('cantidad');
        $entregadas = $lineas->sum(fn($l) => (int) $l['cantidad_entregada']);

        // 3️⃣  Determinar nuevo estado
        $estado = match (true) {
            $entregadas === 0 => EstadoEntrega::NO_ENTREGADO,
            $entregadas < $vendidas => EstadoEntrega::PARCIAL,
            default => EstadoEntrega::COMPLETO,
        };

        // 4️⃣  Actualizar el reparto (si existe y ha cambiado)
        if ($this->reparto && $this->reparto->estado_entrega !== $estado) {
            $this->reparto->update(['estado_entrega' => $estado]);
        }
    }

    public function recomputarImportesDesdeOfertas(): void
    {
        $this->loadMissing(['ventaOfertas.oferta', 'ventaOfertas.productos']);

        $impCom = 0.0;
        $impRep = 0.0;

        foreach ($this->ventaOfertas as $vo) {
            $precio = (float) ($vo->oferta->precio_base ?? 0);

            $tieneCom = $vo->productos->contains(fn($p) => $p->vendido_por === \App\Enums\VendidoPor::Comercial);
            $tieneRep = $vo->productos->contains(fn($p) => $p->vendido_por === \App\Enums\VendidoPor::Repartidor);

            if ($tieneCom || (!$tieneCom && !$tieneRep)) {
                $impCom += $precio;
            } else {
                $impRep += $precio;
            }
        }

        $this->importe_comercial = $impCom;
        $this->importe_repartidor = $impRep;
        $this->importe_total = $impCom + $impRep;
        $this->cuota_mensual = (int) $this->num_cuotas > 0
            ? round($this->importe_total / (int) $this->num_cuotas, 2)
            : null;

        $this->save();
    }

    protected function esOfertaExcepcional(string $nombre): bool
    {
        $n = mb_strtolower($nombre);

        // "Excepciones" por nombre/descripción
        if (str_contains($n, '1899€ canapé') || str_contains($n, '2099€ canapé') || str_contains($n, '2099€ somier')) {
            return true;
        }
        if (str_contains($n, '3564€ somier') && str_contains($n, 'topper')) {
            return true;
        }

        return false;
    }

    /**
     * Recalcula VTA REP (no-excepcional) y VTA ESP (excepcional) SOLO
     * contando ventaOfertas que tengan al menos un producto vendido por Repartidor.
     *
     * @param  bool  $persist
     * @return $this
     */
    public function recomputarVtasRepYEsp(bool $persist = false): self
    {
        $this->loadMissing(['ventaOfertas.oferta', 'ventaOfertas.productos']);

        $vtaRep = 0;
        $vtaEsp = 0;

        foreach ($this->ventaOfertas as $vo) {
            $tieneLineaRep = $vo->productos->contains(
                fn($p) => ($p->vendido_por ?? null) === VendidoPor::Repartidor
            );

            if (!$tieneLineaRep) {
                continue; // solo contamos ventas del repartidor
            }

            $nombre = (string) ($vo->oferta->nombre ?? '');
            if ($this->esOfertaExcepcional($nombre)) {
                $vtaEsp++;
            } else {
                $vtaRep++;
            }
        }

        $this->vta_rep = $vtaRep;
        $this->vta_esp = $vtaEsp;

        if ($persist) {
            $this->save();
        }

        return $this;
    }

    /**
     * Recalcula VTA AC como suma de VTA REP + VTA ESP.
     *
     * @param  bool  $persist  Si true, guarda el cambio en BD.
     * @return $this
     */
    public function recalcularVtasAcumuladas(bool $persist = false): self
    {
        $this->vta_ac = (int) ($this->vta_rep ?? 0) + (int) ($this->vta_esp ?? 0);

        if ($persist) {
            $this->save();
        }

        return $this;
    }


    /**
     * Determina las comisiones (venta, entrega) según el nombre del pack/oferta.
     * Retorna ['venta' => float, 'entrega' => float].
     */
    protected function reglaComisionPorNombre(string $nombre): array
    {
        $n = mb_strtolower($nombre);

        // ───── Excepciones por nombre exacto ────────────────────────────────
        if (str_contains($n, '1899€ canapé')) {
            return ['venta' => 200.0, 'entrega' => 15.0];
        }
        if (str_contains($n, '2099€ canapé') || str_contains($n, '2099€ somier')) {
            return ['venta' => 220.0, 'entrega' => 15.0];
        }
        if (str_contains($n, '3564€ somier') && str_contains($n, 'topper')) {
            return ['venta' => 240.0, 'entrega' => 30.0];
        }

        // ───── Clasificaciones principales por palabra clave ────────────────
        // Media venta
        if (str_contains($n, 'media.vta')) {
            return ['venta' => 100.0, 'entrega' => 7.50];
        }

        // Oferta reparto
        if (str_contains($n, 'oferta reparto')) {
            return ['venta' => 100.0, 'entrega' => 15.0];
        }

        // Triplete especial
        if (str_contains($n, 'tripl')) { // 'tripl.esp'
            return ['venta' => 600.0, 'entrega' => 45.0];
        }

        // Cuádruple especial
        if (str_contains($n, 'cuadrup')) { // 'cuadrup.esp'
            return ['venta' => 800.0, 'entrega' => 60.0];
        }

        // Doblete
        if (str_contains($n, 'doblete')) {
            // Caso 3564€ (2 x 1899 casi) => 440 venta, 30 entrega
            if (str_contains($n, '3564')) {
                return ['venta' => 440.0, 'entrega' => 30.0];
            }
            // Caso 3798€ (= 2 x 1899 exacto) => 400 venta, 30 entrega
            if (str_contains($n, '3798')) {
                return ['venta' => 400.0, 'entrega' => 30.0];
            }
            // Fallback doblete genérico: asume 2 * sencilla (200) => 400/30
            return ['venta' => 400.0, 'entrega' => 30.0];
        }

        // Sencilla
        if (str_contains($n, 'sencilla')) {
            if (str_contains($n, '4pts')) {
                return ['venta' => 200.0, 'entrega' => 15.0];
            }
            // 5/7/8 pts
            if (str_contains($n, '5pts') || str_contains($n, '7pts') || str_contains($n, '8pts')) {
                return ['venta' => 220.0, 'entrega' => 15.0];
            }
            // Fallback sencilla
            return ['venta' => 220.0, 'entrega' => 15.0];
        }

        // Si no matchea nada, sin comisión.
        return ['venta' => 0.0, 'entrega' => 0.0];
    }

    /**
     * Calcula y guarda:
     *  - com_entrega: suma de entrega de TODAS las ventaOfertas
     *  - com_venta  : suma de venta SOLO de ventaOfertas con algún producto vendido_por Repartidor
     *
     * @param  bool  $persist  Si true, persiste en BD.
     * @return $this
     */
    public function calcularComisiones(bool $persist = true): self
    {
        $this->loadMissing(['ventaOfertas.oferta', 'ventaOfertas.productos']);

        $totalEntrega = 0.0;
        $totalVentaRepartidor = 0.0;

        foreach ($this->ventaOfertas as $vo) {
            $ofertaNombre = (string) ($vo->oferta->nombre ?? '');
            $regla = $this->reglaComisionPorNombre($ofertaNombre);

            // Comisión de entrega: SIEMPRE suma (no importa quién vendió)
            $totalEntrega += (float) $regla['entrega'];

            // Comisión de venta de REPARTIDOR: solo si hay al menos un producto vendido por Repartidor
            $tieneLineaDelRepartidor = $vo->productos->contains(
                fn($p) => ($p->vendido_por ?? null) === VendidoPor::Repartidor
            );

            if ($tieneLineaDelRepartidor) {
                $totalVentaRepartidor += (float) $regla['venta'];
            }
        }

        // Redondeos a 2 decimales
        $this->com_entrega = round($totalEntrega, 2);
        $this->com_venta = round($totalVentaRepartidor, 2);

        if ($persist) {
            $this->save();
        }

        return $this;
    }

    public function calcularPas(bool $persist = true): self
    {
        $this->loadMissing(['ventaOfertas.oferta', 'ventaOfertas.productos']);

        $pasR = 0;
        $pasC = 0;

        foreach ($this->ventaOfertas as $vo) {
            $base = (int) ($vo->oferta->puntos_base ?? 0);
            $cerrados = (int) ($vo->puntos ?? $base);
            $diff = $cerrados - $base;

            if ($diff < 0) {
                continue; // no sumamos negativos
            }

            // ¿Hay líneas del repartidor en esta ventaOferta?
            $tieneRep = $vo->productos->contains(
                fn($p) => ($p->vendido_por ?? null) === \App\Enums\VendidoPor::Repartidor
            );

            // ¿Hay líneas del comercial en esta ventaOferta?
            $tieneCom = $vo->productos->contains(
                fn($p) => ($p->vendido_por ?? null) === \App\Enums\VendidoPor::Comercial
            );

            // Suma independiente para cada “lado”
            if ($tieneRep) {
                $pasR += $diff;
            }
            if ($tieneCom) {
                $pasC += $diff;
            }

            /*
            // ───── Alternativa EXCLUSIVA (descomenta si NO quieres doble conteo) ────
            if ($tieneRep && !$tieneCom) {
                $pasR += $diff;
            } elseif ($tieneCom && !$tieneRep) {
                $pasC += $diff;
            } else {
                // Si hay ambos, decide prioridad:
                // $pasR += $diff;  // o $pasC += $diff;
            }
            */
        }

        $this->pas_repartidor = (int) $pasR;
        $this->pas_comercial = (int) $pasC;

        if ($persist) {
            $this->save();
        }

        return $this;
    }

}
