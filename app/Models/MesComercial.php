<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class MesComercial extends Model
{
    protected $table = 'mes_comerciales';

    protected $fillable = [
        'anio',
        'mes',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'anio' => 'integer',
        'mes' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public static function forYearMonth(int $anio, int $mes): ?self
    {
        return static::query()
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->first();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function rangeOrCalendar(int $anio, int $mes): array
    {
        $row = static::forYearMonth($anio, $mes);

        if ($row) {
            return [
                $row->fecha_inicio->copy()->startOfDay(),
                $row->fecha_fin->copy()->endOfDay(),
            ];
        }

        $start = Carbon::create($anio, $mes, 1)->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
    }

    /**
     * @return list<int>
     */
    public static function definedMonths(int $anio): array
    {
        return static::query()
            ->where('anio', $anio)
            ->orderBy('mes')
            ->pluck('mes')
            ->map(fn ($m) => (int) $m)
            ->all();
    }

    public static function previousKey(int $anio, int $mes): array
    {
        $d = Carbon::create($anio, $mes, 1)->subMonthNoOverflow();

        return [(int) $d->year, (int) $d->month];
    }

    public static function nextKey(int $anio, int $mes): array
    {
        $d = Carbon::create($anio, $mes, 1)->addMonthNoOverflow();

        return [(int) $d->year, (int) $d->month];
    }

    /**
     * @throws ValidationException
     */
    public static function assertNoOverlap(int $anio, int $mes, Carbon $inicio, Carbon $fin): void
    {
        if ($fin->lt($inicio)) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'La fecha final debe ser igual o posterior a la fecha de inicio.',
            ]);
        }

        [$prevAnio, $prevMes] = static::previousKey($anio, $mes);
        $prev = static::forYearMonth($prevAnio, $prevMes);

        if ($prev && $inicio->lte($prev->fecha_fin)) {
            $hasta = $prev->fecha_fin->format('d/m/Y');
            throw ValidationException::withMessages([
                'fecha_inicio' => "La fecha de inicio no puede estar incluida en el mes comercial anterior (hasta {$hasta}). Debe ser a partir del día siguiente.",
            ]);
        }

        [$nextAnio, $nextMes] = static::nextKey($anio, $mes);
        $next = static::forYearMonth($nextAnio, $nextMes);

        if ($next && $fin->gte($next->fecha_inicio)) {
            $desde = $next->fecha_inicio->format('d/m/Y');
            throw ValidationException::withMessages([
                'fecha_fin' => "La fecha final no puede solaparse con el mes comercial siguiente (desde {$desde}).",
            ]);
        }
    }
}
