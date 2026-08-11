<?php

namespace App\Models;

use App\Enums\EstadoVenta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * @property int $id
 * @property string $nro_contr_adm
 * @property int|null $created_by_user_id
 */
class ContratoRecuperado extends Model
{
    protected $table = 'contratos_recuperados';

    protected $fillable = [
        'nro_contr_adm',
        'created_by_user_id',
    ];

    /** @var array<string, true>|null */
    protected static ?array $nrosLookup = null;

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public static function isRecuperado(?string $nroContrAdm): bool
    {
        $nro = trim((string) $nroContrAdm);
        if ($nro === '') {
            return false;
        }

        return isset(static::nrosLookup()[$nro]);
    }

    /**
     * Color del badge ESTADO/CONTR: recuperados en «En revisión» (sin estado
     * manual distinto) se muestran en gris; el resto usa el color del enum.
     */
    public static function estadoBadgeColor(null|string|EstadoVenta $estado, ?string $nroContrAdm): string
    {
        $enum = $estado instanceof EstadoVenta
            ? $estado
            : (EstadoVenta::tryFrom(trim((string) $estado)) ?? EstadoVenta::EN_REVISION);

        if ($enum === EstadoVenta::EN_REVISION && static::isRecuperado($nroContrAdm)) {
            return 'gray';
        }

        return $enum->color();
    }

    /**
     * @return array<string, true>
     */
    protected static function nrosLookup(): array
    {
        if (static::$nrosLookup !== null) {
            return static::$nrosLookup;
        }

        if (! Schema::hasTable('contratos_recuperados')) {
            return static::$nrosLookup = [];
        }

        $lookup = [];
        foreach (static::query()->pluck('nro_contr_adm') as $nro) {
            $nro = trim((string) $nro);
            if ($nro !== '') {
                $lookup[$nro] = true;
            }
        }

        return static::$nrosLookup = $lookup;
    }

    /**
     * @param  iterable<int, string|null>  $nros
     * @return list<string>
     */
    public static function nrosRecuperadosAmong(iterable $nros): array
    {
        if (! Schema::hasTable('contratos_recuperados')) {
            return [];
        }

        $clean = collect($nros)
            ->map(fn ($n) => trim((string) $n))
            ->filter(fn (string $n) => $n !== '')
            ->unique()
            ->values()
            ->all();

        if ($clean === []) {
            return [];
        }

        return static::query()
            ->whereIn('nro_contr_adm', $clean)
            ->pluck('nro_contr_adm')
            ->map(fn ($n) => (string) $n)
            ->values()
            ->all();
    }
}
