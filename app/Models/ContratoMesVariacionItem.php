<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Detalle de un contrato que explica una variación mensual.
 *
 * @property int $id
 * @property int|null $venta_id
 * @property string $mes_key
 * @property string $estado
 * @property string|null $nro_contr_adm
 * @property string|null $cliente_nombre
 * @property string|null $dni
 * @property \Illuminate\Support\Carbon|null $ocurrido_at
 * @property int|null $caused_by_user_id
 * @property string|null $caused_by_label
 */
class ContratoMesVariacionItem extends Model
{
    public const ESTADO_SOFT_DELETE = 'soft_delete';

    public const ESTADO_NUEVO = 'nuevo';

    public const ESTADO_RESTAURADO = 'restaurado';

    public const ESTADO_BORRADO = 'borrado';

    protected $table = 'contratos_mes_variacion_items';

    protected $fillable = [
        'venta_id',
        'mes_key',
        'estado',
        'nro_contr_adm',
        'cliente_nombre',
        'dni',
        'ocurrido_at',
        'caused_by_user_id',
        'caused_by_label',
    ];

    protected $casts = [
        'ocurrido_at' => 'datetime',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class)->withTrashed();
    }

    public function causedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caused_by_user_id');
    }

    public function estadoLabel(): string
    {
        return match ($this->estado) {
            self::ESTADO_SOFT_DELETE => 'Soft-delete',
            self::ESTADO_NUEVO => 'Nuevo',
            self::ESTADO_RESTAURADO => 'Restaurado',
            self::ESTADO_BORRADO => 'Borrado',
            default => $this->estado,
        };
    }

    public function diaLabel(): string
    {
        $at = $this->ocurrido_at;
        if (! $at instanceof Carbon) {
            return '—';
        }

        return $at->locale('es')->translatedFormat('l'); // lunes, martes...
    }

    public function fechaHoraLabel(): string
    {
        $at = $this->ocurrido_at;
        if (! $at instanceof Carbon) {
            return '—';
        }

        return $at->format('d/m/Y H:i:s');
    }

    public function quienLabel(): string
    {
        if (filled($this->caused_by_label)) {
            return (string) $this->caused_by_label;
        }

        $user = $this->causedBy;
        if ($user) {
            if (filled($user->empleado_id)) {
                return trim("{$user->empleado_id} - {$user->name} {$user->last_name}");
            }

            $nombre = trim(($user->name ?? '') . ' ' . ($user->last_name ?? ''));

            return $nombre !== ''
                ? "{$nombre} (sin ID empleado)"
                : "Usuario #{$user->id}";
        }

        return 'Sistema / automático';
    }
}
