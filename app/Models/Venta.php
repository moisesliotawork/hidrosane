<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

use App\Enums\{EstadoEntrega, EstadoVenta, Financiera, MesesEnum, VendidoPor};

// Si no estaban importadas explícitamente
use App\Models\{Customer, Note, VentaOferta, Reparto, User, Producto, Oferta};

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
        'entrada',
        'mostrar_ingresos',
        'mostrar_tipo_vivienda',
        'mostrar_situacion_lab',
        'mes_contr',
        'nro_contr_adm',
        'nro_cliente_adm',
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
        'entrada' => 'decimal:2',
        'mostrar_ingresos' => 'boolean',
        'mostrar_tipo_vivienda' => 'boolean',
        'mostrar_situacion_lab' => 'boolean',
        'mes_contr' => MesesEnum::class,
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
        // ❌ Sin defaults 0 para total_final / cuota_final (evita pisadas)
        'entrada' => 0,
        'mostrar_tipo_vivienda' => true,
        'mostrar_situacion_lab' => true,
    ];

    /* ==================== EVENTOS ==================== */

    protected static function boot()
    {
        parent::boot();

        // Autogenerar nro_contrato
        static::creating(function ($venta) {
            if (!$venta->nro_contrato) {
                $max = self::max('nro_contrato');
                $next = $max ? (int) ltrim($max, '0') + 1 : 1023;
                $venta->nro_contrato = str_pad($next, 5, '0', STR_PAD_LEFT);
            }
        });

        // Asignar nro_cliente_adm al crear (si procede)
        static::created(function (Venta $venta) {
            if (!$venta->customer_id)
                return;

            DB::transaction(function () use ($venta) {
                /** @var \App\Models\Customer|null $customer */
                $customer = Customer::whereKey($venta->customer_id)->lockForUpdate()->first();
                if (!$customer || !empty($customer->nro_cliente))
                    return;

                $next = null;

                if (DB::getSchemaBuilder()->hasTable('app_counters')) {
                    $row = DB::table('app_counters')
                        ->where('name', 'nro_cliente')
                        ->lockForUpdate()
                        ->first();

                    $current = $row ? (int) $row->value : 526;

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

                $nro = str_pad($next, 5, '0', STR_PAD_LEFT);
                $customer->forceFill(['nro_cliente' => $nro])->saveQuietly();
            });
        });
    }

    protected static function booted()
    {
        // Totales simples (no dependen de relaciones) antes de guardar
        static::saving(function (Venta $venta) {
            $venta->recalcularTotalesDerivados();
        });

        // Recalculos que dependen de ventaOfertas/productos una vez guardado todo
        static::saved(function (Venta $venta) {
            $venta->recomputarImportesDesdeOfertas(false)
                ->calcularComisiones(false)
                ->recomputarVtasRepYEsp(false)
                ->recalcularVtasAcumuladas(false)
                ->calcularPas(false)
                ->recalcularTotalesDerivados();

            // Persistir sin disparar eventos para evitar recursión
            $venta->withoutEvents(function () use ($venta) {
                $venta->save();
            });
        });
    }

    /* ==================== ACCESSORS URL ==================== */

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

    protected function urlFor(string $field): ?string
    {
        return $this->$field ? Storage::disk('public')->url($this->$field) : null;
    }

    /* ==================== RELACIONES ==================== */

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
    public function ventaOfertas(): HasMany
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

    /* ==================== LÓGICA DE ESTADO ENTREGA ==================== */

    public function refreshEstadoEntrega(): void
    {
        $lineas = $this->ventaOfertas()
            ->with('productos')
            ->get()
            ->flatMap->productos;

        if ($lineas->isEmpty())
            return;

        $vendidas = $lineas->sum('cantidad');
        $entregadas = $lineas->sum(fn($l) => (int) $l['cantidad_entregada']);

        $estado = match (true) {
            $entregadas === 0 => EstadoEntrega::NO_ENTREGADO,
            $entregadas < $vendidas => EstadoEntrega::PARCIAL,
            default => EstadoEntrega::COMPLETO,
        };

        if ($this->reparto && $this->reparto->estado_entrega !== $estado) {
            $this->reparto->update(['estado_entrega' => $estado]);
        }
    }

    /* ==================== CÁLCULOS ==================== */

    public function recalcularTotalesDerivados(): self
    {
        $entrada = (float) ($this->entrada ?? 0);
        $montoExtra = (float) ($this->monto_extra ?? 0);
        $importe = (float) ($this->importe_total ?? 0);

        $this->total_final = round(($importe - $entrada) + $montoExtra, 2);

        $n = (int) ($this->num_cuotas ?? 0);
        $this->cuota_final = $n > 0 ? round($this->total_final / $n, 2) : null;

        return $this;
    }

    public function recomputarImportesDesdeOfertas(bool $persist = true): self
    {
        $this->loadMissing(['ventaOfertas.oferta', 'ventaOfertas.productos']);

        $impCom = 0.0;
        $impRep = 0.0;

        foreach ($this->ventaOfertas as $vo) {
            $precio = (float) ($vo->oferta->precio_base ?? 0);
            $tieneCom = $vo->productos->contains(fn($p) => ($p->vendido_por ?? null) === VendidoPor::Comercial);
            $tieneRep = $vo->productos->contains(fn($p) => ($p->vendido_por ?? null) === VendidoPor::Repartidor);

            if ($tieneCom || (!$tieneCom && !$tieneRep))
                $impCom += $precio;
            else
                $impRep += $precio;
        }

        $this->importe_comercial = $impCom;
        $this->importe_repartidor = $impRep;
        $this->importe_total = $impCom + $impRep;
        $this->cuota_mensual = (int) $this->num_cuotas > 0
            ? round($this->importe_total / (int) $this->num_cuotas, 2)
            : null;

        if ($persist)
            $this->saveQuietly();
        return $this;
    }

    protected function esOfertaExcepcional(string $nombre): bool
    {
        $n = mb_strtolower($nombre);
        if (str_contains($n, '1899€ canapé') || str_contains($n, '2099€ canapé') || str_contains($n, '2099€ somier'))
            return true;
        if (str_contains($n, '3564€ somier') && str_contains($n, 'topper'))
            return true;
        return false;
    }

    public function recomputarVtasRepYEsp(bool $persist = false): self
    {
        $this->loadMissing(['ventaOfertas.oferta', 'ventaOfertas.productos']);

        $vtaRep = 0;
        $vtaEsp = 0;

        foreach ($this->ventaOfertas as $vo) {
            $tieneLineaRep = $vo->productos->contains(fn($p) => ($p->vendido_por ?? null) === VendidoPor::Repartidor);
            if (!$tieneLineaRep)
                continue;

            $nombre = (string) ($vo->oferta->nombre ?? '');
            if ($this->esOfertaExcepcional($nombre))
                $vtaEsp++;
            else
                $vtaRep++;
        }

        $this->vta_rep = $vtaRep;
        $this->vta_esp = $vtaEsp;

        if ($persist)
            $this->saveQuietly();
        return $this;
    }

    public function recalcularVtasAcumuladas(bool $persist = false): self
    {
        $this->vta_ac = (int) ($this->vta_rep ?? 0) + (int) ($this->vta_esp ?? 0);
        if ($persist)
            $this->saveQuietly();
        return $this;
    }

    protected function reglaComisionPorNombre(string $nombre): array
    {
        $n = mb_strtolower($nombre);

        if (str_contains($n, '1899€ canapé'))
            return ['venta' => 200.0, 'entrega' => 15.0];
        if (str_contains($n, '2099€ canapé') || str_contains($n, '2099€ somier'))
            return ['venta' => 220.0, 'entrega' => 15.0];
        if (str_contains($n, '3564€ somier') && str_contains($n, 'topper'))
            return ['venta' => 240.0, 'entrega' => 30.0];

        if (str_contains($n, 'media.vta'))
            return ['venta' => 100.0, 'entrega' => 7.50];
        if (str_contains($n, 'oferta reparto'))
            return ['venta' => 100.0, 'entrega' => 15.0];
        if (str_contains($n, 'tripl'))
            return ['venta' => 600.0, 'entrega' => 45.0];
        if (str_contains($n, 'cuadrup'))
            return ['venta' => 800.0, 'entrega' => 60.0];

        if (str_contains($n, 'doblete')) {
            if (str_contains($n, '3564'))
                return ['venta' => 440.0, 'entrega' => 30.0];
            if (str_contains($n, '3798'))
                return ['venta' => 400.0, 'entrega' => 30.0];
            return ['venta' => 400.0, 'entrega' => 30.0];
        }

        if (str_contains($n, 'sencilla')) {
            if (str_contains($n, '4pts'))
                return ['venta' => 200.0, 'entrega' => 15.0];
            if (str_contains($n, '5pts') || str_contains($n, '7pts') || str_contains($n, '8pts'))
                return ['venta' => 220.0, 'entrega' => 15.0];
            return ['venta' => 220.0, 'entrega' => 15.0];
        }

        return ['venta' => 0.0, 'entrega' => 0.0];
    }

    public function calcularComisiones(bool $persist = false): self
    {
        $this->loadMissing(['ventaOfertas.oferta', 'ventaOfertas.productos']);

        $totalEntrega = 0.0;
        $totalVentaRepartidor = 0.0;

        foreach ($this->ventaOfertas as $vo) {
            $regla = $this->reglaComisionPorNombre((string) ($vo->oferta->nombre ?? ''));
            $totalEntrega += (float) $regla['entrega'];

            $tieneLineaRep = $vo->productos->contains(fn($p) => ($p->vendido_por ?? null) === VendidoPor::Repartidor);
            if ($tieneLineaRep)
                $totalVentaRepartidor += (float) $regla['venta'];
        }

        $this->com_entrega = round($totalEntrega, 2);
        $this->com_venta = round($totalVentaRepartidor, 2);

        if ($persist)
            $this->saveQuietly();
        return $this;
    }

    public function calcularPas(bool $persist = false): self
    {
        $this->loadMissing(['ventaOfertas.oferta', 'ventaOfertas.productos']);

        $pasR = 0;
        $pasC = 0;

        foreach ($this->ventaOfertas as $vo) {
            $base = (int) ($vo->oferta->puntos_base ?? 0);
            $cerrados = (int) ($vo->puntos ?? $base);
            $diff = $cerrados - $base;
            if ($diff < 0)
                continue;

            $tieneRep = $vo->productos->contains(fn($p) => ($p->vendido_por ?? null) === VendidoPor::Repartidor);
            $tieneCom = $vo->productos->contains(fn($p) => ($p->vendido_por ?? null) === VendidoPor::Comercial);

            if ($tieneRep)
                $pasR += $diff;
            if ($tieneCom)
                $pasC += $diff;
        }

        $this->pas_repartidor = (int) $pasR;
        $this->pas_comercial = (int) $pasC;

        if ($persist)
            $this->saveQuietly();
        return $this;
    }
}
