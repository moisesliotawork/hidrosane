<?php

namespace App\Support;

use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Collection;

class TotalVtasComReport
{
    /**
     * Alias → patrones de búsqueda en name / last_name / empleado_id.
     *
     * @var list<array{alias: string, needles: list<string>}>
     */
    public const COMERCIALES = [
        ['alias' => 'Nuria', 'needles' => ['nuria']],
        ['alias' => 'Carla', 'needles' => ['carla']],
        ['alias' => 'Nerea', 'needles' => ['nerea']],
        ['alias' => 'Samu', 'needles' => ['samu', 'samuel']],
        ['alias' => 'Pol', 'needles' => ['pol']],
        ['alias' => 'Lage', 'needles' => ['lage']],
        ['alias' => 'Juanse', 'needles' => ['juanse']],
    ];

    /**
     * @return Collection<int, array{
     *     alias: string,
     *     found: bool,
     *     user_id: ?int,
     *     empleado_id: string,
     *     nombre: string,
     *     contratos: int,
     *     clientes: int,
     *     primera: ?string,
     *     ultima: ?string
     * }>
     */
    public function rows(): Collection
    {
        return collect(self::COMERCIALES)->map(function (array $cfg): array {
            $user = $this->resolveUser($cfg['needles']);

            if (! $user) {
                return [
                    'alias' => $cfg['alias'],
                    'found' => false,
                    'user_id' => null,
                    'empleado_id' => '—',
                    'nombre' => $cfg['alias'].' (no encontrado)',
                    'contratos' => 0,
                    'clientes' => 0,
                    'primera' => null,
                    'ultima' => null,
                ];
            }

            $stats = $this->soloStats((int) $user->id);

            return [
                'alias' => $cfg['alias'],
                'found' => true,
                'user_id' => (int) $user->id,
                'empleado_id' => (string) ($user->empleado_id ?? '—'),
                'nombre' => mb_strtoupper(trim($user->name.' '.($user->last_name ?? ''))),
                'contratos' => (int) ($stats->contratos ?? 0),
                'clientes' => (int) ($stats->clientes ?? 0),
                'primera' => $stats->primera
                    ? \Carbon\Carbon::parse($stats->primera)->format('d/m/Y')
                    : null,
                'ultima' => $stats->ultima
                    ? \Carbon\Carbon::parse($stats->ultima)->format('d/m/Y')
                    : null,
            ];
        });
    }

    /**
     * @param  list<string>  $needles
     */
    protected function resolveUser(array $needles): ?User
    {
        $candidates = User::query()
            ->where(function ($q) use ($needles) {
                foreach ($needles as $needle) {
                    $like = '%'.$needle.'%';
                    $q->orWhereRaw('LOWER(name) LIKE ?', [mb_strtolower($like)])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [mb_strtolower($like)])
                        ->orWhereRaw('LOWER(CONCAT(COALESCE(name, \'\'), \' \', COALESCE(last_name, \'\'))) LIKE ?', [mb_strtolower($like)])
                        ->orWhereRaw('LOWER(CAST(empleado_id AS CHAR)) LIKE ?', [mb_strtolower($like)]);
                }
            })
            ->get(['id', 'empleado_id', 'name', 'last_name']);

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        // Preferir rol commercial / team_leader si Spatie está disponible.
        $withRole = $candidates->filter(function (User $u) {
            try {
                return $u->hasAnyRole(['commercial', 'team_leader', 'sales_manager']);
            } catch (\Throwable) {
                return false;
            }
        });

        $pool = $withRole->isNotEmpty() ? $withRole : $candidates;

        // Entre varios: el que más ventas en solitario tenga.
        return $pool
            ->sortByDesc(fn (User $u) => $this->soloStats((int) $u->id)->contratos ?? 0)
            ->first();
    }

    /**
     * @return object{contratos: int|string, clientes: int|string, primera: ?string, ultima: ?string}
     */
    protected function soloStats(int $userId): object
    {
        return Venta::query()
            ->where('comercial_id', $userId)
            ->where(function ($q) {
                $q->whereNull('companion_id')->orWhere('companion_id', 0);
            })
            ->whereNull('deleted_at')
            ->whereNotNull('customer_id')
            ->where(function ($q) {
                $q->whereNull('nro_contr_adm')
                    ->orWhere('nro_contr_adm', 'not like', '%-B%');
            })
            ->selectRaw('COUNT(*) as contratos')
            ->selectRaw('COUNT(DISTINCT customer_id) as clientes')
            ->selectRaw('MIN(fecha_venta) as primera')
            ->selectRaw('MAX(fecha_venta) as ultima')
            ->first() ?? (object) [
                'contratos' => 0,
                'clientes' => 0,
                'primera' => null,
                'ultima' => null,
            ];
    }
}
