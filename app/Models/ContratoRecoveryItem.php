<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Staging de recuperación de contratos desde imagen (SuperAdmin).
 * No crea ventas hasta la acción "Agregar Contrato".
 *
 * @property int $id
 * @property string $status
 * @property array|null $documents
 * @property array|null $reference_photos
 * @property array|null $extracted_json
 * @property array|null $reviewed_json
 * @property string|null $dni
 * @property string|null $nro_contr_adm
 * @property string|null $cliente_nombre
 * @property int|null $customer_id
 * @property int|null $venta_id
 * @property int|null $comercial_id
 * @property int|null $created_by_user_id
 * @property string|null $last_error
 */
class ContratoRecoveryItem extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_ADD = 'pending_add';

    public const STATUS_ADDED = 'added';

    public const STATUS_FAILED = 'failed';

    protected $table = 'contrato_recovery_items';

    protected $fillable = [
        'status',
        'documents',
        'reference_photos',
        'extracted_json',
        'reviewed_json',
        'dni',
        'nro_contr_adm',
        'cliente_nombre',
        'customer_id',
        'venta_id',
        'comercial_id',
        'created_by_user_id',
        'last_error',
    ];

    protected $casts = [
        'documents' => 'array',
        'reference_photos' => 'array',
        'extracted_json' => 'array',
        'reviewed_json' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class)->withTrashed();
    }

    public function comercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comercial_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Borrador',
            self::STATUS_PENDING_ADD => 'PendxAgregar',
            self::STATUS_ADDED => 'Agregado',
            self::STATUS_FAILED => 'Error',
            default => $this->status,
        };
    }

    public function reviewedData(): array
    {
        return is_array($this->reviewed_json) ? $this->reviewed_json : [];
    }

    public function canSyncFromVenta(): bool
    {
        return filled($this->venta_id);
    }

    /**
     * Lectura en vivo: venta (si existe) > snapshot de recuperación.
     */
    public function displayNroContrAdm(): ?string
    {
        $fromVenta = trim((string) ($this->venta?->nro_contr_adm ?? ''));
        if ($fromVenta !== '') {
            return $fromVenta;
        }

        $fromCol = trim((string) ($this->nro_contr_adm ?? ''));

        return $fromCol !== '' ? $fromCol : null;
    }

    public function displayDni(): ?string
    {
        $this->loadMissing(['venta.customer', 'customer']);

        $fromVentaCustomer = trim((string) ($this->venta?->customer?->dni ?? ''));
        if ($fromVentaCustomer !== '') {
            return $fromVentaCustomer;
        }

        $fromCustomer = trim((string) ($this->customer?->dni ?? ''));
        if ($fromCustomer !== '') {
            return $fromCustomer;
        }

        $fromCol = trim((string) ($this->dni ?? ''));

        return $fromCol !== '' ? $fromCol : null;
    }

    public function displayClienteNombre(): ?string
    {
        $this->loadMissing(['venta.customer', 'customer']);

        $fromVentaCustomer = trim((string) ($this->venta?->customer?->name ?? ''));
        if ($fromVentaCustomer !== '') {
            return mb_strtoupper($fromVentaCustomer);
        }

        $fromCustomer = trim((string) ($this->customer?->name ?? ''));
        if ($fromCustomer !== '') {
            return mb_strtoupper($fromCustomer);
        }

        $fromCol = trim((string) ($this->cliente_nombre ?? ''));

        return $fromCol !== '' ? $fromCol : null;
    }

    public function displayCustomerId(): ?int
    {
        $fromVenta = (int) ($this->venta?->customer_id ?? 0);
        if ($fromVenta > 0) {
            return $fromVenta;
        }

        $fromCol = (int) ($this->customer_id ?? 0);

        return $fromCol > 0 ? $fromCol : null;
    }

    /**
     * Preferir fecha de la venta cuando ya está enlazada.
     */
    public function displayFechaVentaRaw(): mixed
    {
        if ($this->venta_id && filled($this->venta?->fecha_venta)) {
            return $this->venta->fecha_venta;
        }

        $fromJson = data_get($this->reviewedData(), 'fecha_venta');
        if (filled($fromJson)) {
            return $fromJson;
        }

        return $this->venta?->fecha_venta;
    }

    /**
     * @return list<string>
     */
    public function displayOfertaNombres(): array
    {
        $names = [];

        if ($this->venta) {
            $this->venta->loadMissing('ventaOfertas.oferta');
            foreach ($this->venta->ventaOfertas as $vo) {
                $nombre = trim((string) ($vo->oferta?->nombre ?? ''));
                if ($nombre !== '') {
                    $names[] = $nombre;
                }
            }
        }

        if ($names !== []) {
            return $names;
        }

        $ofertaIds = [];
        foreach ($this->reviewedData()['ventaOfertas'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['oferta_id'] ?? 0);
            if ($id > 0) {
                $ofertaIds[] = $id;
            }
        }

        if ($ofertaIds === []) {
            return [];
        }

        $map = Oferta::query()
            ->whereIn('id', array_values(array_unique($ofertaIds)))
            ->pluck('nombre', 'id');

        foreach ($ofertaIds as $id) {
            $nombre = trim((string) ($map[$id] ?? ''));
            if ($nombre !== '') {
                $names[] = $nombre;
            }
        }

        return $names;
    }
}
