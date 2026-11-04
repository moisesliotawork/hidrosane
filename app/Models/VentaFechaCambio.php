<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Auditoría de cambios de fecha_venta (alerta SuperAdmin).
 *
 * @property int $id
 * @property int|null $venta_id
 * @property string|null $nro_contr_adm
 * @property string|null $cliente_nombre
 * @property \Illuminate\Support\Carbon|null $fecha_anterior
 * @property \Illuminate\Support\Carbon|null $fecha_nueva
 * @property int|null $caused_by_user_id
 * @property string|null $caused_by_label
 * @property \Illuminate\Support\Carbon|null $ocurrido_at
 * @property \Illuminate\Support\Carbon|null $acknowledged_at
 */
class VentaFechaCambio extends Model
{
    protected $table = 'venta_fecha_cambios';

    protected $fillable = [
        'venta_id',
        'nro_contr_adm',
        'cliente_nombre',
        'fecha_anterior',
        'fecha_nueva',
        'caused_by_user_id',
        'caused_by_label',
        'ocurrido_at',
        'acknowledged_at',
    ];

    protected $casts = [
        'fecha_anterior' => 'date',
        'fecha_nueva' => 'date',
        'ocurrido_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class)->withTrashed();
    }

    public function causedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caused_by_user_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('acknowledged_at');
    }

    public function fechaAnteriorLabel(): string
    {
        return $this->fecha_anterior instanceof Carbon
            ? $this->fecha_anterior->format('d/m/Y')
            : '—';
    }

    public function fechaNuevaLabel(): string
    {
        return $this->fecha_nueva instanceof Carbon
            ? $this->fecha_nueva->format('d/m/Y')
            : '—';
    }

    public function fechaAnteriorConHoraLabel(): string
    {
        return $this->fechaConHoraLabel($this->fecha_anterior);
    }

    public function fechaNuevaConHoraLabel(): string
    {
        return $this->fechaConHoraLabel($this->fecha_nueva);
    }

    private function fechaConHoraLabel(mixed $fecha): string
    {
        if (! $fecha instanceof Carbon) {
            return '—';
        }

        $hora = $this->ocurrido_at instanceof Carbon
            ? $this->ocurrido_at->format('H:i:s')
            : '00:00:00';

        return $fecha->format('d/m/Y').' '.$hora;
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

            $nombre = trim(($user->name ?? '').' '.($user->last_name ?? ''));

            return $nombre !== '' ? $nombre : "Usuario #{$user->id}";
        }

        return 'Sistema / automático';
    }
}
