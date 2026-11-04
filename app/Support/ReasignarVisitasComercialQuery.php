<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Filtros compartidos del listado "Reasignar Visitas" y selectores de comercial.
 */
class ReasignarVisitasComercialQuery
{
    /** Empleados que no deben salir en reasignación (aunque baja venga vacía). */
    public const HIDDEN_EMPLEADO_IDS = [
        '011', // Lucía Taboada
        '041', // Diego Fernández
        '991', // Gerente Ventas / pool VIP (solo visible para gerente)
    ];

    /** 911 ADMI/contratos: visible para JV/gerente aunque can_login = false (JE no lo ve). */
    public const ALWAYS_VISIBLE_EMPLEADO_IDS = [
        '911',
    ];

    /** IDs ocultos a comerciales/JE pero visibles en panel gerente. */
    public const VISIBLE_TO_GERENTE_EMPLEADO_IDS = [
        '991', // pool VIP-INT (p. ej. VIPS sábado)
    ];

    /** IDs que el JE no debe ver (además de HIDDEN_EMPLEADO_IDS). */
    public const HIDDEN_FROM_TEAM_LEADER_EMPLEADO_IDS = [
        '911', // ADMI / contratos
    ];

    /** Roles de perfil gerente/JV que un JE no debe ver en reasignación. */
    public const HIDDEN_FROM_TEAM_LEADER_ROLES = [
        'gerente',
        'gerente_general',
        'sales_manager',
    ];

    /**
     * ¿Este usuario debe ocultarse en listados de comerciales operativos?
     */
    public static function shouldHideComercial(object $user, bool $forGerente = false): bool
    {
        $empleadoId = (string) ($user->empleado_id ?? '');

        if (in_array($empleadoId, self::hiddenEmpleadoIds($forGerente), true)) {
            return true;
        }

        if (($user->baja ?? null) !== null) {
            return true;
        }

        if (! $forGerente && self::viewerIsTeamLeaderOnly()) {
            if (in_array($empleadoId, self::HIDDEN_FROM_TEAM_LEADER_EMPLEADO_IDS, true)) {
                return true;
            }

            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(self::HIDDEN_FROM_TEAM_LEADER_ROLES)) {
                return true;
            }
        }

        $full = mb_strtolower(trim(($user->name ?? '').' '.($user->last_name ?? '')), 'UTF-8');

        return str_contains($full, 'diego')
            || str_contains($full, 'lucia')
            || str_contains($full, 'lucía');
    }

    /**
     * @return list<string>
     */
    public static function hiddenEmpleadoIds(bool $forGerente = false): array
    {
        if (! $forGerente) {
            return self::HIDDEN_EMPLEADO_IDS;
        }

        return array_values(array_diff(
            self::HIDDEN_EMPLEADO_IDS,
            self::VISIBLE_TO_GERENTE_EMPLEADO_IDS
        ));
    }

    /**
     * JE puro (sin rol JV): no ve perfiles de gerente / sales_manager.
     */
    public static function viewerIsTeamLeaderOnly(?User $viewer = null): bool
    {
        $viewer ??= auth()->user();

        return (bool) ($viewer
            && $viewer->hasRole('team_leader')
            && ! $viewer->hasRole('sales_manager')
            && ! $viewer->hasAnyRole(['gerente', 'gerente_general']));
    }

    /**
     * Solo comerciales que siguen en la app: sin baja, con login permitido,
     * y sin los IDs/nombres excluidos.
     *
     * @param  Builder<\App\Models\User>  $query
     * @return Builder<\App\Models\User>
     */
    public static function applyHiddenComerciales(Builder $query, bool $forGerente = false): Builder
    {
        // `baja` es datetime nullable: solo IS NULL (no comparar con '').
        $query
            ->whereNull('baja')
            ->whereNotIn('empleado_id', self::hiddenEmpleadoIds($forGerente))
            ->whereRaw(
                "LOWER(CONCAT(COALESCE(name,''), ' ', COALESCE(last_name,''))) NOT LIKE ?",
                ['%diego%']
            )
            ->whereRaw(
                "LOWER(CONCAT(COALESCE(name,''), ' ', COALESCE(last_name,''))) NOT LIKE ?",
                ['%lucia%']
            )
            ->whereRaw(
                "LOWER(CONCAT(COALESCE(name,''), ' ', COALESCE(last_name,''))) NOT LIKE ?",
                ['%lucía%']
            );

        if (Schema::hasColumn('users', 'can_login')) {
            $query->where(function (Builder $q) use ($forGerente) {
                $q->whereNull('can_login')->orWhere('can_login', true);

                // 911 visible para JV/gerente aunque can_login = false
                $q->orWhereIn('empleado_id', self::ALWAYS_VISIBLE_EMPLEADO_IDS);

                // 991 solo en panel gerente
                if ($forGerente) {
                    $q->orWhereIn('empleado_id', self::VISIBLE_TO_GERENTE_EMPLEADO_IDS);
                }
            });
        }

        // JE: sin 911, ni perfiles gerente / JV
        if (! $forGerente && self::viewerIsTeamLeaderOnly()) {
            $query
                ->whereNotIn('empleado_id', self::HIDDEN_FROM_TEAM_LEADER_EMPLEADO_IDS)
                ->whereDoesntHave('roles', function (Builder $q) {
                    $q->whereIn('name', self::HIDDEN_FROM_TEAM_LEADER_ROLES);
                });
        }

        return $query;
    }
}
