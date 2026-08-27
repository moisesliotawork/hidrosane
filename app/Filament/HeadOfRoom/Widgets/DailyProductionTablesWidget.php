<?php

namespace App\Filament\HeadOfRoom\Widgets;

use App\Enums\EstadoTerminal;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

class DailyProductionTablesWidget extends Widget
{
    protected static string $view = 'filament.head-of-room.widgets.daily-production-tables-widget';

    protected static bool $isDiscovered = false;

    protected int | string | array $columnSpan = 'full';

    #[Computed]
    public function data(): array
    {
        $tz  = config('app.timezone', 'UTC');
        $now = Carbon::now($tz);

        $currStart = $now->copy()->startOfMonth()->startOfDay()->toDateTimeString();
        $currEnd   = $now->copy()->endOfMonth()->endOfDay()->toDateTimeString();
        $prevStart = $now->copy()->subMonth()->startOfMonth()->startOfDay()->toDateTimeString();
        $prevEnd   = $now->copy()->subMonth()->endOfMonth()->endOfDay()->toDateTimeString();

        $prevMonth = $now->copy()->subMonth()->startOfMonth()->toDateString();

        $teleops = User::query()
            ->select('users.id', 'users.empleado_id', 'users.name', 'users.last_name')
            ->role(['teleoperator', 'head_of_room'])
            ->where(function ($q) use ($prevMonth) {
                $q->whereNull('users.baja')->orWhereDate('users.baja', '>=', $prevMonth);
            })
            ->whereNotIn('users.empleado_id', ['038', '046'])
            ->orderBy('users.empleado_id')
            ->distinct()
            ->get();

        $userIds = $teleops->pluck('id');

        $fetchProd = function (string $start, string $end) use ($userIds) {
            return DB::table('notes')
                ->selectRaw('user_id, DAY(created_at) as day, COUNT(*) as total')
                ->whereIn('user_id', $userIds)
                ->whereBetween('created_at', [$start, $end])
                ->groupBy('user_id', DB::raw('DAY(created_at)'))
                ->get()
                ->groupBy('user_id')
                ->map(fn ($rows) => $rows->pluck('total', 'day')
                    ->mapWithKeys(fn ($v, $k) => [(int) $k => (int) $v]));
        };

        $fetchByDeclaracion = function (string $start, string $end, string $estado) use ($userIds) {
            return DB::table('notes')
                ->selectRaw('user_id, DAY(fecha_declaracion) as day, COUNT(*) as total')
                ->whereIn('user_id', $userIds)
                ->where('estado_terminal', $estado)
                ->whereBetween('fecha_declaracion', [$start, $end])
                ->groupBy('user_id', DB::raw('DAY(fecha_declaracion)'))
                ->get()
                ->groupBy('user_id')
                ->map(fn ($rows) => $rows->pluck('total', 'day')
                    ->mapWithKeys(fn ($v, $k) => [(int) $k => (int) $v]));
        };

        return [
            'teleops'     => $teleops,
            'curr'        => $fetchProd($currStart, $currEnd),
            'prev'        => $fetchProd($prevStart, $prevEnd),
            'ventas_curr' => $fetchByDeclaracion($currStart, $currEnd, EstadoTerminal::VENTA->value),
            'ventas_prev' => $fetchByDeclaracion($prevStart, $prevEnd, EstadoTerminal::VENTA->value),
            'conf_curr'   => $fetchByDeclaracion($currStart, $currEnd, EstadoTerminal::CONFIRMADO->value),
            'conf_prev'   => $fetchByDeclaracion($prevStart, $prevEnd, EstadoTerminal::CONFIRMADO->value),
            'nulas_curr'  => $fetchByDeclaracion($currStart, $currEnd, EstadoTerminal::NUL->value),
            'nulas_prev'  => $fetchByDeclaracion($prevStart, $prevEnd, EstadoTerminal::NUL->value),
            'curr_days'   => $now->copy()->daysInMonth(),
            'prev_days'   => $now->copy()->subMonth()->daysInMonth(),
            'curr_label'  => ucfirst($now->copy()->locale('es')->translatedFormat('M Y')),
            'prev_label'  => ucfirst($now->copy()->subMonth()->locale('es')->translatedFormat('M Y')),
        ];
    }
}
