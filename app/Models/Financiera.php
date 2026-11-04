<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Financiera extends Model
{
    protected $table = 'financieras';

    protected $fillable = [
        'codigo',
        'nombre',
        'fecha_inclusion',
        'baja_at',
    ];

    protected $casts = [
        'fecha_inclusion' => 'date',
        'baja_at' => 'datetime',
    ];

    public function scopeActivas(Builder $query): Builder
    {
        return $query->whereNull('baja_at');
    }

    public function scopeDeBaja(Builder $query): Builder
    {
        return $query->whereNotNull('baja_at');
    }

    public function estaActiva(): bool
    {
        return $this->baja_at === null;
    }

    protected static function booted(): void
    {
        static::updating(function (self $financiera): void {
            if ($financiera->isDirty('codigo') && filled($financiera->getOriginal('codigo'))) {
                $financiera->codigo = $financiera->getOriginal('codigo');
            }
        });
    }

    /**
     * Altas iniciales del catálogo. El código es el texto que ya queda en el contrato.
     *
     * @return list<array{codigo: string, nombre: string, fecha_inclusion: string}>
     */
    public static function registrosIniciales(): array
    {
        return [
            ['codigo' => 'CREDIBOX', 'nombre' => 'CREDIBOX', 'fecha_inclusion' => '2025-08-21'],
            ['codigo' => 'findirect', 'nombre' => 'findirect', 'fecha_inclusion' => '2025-08-21'],
            ['codigo' => 'MONTJUIT', 'nombre' => 'MONTJUIT', 'fecha_inclusion' => '2025-08-21'],
            ['codigo' => 'Pepper', 'nombre' => 'Pepper', 'fecha_inclusion' => '2025-10-28'],
            ['codigo' => 'Sofinco', 'nombre' => 'Sofinco', 'fecha_inclusion' => '2026-10-28'],
        ];
    }

    /**
     * Los cuatro valores que ya se guardan en contratos.
     * El desplegable sigue usando estas claves si el catálogo todavía no existe.
     *
     * @return array<string, string>
     */
    public static function opcionesHistoricas(): array
    {
        return [
            'CREDIBOX' => 'CREDIBOX',
            'findirect' => 'findirect',
            'MONTJUIT' => 'MONTJUIT',
            'Pepper' => 'Pepper',
        ];
    }

    /**
     * Opciones del desplegable. La clave es el código que ya está en el contrato,
     * no el nombre visible. Una baja o un cambio de nombre no dejan el valor fuera.
     *
     * @return array<string, string>
     */
    public static function opcionesSelect(?string $actual = null): array
    {
        $options = [];
        $catalogoListo = false;

        try {
            if (Schema::hasTable('financieras') && Schema::hasColumn('financieras', 'codigo')) {
                $catalogoListo = true;
                $options = static::query()
                    ->activas()
                    ->orderBy('nombre')
                    ->pluck('nombre', 'codigo')
                    ->all();
            }
        } catch (\Throwable) {
            $catalogoListo = false;
            $options = [];
        }

        if (! $catalogoListo) {
            $options = self::opcionesHistoricas();
        }

        if (filled($actual) && ! array_key_exists($actual, $options)) {
            $nombre = null;

            try {
                if (Schema::hasTable('financieras')) {
                    $nombre = static::query()->where('codigo', $actual)->value('nombre');
                }
            } catch (\Throwable) {
                $nombre = null;
            }

            $options[$actual] = $nombre ?: (self::opcionesHistoricas()[$actual] ?? $actual);
        }

        return $options;
    }
}
