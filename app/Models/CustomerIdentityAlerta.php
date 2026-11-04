<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerIdentityAlerta extends Model
{
    protected $table = 'customer_identity_alertas';

    protected $fillable = [
        'venta_id',
        'nro_contr_adm',
        'customer_id',
        'coincidencia_customer_id',
        'nombre_antes',
        'dni_antes',
        'nombre_despues',
        'dni_despues',
        'coincidencia_nombre',
        'coincidencia_dni',
        'via',
        'caused_by_user_id',
        'caused_by_label',
        'ocurrido_at',
        'acknowledged_at',
    ];

    protected $casts = [
        'ocurrido_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('acknowledged_at');
    }

    public function quienLabel(): string
    {
        return filled($this->caused_by_label)
            ? (string) $this->caused_by_label
            : 'Sistema / automático';
    }

    public function cuandoLabel(): string
    {
        return $this->ocurrido_at?->timezone('Europe/Madrid')->format('d/m/Y H:i') ?? '—';
    }

    public function viaLabel(): string
    {
        return match ($this->via) {
            'dni' => 'DNI',
            'nombre' => 'nombre',
            'ambos' => 'nombre y DNI',
            default => 'dato',
        };
    }
}
