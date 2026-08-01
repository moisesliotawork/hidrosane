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
            self::STATUS_PENDING_ADD => 'Pendiente de agregar',
            self::STATUS_ADDED => 'Agregado',
            self::STATUS_FAILED => 'Error',
            default => $this->status,
        };
    }

    public function reviewedData(): array
    {
        return is_array($this->reviewed_json) ? $this->reviewed_json : [];
    }
}
