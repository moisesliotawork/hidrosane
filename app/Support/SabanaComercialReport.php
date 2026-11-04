<?php

namespace App\Support;

use App\Enums\EstadoTerminal;
use App\Enums\EstadoVenta;
use App\Enums\FuenteNotas;
use App\Models\MesComercial;
use App\Models\Note;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SabanaComercialReport
{
    /**
     * @return array{
     *     jefes: Collection<int, User>,
     *     comerciales: Collection<int, User>,
     *     repartidores: Collection<int, User>
     * }
     */
    public function people(): array
    {
        $base = fn (): Builder => User::query()
            ->whereNull('baja')
            ->orderBy('empleado_id');

        return [
            'jefes' => $this->usersWithRole($base(), 'team_leader'),
            'comerciales' => $this->usersWithRole($base(), 'commercial'),
            'repartidores' => $this->usersWithRole($base(), 'delivery'),
        ];
    }

    /**
     * @return Collection<int, User>
     */
    protected function usersWithRole(Builder $query, string $role): Collection
    {
        if (! \Spatie\Permission\Models\Role::query()->where('name', $role)->where('guard_name', 'web')->exists()) {
            return collect();
        }

        return $query->role($role)->get(['id', 'empleado_id', 'name', 'last_name', 'genero']);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function rangeForTab(string $tab): array
    {
        $now = now();
        $year = (int) $now->year;
        $month = (int) $now->month;

        if ($tab === 'anterior') {
            [$year, $month] = MesComercial::previousKey($year, $month);
        }

        return MesComercial::rangeOrCalendar($year, $month);
    }

    /**
     * Periodo comercial inmediatamente anterior al de la pestaña activa.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function previousRangeForTab(string $tab): array
    {
        $now = now();
        $year = (int) $now->year;
        $month = (int) $now->month;

        if ($tab === 'anterior') {
            [$year, $month] = MesComercial::previousKey($year, $month);
        }

        [$year, $month] = MesComercial::previousKey($year, $month);

        return MesComercial::rangeOrCalendar($year, $month);
    }

    /**
     * @return array{
     *     entregadas: Collection<int, array<string, mixed>>,
     *     neutras: Collection<int, array<string, mixed>>,
     *     descontar: Collection<int, array<string, mixed>>
     * }
     */
    public function sectionsForUser(User $user, string $tab = 'este'): array
    {
        [$start, $end] = $this->rangeForTab($tab);
        [$prevStart, $prevEnd] = $this->previousRangeForTab($tab);

        $periodo = $this->ventasDelUsuario($user)
            ->whereNotNull('fecha_entrega')
            ->whereBetween('fecha_entrega', [$start, $end])
            ->orderBy('fecha_entrega')
            ->orderBy('id')
            ->get();

        $descontarVentas = $this->ventasDelUsuario($user)
            ->whereNotNull('fecha_venta')
            ->whereBetween('fecha_venta', [$prevStart, $prevEnd])
            ->whereIn('estado_venta', VtasNetasReport::nuloVentaEstados())
            ->where(function (Builder $q) use ($start, $end) {
                // Anulación en este periodo (aprox. por updated_at) o sin entrega en este periodo
                $q->whereBetween('updated_at', [$start, $end])
                    ->orWhereNull('fecha_entrega')
                    ->orWhereNotBetween('fecha_entrega', [$start, $end]);
            })
            ->orderBy('fecha_venta')
            ->orderBy('id')
            ->get()
            ->reject(fn (Venta $v) => $periodo->contains('id', $v->id))
            ->values();

        $all = $periodo->concat($descontarVentas);
        $customerIds = $all->pluck('customer_id')->filter()->unique()->values();
        $pagandoPorCliente = $this->pagandoCounts($customerIds);
        $totalPorCliente = $this->totalContratosCounts($customerIds);

        return [
            'entregadas' => $this->mapSection(
                $periodo->filter(fn (Venta $v) => ! $this->isNeutra($v))->values(),
                $user,
                $pagandoPorCliente,
                $totalPorCliente,
                tipo: 'entregadas',
            ),
            'neutras' => $this->mapSection(
                $periodo->filter(fn (Venta $v) => $this->isNeutra($v))->values(),
                $user,
                $pagandoPorCliente,
                $totalPorCliente,
                tipo: 'neutras',
            ),
            'descontar' => $this->mapSection(
                $descontarVentas,
                $user,
                $pagandoPorCliente,
                $totalPorCliente,
                tipo: 'descontar',
            ),
        ];
    }

    protected function ventasDelUsuario(User $user): Builder
    {
        return Venta::query()
            ->with([
                'customer:id,fecha_nac,first_names,last_names,inhabilitado',
                'comercial:id,empleado_id',
                'companion:id,empleado_id',
                'repartidor:id,empleado_id',
                'repartidor2:id,empleado_id',
                'note:id,fuente,user_id,comercial_id',
            ])
            ->where(function (Builder $q) {
                $q->whereNull('nro_contr_adm')
                    ->orWhere('nro_contr_adm', 'not like', '%-B%');
            })
            ->where(function (Builder $q) use ($user) {
                $q->where('comercial_id', $user->id)
                    ->orWhere('companion_id', $user->id)
                    ->orWhere('repartidor_id', $user->id)
                    ->orWhere('repartidor_2', $user->id);
            });
    }

    /**
     * @deprecated Use sectionsForUser()
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rowsForUser(User $user, string $tab = 'este'): Collection
    {
        return $this->sectionsForUser($user, $tab)['entregadas'];
    }

    /**
     * @param  Collection<int, Venta>  $ventas
     * @param  Collection<int|string, int>  $pagandoPorCliente
     * @param  Collection<int|string, int>  $totalPorCliente
     * @return Collection<int, array<string, mixed>>
     */
    /**
     * @param  'entregadas'|'neutras'|'descontar'  $tipo
     */
    protected function mapSection(
        Collection $ventas,
        User $user,
        Collection $pagandoPorCliente,
        Collection $totalPorCliente,
        string $tipo,
    ): Collection {
        $esNeutraSeccion = in_array($tipo, ['neutras', 'descontar'], true);

        return $ventas->map(function (Venta $venta) use ($user, $pagandoPorCliente, $totalPorCliente, $tipo, $esNeutraSeccion): array {
            $row = $this->mapVentaRow($venta, $user, $pagandoPorCliente, $totalPorCliente);
            $row['es_neutra'] = $esNeutraSeccion;
            $row['es_descontar'] = $tipo === 'descontar';
            $row['cliente_perdido'] = $esNeutraSeccion && (
                $venta->estado_venta === EstadoVenta::RETROCESO
                || (bool) ($venta->customer?->inhabilitado)
            );
            $row['fecha_anulacion'] = $esNeutraSeccion && $venta->updated_at
                ? $venta->updated_at->format('d/m/y')
                : '';

            return $row;
        })->values();
    }

    protected function isNeutra(Venta $venta): bool
    {
        $estado = $venta->estado_venta;

        if (! $estado instanceof EstadoVenta) {
            return false;
        }

        return in_array($estado->value, VtasNetasReport::nuloVentaEstados(), true);
    }

    /**
     * @param  Collection<int|string, int>  $pagandoPorCliente
     * @param  Collection<int|string, int>  $totalPorCliente
     * @return array<string, mixed>
     */
    protected function mapVentaRow(
        Venta $venta,
        User $user,
        Collection $pagandoPorCliente,
        Collection $totalPorCliente,
    ): array {
        $raw = $venta->total_ventas_oferta;
        $total = self::totalVentaContrato($raw === null || $raw === '' ? null : (float) $raw);
        $comercialId = $venta->comercial_id !== null ? (int) $venta->comercial_id : null;
        $companionId = filled($venta->companion_id) ? (int) $venta->companion_id : null;
        $partes = self::columnasVenta($total, $comercialId, $companionId);
        $vtaPropia = self::parteComercial($total, $comercialId, $companionId, (int) $user->id);

        $edad = $this->edadCliente($venta);
        $cliente = trim(mb_strtoupper(trim(
            ($venta->customer?->first_names ?? '').' '.($venta->customer?->last_names ?? '')
        )));
        $pagando = (int) ($pagandoPorCliente[$venta->customer_id] ?? 0);
        $fuente = VentaOrigenResolver::fuenteDisplayForVenta($venta);
        $fuenteCorta = $this->fuenteCorta($fuente);
        $esNuevo = (int) ($totalPorCliente[$venta->customer_id] ?? 0) <= 1;

        return [
            'contrato' => $venta->nro_contr_adm ?: '—',
            'cliente' => $cliente !== '' ? $cliente : '—',
            'fuente_corta' => $fuenteCorta,
            'fuente_clase' => $this->fuenteClase($fuente),
            'fuente_badge' => $fuenteCorta.' Ahora: '.$pagando.'C',
            'es_nuevo_cliente' => $esNuevo,
            'cliente_perdido' => false,
            'fecha_entrega' => $venta->fecha_entrega
                ? Carbon::parse($venta->fecha_entrega)->format('d/m/y')
                : '—',
            'fecha_anulacion' => '',
            'fecha_venta' => $venta->fecha_venta
                ? Carbon::parse($venta->fecha_venta)->format('d/m/y')
                : null,
            'c1' => $venta->comercial?->empleado_id ?? '0',
            'c2' => $venta->companion?->empleado_id ?? '0',
            'r1' => $venta->repartidor?->empleado_id ?? '0',
            'r2' => $venta->repartidor2?->empleado_id ?? '0',
            'estado' => $venta->estado_venta?->label() ?? '—',
            'imp_com' => $this->money($venta->total_final),
            'imp_rep' => $this->money($venta->importe_repartidor),
            'vta_com' => $this->formatVta($partes['principal']),
            'vta_com_pct' => $partes['compartida'],
            'vta_com_ac' => $this->formatVta($partes['acompanante']),
            'vta_com_ac_pct' => $partes['compartida'],
            'vta_propia' => $this->formatVta($vtaPropia),
            'vta_esp' => '',
            'edad_gt_80' => $edad['gt80'] ? 'SI' : 'NO',
            'edad_texto' => $edad['texto'],
            'cuotas' => $venta->num_cuotas ?? 0,
            'pagando' => $pagando,
            'pas_comercial' => (int) ($venta->pas_comercial ?? 0),
            'pas_repartidor' => (int) ($venta->pas_repartidor ?? 0),
            'edad_gt_80_bool' => $edad['gt80'],
            'highlight' => $this->highlightColumn($venta, $user),
        ];
    }

    protected function fuenteCorta(FuenteNotas $fuente): string
    {
        return match ($fuente) {
            FuenteNotas::VIP_INT, FuenteNotas::VIP_EXT => 'VIP',
            FuenteNotas::CALLE => 'CALLE',
            FuenteNotas::PTA_FRIA => 'PTA FRIA',
            FuenteNotas::EXCEL => 'EXCEL',
            FuenteNotas::AG_N_ADMIN => 'AG_N',
            FuenteNotas::PF_ADMIN => 'PF',
        };
    }

    protected function fuenteClase(FuenteNotas $fuente): string
    {
        return match ($fuente) {
            FuenteNotas::VIP_INT, FuenteNotas::VIP_EXT => 'is-vip',
            FuenteNotas::CALLE => 'is-calle',
            FuenteNotas::PTA_FRIA, FuenteNotas::AG_N_ADMIN, FuenteNotas::PF_ADMIN => 'is-ptafria',
            default => 'is-calle',
        };
    }

    /**
     * Contratos en corriente del cliente (no nulos / no retroceso).
     *
     * @param  Collection<int, int|string>  $customerIds
     * @return Collection<int|string, int>
     */
    protected function pagandoCounts(Collection $customerIds): Collection
    {
        if ($customerIds->isEmpty()) {
            return collect();
        }

        $excluidos = [
            EstadoVenta::NULO_EN_REPARTO->value,
            EstadoVenta::NULO_POR_OFICINA->value,
            EstadoVenta::NULO_FINANCIERO->value,
            EstadoVenta::NULO_POR_AUSENTE->value,
            EstadoVenta::NG->value,
            EstadoVenta::RETROCESO->value,
        ];

        return Venta::query()
            ->whereIn('customer_id', $customerIds)
            ->where(function (Builder $q) {
                $q->whereNull('nro_contr_adm')
                    ->orWhere('nro_contr_adm', 'not like', '%-B%');
            })
            ->where(function (Builder $q) use ($excluidos) {
                $q->whereNull('estado_venta')
                    ->orWhereNotIn('estado_venta', $excluidos);
            })
            ->selectRaw('customer_id, COUNT(*) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');
    }

    /**
     * @param  Collection<int, int|string>  $customerIds
     * @return Collection<int|string, int>
     */
    protected function totalContratosCounts(Collection $customerIds): Collection
    {
        if ($customerIds->isEmpty()) {
            return collect();
        }

        return Venta::query()
            ->whereIn('customer_id', $customerIds)
            ->where(function (Builder $q) {
                $q->whereNull('nro_contr_adm')
                    ->orWhere('nro_contr_adm', 'not like', '%-B%');
            })
            ->selectRaw('customer_id, COUNT(*) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return array{reparto: ?string, venta: ?string}
     */
    public function periodosFromRows(Collection $filas): array
    {
        if ($filas->isEmpty()) {
            return ['reparto' => null, 'venta' => null];
        }

        $entregas = $filas->pluck('fecha_entrega')->filter()->values();
        $ventas = $filas->pluck('fecha_venta')->filter()->values();

        return [
            'reparto' => $entregas->isNotEmpty()
                ? $entregas->first().' a '.$entregas->last()
                : null,
            'venta' => $ventas->isNotEmpty()
                ? $ventas->first().' a '.$ventas->last()
                : null,
        ];
    }

    /**
     * Resumen y KPI bajo la sección 3 (estilo Zenany).
     *
     * @param  array{
     *     entregadas: Collection<int, array<string, mixed>>,
     *     neutras: Collection<int, array<string, mixed>>,
     *     descontar: Collection<int, array<string, mixed>>
     * }  $secciones
     * @return array{
     *     vip_ya_eran: int,
     *     calle_nuevos: int,
     *     neutras_vip_perdidos: int,
     *     neutras_calle: int,
     *     recogidas_vip_perdidos: int,
     *     recogidas_calle: int,
     *     saldo: int,
     *     kpi: int,
     *     kpi_emoji: string,
     *     vtas_brutas: string,
     *     vtas_neutras: string,
     *     vtas_entregadas: string,
     *     anulaciones_anteriores: string,
     *     vtas_netas: string,
     *     vtas_netas_comisionables: string,
     *     rappel_netas_label: string,
     *     rappel_ohanapoints: string,
     *     ref_comision_base: int,
     *     ref_comision: int,
     *     pen_puntos: int,
     *     pen_carencia: int,
     *     pen_edad: int,
     *     declaraciones: array{
     *         ofis: string,
     *         nulos: string,
     *         conf: string,
     *         vis_con_venta: string,
     *         total_conf: string,
     *         total_conf_pct: int
     *     }
     * }
     */
    public function resumenFromSections(array $secciones, ?User $user = null, string $tab = 'este'): array
    {
        $entregadas = $secciones['entregadas'] ?? collect();
        $neutras = $secciones['neutras'] ?? collect();
        $descontar = $secciones['descontar'] ?? collect();

        $vipYaEran = $entregadas
            ->filter(fn (array $r) => ($r['fuente_corta'] ?? '') === 'VIP' && empty($r['es_nuevo_cliente']))
            ->count();

        $calleNuevos = $entregadas
            ->filter(fn (array $r) => ($r['fuente_corta'] ?? '') === 'CALLE' && ! empty($r['es_nuevo_cliente']))
            ->count();

        [$neutrasVip, $neutrasCalle] = $this->splitVipCalle($neutras);
        [$recogidasVip, $recogidasCalle] = $this->splitVipCalle($descontar);

        $saldo = $calleNuevos - $neutrasVip - $recogidasVip;

        $vtaEntregadas = $this->sumVta($entregadas);
        $vtaNeutras = $this->sumVta($neutras);
        $vtaDescontar = $this->sumVta($descontar);
        $vtaBrutas = $vtaEntregadas + $vtaNeutras;
        $vtaNetas = $vtaEntregadas - $vtaDescontar;
        // Comisionables a partir de la 7ª venta neta (las 6 primeras no cuentan).
        $vtaNetasComisionables = max(0.0, $vtaNetas - 6.0);
        // Rappel: 5 ohanapoints por venta neta a partir de la 5ª → 12 netas = +40.
        $rappelPts = (int) max(0, (int) floor($vtaNetas) - 4) * 5;
        $refBase = 220;
        $refComision = $refBase + $rappelPts;
        // Puntos pasados (PasC) en entregadas del periodo.
        $penPuntos = (int) $entregadas->sum(fn (array $r) => (int) ($r['pas_comercial'] ?? 0));
        // Carencia: sin campo propio aún → 0 (se ajustará cuando definamos Car).
        $penCarencia = 0;
        // Edad >80: 40 op por venta entregada con cliente >80.
        $penEdad = (int) $entregadas
            ->filter(fn (array $r) => ! empty($r['edad_gt_80_bool']))
            ->count() * 40;
        $kpi = $vtaBrutas > 0 ? (int) round(($vtaEntregadas / $vtaBrutas) * 100) : 0;

        return [
            'vip_ya_eran' => $vipYaEran,
            'calle_nuevos' => $calleNuevos,
            'neutras_vip_perdidos' => $neutrasVip,
            'neutras_calle' => $neutrasCalle,
            'recogidas_vip_perdidos' => $recogidasVip,
            'recogidas_calle' => $recogidasCalle,
            'saldo' => $saldo,
            'kpi' => $kpi,
            'kpi_emoji' => $kpi >= 80 ? '😊' : ($kpi >= 50 ? '😐' : '☹️'),
            'vtas_brutas' => $this->formatVta($vtaBrutas),
            'vtas_neutras' => $this->formatVta(-$vtaNeutras),
            'vtas_entregadas' => $this->formatVta($vtaEntregadas),
            'anulaciones_anteriores' => $this->formatVta(-$vtaDescontar),
            'vtas_netas' => $this->formatVta($vtaNetas),
            'vtas_netas_comisionables' => $this->formatVta($vtaNetasComisionables),
            'rappel_netas_label' => $this->formatVta($vtaNetas),
            'rappel_ohanapoints' => ($rappelPts > 0 ? '+' : '').(string) $rappelPts,
            'ref_comision_base' => $refBase,
            'ref_comision' => $refComision,
            'pen_puntos' => $penPuntos,
            'pen_carencia' => $penCarencia,
            'pen_edad' => $penEdad,
            'declaraciones' => $user
                ? $this->declaracionesForUser($user, $tab)
                : [
                    'ofis' => 'N/D',
                    'nulos' => '0',
                    'conf' => '0',
                    'vis_con_venta' => '0',
                    'total_conf' => '0',
                    'total_conf_pct' => 0,
                ],
        ];
    }

    /**
     * Declaraciones comerciales tras visita en el periodo de venta (mes comercial).
     *
     * @return array{
     *     ofis: string,
     *     nulos: string,
     *     conf: string,
     *     vis_con_venta: string,
     *     total_conf: string,
     *     total_conf_pct: int
     * }
     */
    public function declaracionesForUser(User $user, string $tab = 'este'): array
    {
        [$start, $end] = $this->rangeForTab($tab);

        $notes = Note::query()
            ->with([
                'confirmations' => fn ($q) => $q->latest('id'),
                'nullReasons' => fn ($q) => $q->latest('id'),
                'venta:id,note_id,companion_id,comercial_id',
            ])
            ->whereNotNull('fecha_declaracion')
            ->whereBetween('fecha_declaracion', [$start, $end])
            ->where(function (Builder $q) use ($user) {
                $q->where('comercial_id', $user->id)
                    ->orWhereHas('confirmations', fn (Builder $c) => $c->where('companion_id', $user->id))
                    ->orWhereHas('nullReasons', fn (Builder $c) => $c->where('companion_id', $user->id))
                    ->orWhereHas('venta', fn (Builder $c) => $c->where('companion_id', $user->id));
            })
            ->get();

        $ofis = 0.0;
        $nulos = 0.0;
        $conf = 0.0;
        $vis = 0.0;
        $ausentes = 0.0;

        foreach ($notes as $note) {
            $peso = $this->pesoDeclaracion($note, $user);
            $estado = $note->estado_terminal instanceof EstadoTerminal
                ? $note->estado_terminal
                : EstadoTerminal::tryFrom((string) ($note->estado_terminal ?? ''));

            match ($estado) {
                EstadoTerminal::SALA => $ofis += $peso,
                EstadoTerminal::NUL => $nulos += $peso,
                EstadoTerminal::CONFIRMADO => $conf += $peso,
                EstadoTerminal::VENTA => $vis += $peso,
                EstadoTerminal::AUSENTE => $ausentes += $peso,
                default => null,
            };
        }

        $totalConf = $conf + $vis;
        $denominador = $ofis + $nulos + $conf + $vis + $ausentes;
        $pct = $denominador > 0 ? (int) round(($totalConf / $denominador) * 100) : 0;

        return [
            'ofis' => $ofis > 0 ? $this->formatVta($ofis) : 'N/D',
            'nulos' => $this->formatVta($nulos),
            'conf' => $this->formatVta($conf),
            'vis_con_venta' => $this->formatVta($vis),
            'total_conf' => $this->formatVta($totalConf),
            'total_conf_pct' => $pct,
        ];
    }

    protected function pesoDeclaracion(Note $note, User $user): float
    {
        $confirmation = $note->confirmations->first();
        $nullReason = $note->nullReasons->first();
        $venta = $note->relationLoaded('venta') ? $note->venta : null;

        $companionId = $confirmation?->companion_id
            ?? $nullReason?->companion_id
            ?? $venta?->companion_id;

        if (! filled($companionId)) {
            return 1.0;
        }

        return 0.5;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     */
    protected function sumVta(Collection $filas): float
    {
        return (float) $filas->sum(function (array $r): float {
            return (float) str_replace(',', '.', (string) ($r['vta_propia'] ?? $r['vta_com'] ?? 0));
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return array{0: int, 1: int}
     */
    protected function splitVipCalle(Collection $filas): array
    {
        $vip = $filas->filter(fn (array $r) => ($r['fuente_corta'] ?? '') === 'VIP')->count();
        $calle = $filas->filter(fn (array $r) => ($r['fuente_corta'] ?? '') === 'CALLE')->count();

        return [$vip, $calle];
    }

    /**
     * Total de la venta del contrato. Si no hay ofertas, cuenta como 1.
     */
    public static function totalVentaContrato(?float $totalVentasOferta): float
    {
        if ($totalVentasOferta === null) {
            return 1.0;
        }

        return $totalVentasOferta;
    }

    /**
     * VTA.COM. es la parte del comercial principal.
     * VTA COM. AC. es la del acompañante: la misma cifra si hay compañero, 0 si no.
     *
     * @return array{principal: float, acompanante: float, compartida: bool}
     */
    public static function columnasVenta(float $total, ?int $comercialId, ?int $companionId): array
    {
        $hayCompanero = $companionId !== null && $companionId > 0 && $companionId !== $comercialId;

        if (! $hayCompanero) {
            return [
                'principal' => $total,
                'acompanante' => 0.0,
                'compartida' => false,
            ];
        }

        $mitad = $total / 2;

        return [
            'principal' => $mitad,
            'acompanante' => $mitad,
            'compartida' => true,
        ];
    }

    /**
     * Parte de un comercial. Con compañero distinto, cada uno se lleva la mitad
     * y las dos partes suman el total. Sin compañero, el comercial se lleva el total.
     * Quien no es comercial ni compañero (repartidor) ve el total del contrato.
     */
    public static function parteComercial(float $total, ?int $comercialId, ?int $companionId, int $userId): float
    {
        $esComercial = $comercialId !== null && $comercialId === $userId;
        $hayCompanero = $companionId !== null && $companionId > 0;
        $esCompanero = $hayCompanero && $companionId === $userId;
        $companeroDistinto = $hayCompanero && $companionId !== $comercialId;

        if (($esComercial || $esCompanero) && $companeroDistinto) {
            return $total / 2;
        }

        return $total;
    }

    /**
     * @return array{gt80: bool, texto: string}
     */
    protected function edadCliente(Venta $venta): array
    {
        $fecha = $venta->customer?->safeFechaNac();

        if (! $fecha) {
            return ['gt80' => false, 'texto' => '—'];
        }

        $diff = $fecha->diff(now());

        return [
            'gt80' => $diff->y >= 80,
            'texto' => "{$diff->y} años y {$diff->m} meses",
        ];
    }

    protected function highlightColumn(Venta $venta, User $user): ?string
    {
        if ((int) $venta->comercial_id === (int) $user->id) {
            return 'c1';
        }
        if ((int) $venta->companion_id === (int) $user->id) {
            return 'c2';
        }
        if ((int) $venta->repartidor_id === (int) $user->id) {
            return 'r1';
        }
        if ((int) $venta->repartidor_2 === (int) $user->id) {
            return 'r2';
        }

        return null;
    }

    protected function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0,00';
        }

        return number_format((float) $value, 2, ',', '.');
    }

    protected function formatVta(float $value): string
    {
        $rounded = round($value, 2);

        if (abs($rounded - round($rounded)) < 0.001) {
            return (string) (int) round($rounded);
        }

        return rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');
    }
}
