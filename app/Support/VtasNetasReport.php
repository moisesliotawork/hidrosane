<?php

namespace App\Support;

use App\Enums\EstadoReparto;
use App\Enums\EstadoVenta;
use App\Models\MesComercial;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class VtasNetasReport
{
    /** @return list<string> */
    public static function nuloVentaEstados(): array
    {
        return [
            EstadoVenta::NULO_EN_REPARTO->value,
            EstadoVenta::NULO_POR_OFICINA->value,
            EstadoVenta::NULO_FINANCIERO->value,
            EstadoVenta::NULO_POR_AUSENTE->value,
            EstadoVenta::NG->value,
            EstadoVenta::RETROCESO->value,
        ];
    }

    /** @return list<string> */
    public static function nuloRepartoEstados(): array
    {
        return [
            EstadoReparto::NULO_REPARTO->value,
            EstadoReparto::NULO_FINANCIERO->value,
            EstadoReparto::NULO_AUSENTE->value,
        ];
    }

    /**
     * @return Collection<int, array{id:int, label:string, netas:int, brut:int, neut:int, entr:int, desc:int}>
     */
    public function rows(int $year, int $month): Collection
    {
        [$start, $end] = MesComercial::rangeOrCalendar($year, $month);
        [$prevYear, $prevMonth] = MesComercial::previousKey($year, $month);
        [$prevStart, $prevEnd] = MesComercial::rangeOrCalendar($prevYear, $prevMonth);

        $comerciales = User::query()
            ->role(['commercial', 'team_leader', 'sales_manager'])
            ->whereNull('baja')
            ->whereNotIn('empleado_id', ['991']) // GERENTE: no entra en Vtas Netas
            ->with('roles:id,name')
            ->orderBy('empleado_id')
            ->get(['id', 'empleado_id', 'name', 'genero']);

        $ids = $comerciales->pluck('id');

        // Mes comercial: Neut. = nulos por fecha_venta; Entr. = no nulos por fecha_entrega.
        // Brut. = Neut. + Entr. (ventas brutas del mes comercial).
        $neut = $this->counts($ids, $start, $end, 'fecha_venta', neutral: true);
        $entr = $this->counts($ids, $start, $end, 'fecha_entrega', delivered: true);
        $desc = $this->counts($ids, $prevStart, $prevEnd, 'fecha_venta', neutral: true);

        return $comerciales
            ->map(function (User $user) use ($neut, $entr, $desc): array {
                $neutras = (int) ($neut[$user->id] ?? 0);
                $entregadas = (int) ($entr[$user->id] ?? 0);
                $descuentos = (int) ($desc[$user->id] ?? 0);

                $empleadoId = (string) ($user->empleado_id ?? '');
                $nombre = mb_strtoupper(trim((string) $user->name));
                $genero = strtoupper((string) ($user->genero ?? ''));

                return [
                    'id' => $user->id,
                    'empleado_id' => $empleadoId,
                    'nombre' => $nombre,
                    'genero' => in_array($genero, ['M', 'F'], true) ? $genero : null,
                    'es_jefe' => $user->roles->contains('name', 'team_leader'),
                    'label' => trim($empleadoId.' '.$nombre),
                    'netas' => $entregadas - $descuentos,
                    'brut' => $neutras + $entregadas,
                    'neut' => $neutras,
                    'entr' => $entregadas,
                    'desc' => $descuentos,
                ];
            })
            ->sortBy(fn (array $row): int => (int) $row['empleado_id'])
            ->values();
    }

    /**
     * @param  Collection<int, int>  $comercialIds
     * @return Collection<int|string, int>
     */
    protected function counts(Collection $comercialIds, Carbon $start, Carbon $end, string $dateColumn, bool $neutral = false, bool $delivered = false): Collection
    {
        if ($comercialIds->isEmpty()) {
            return collect();
        }

        $query = Venta::query()
            ->whereIn('comercial_id', $comercialIds)
            ->whereBetween($dateColumn, [$start, $end])
            ->where(function (Builder $q) {
                $q->whereNull('nro_contr_adm')
                    ->orWhere('nro_contr_adm', 'not like', '%-B%');
            });

        if ($neutral) {
            $this->whereNeutral($query);
        }

        if ($delivered) {
            $this->whereNotNeutral($query);
        }

        return $query
            ->selectRaw('comercial_id, COUNT(*) as total')
            ->groupBy('comercial_id')
            ->pluck('total', 'comercial_id');
    }

    protected function whereNeutral(Builder $query): void
    {
        $venta = self::nuloVentaEstados();
        $reparto = self::nuloRepartoEstados();

        $query->where(function (Builder $q) use ($venta, $reparto) {
            $q->whereIn('estado_venta', $venta)
                ->orWhereHas('reparto', fn (Builder $r) => $r->whereIn('estado', $reparto));
        });
    }

    protected function whereNotNeutral(Builder $query): void
    {
        $venta = self::nuloVentaEstados();
        $reparto = self::nuloRepartoEstados();

        $query->where(function (Builder $q) use ($venta) {
            $q->whereNull('estado_venta')
                ->orWhereNotIn('estado_venta', $venta);
        })->whereDoesntHave('reparto', fn (Builder $r) => $r->whereIn('estado', $reparto));
    }
}
