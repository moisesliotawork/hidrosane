<?php

namespace App\Models;

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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public static function isRecuperado(?string $nroContrAdm): bool
    {
        $nro = trim((string) $nroContrAdm);
        if ($nro === '' || ! Schema::hasTable('contratos_recuperados')) {
            return false;
        }

        return static::query()->where('nro_contr_adm', $nro)->exists();
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
