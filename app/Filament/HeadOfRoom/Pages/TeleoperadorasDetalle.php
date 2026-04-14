<?php

namespace App\Filament\HeadOfRoom\Pages;

use App\Enums\EstadoTerminal;
use App\Models\User;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

class TeleoperadorasDetalle extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    protected static ?string $navigationLabel = 'Detalle Teleop.';
    protected static ?string $title = 'Detalle de Notas por Teleoperadora';
    protected static ?string $slug = 'teleoperadoras-detalle';
    protected static string $view = 'filament.head-of-room.pages.teleoperadoras-detalle';

    public string $periodo = 'this';

    public function setPeriodo(string $p): void
    {
        $this->periodo = $p;
    }

    #[Computed]
    public function dailyProduccion(): array
    {
        $tz  = config('app.timezone', 'UTC');
        $now = Carbon::now($tz);

        $currStart = $now->copy()->startOfMonth()->startOfDay()->toDateTimeString();
        $currEnd   = $now->copy()->endOfMonth()->endOfDay()->toDateTimeString();
        $prevStart = $now->copy()->subMonth()->startOfMonth()->startOfDay()->toDateTimeString();
        $prevEnd   = $now->copy()->subMonth()->endOfMonth()->endOfDay()->toDateTimeString();

        $userIds  = $this->teleoperadoras->pluck('id');

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
            'curr'            => $fetchProd($currStart, $currEnd),
            'prev'            => $fetchProd($prevStart, $prevEnd),
            'ventas_curr'     => $fetchByDeclaracion($currStart, $currEnd, EstadoTerminal::VENTA->value),
            'ventas_prev'     => $fetchByDeclaracion($prevStart, $prevEnd, EstadoTerminal::VENTA->value),
            'conf_curr'       => $fetchByDeclaracion($currStart, $currEnd, EstadoTerminal::CONFIRMADO->value),
            'conf_prev'       => $fetchByDeclaracion($prevStart, $prevEnd, EstadoTerminal::CONFIRMADO->value),
            'curr_days'       => $now->copy()->daysInMonth(),
            'prev_days'       => $now->copy()->subMonth()->daysInMonth(),
            'curr_label'      => ucfirst($now->copy()->locale('es')->translatedFormat('M Y')),
            'prev_label'      => ucfirst($now->copy()->subMonth()->locale('es')->translatedFormat('M Y')),
        ];
    }

    #[Computed]
    public function teleoperadoras()
    {
        $tz     = config('app.timezone', 'UTC');
        $offset = $this->periodo === 'prev' ? 1 : 0;

        $start = Carbon::now($tz)->subMonths($offset)->startOfMonth()->startOfDay()->toDateTimeString();
        $end   = Carbon::now($tz)->subMonths($offset)->endOfMonth()->endOfDay()->toDateTimeString();

        $prevMonth = Carbon::now($tz)->subMonth()->startOfMonth()->toDateString();

        return User::query()
            ->select('users.*')
            ->role(['teleoperator', 'head_of_room'])
            ->where(function ($q) use ($prevMonth) {
                $q->whereNull('baja')->orWhereDate('baja', '>=', $prevMonth);
            })
            ->with(['notes' => function ($q) use ($start, $end) {
                $q->whereIn('estado_terminal', [EstadoTerminal::VENTA->value, EstadoTerminal::CONFIRMADO->value])
                    ->whereBetween('fecha_declaracion', [$start, $end])
                    ->with(['customer', 'venta'])
                    ->orderBy('created_at', 'desc');
            }])
            ->withCount([
                'notes as confirmadas_count' => fn ($q) => $q
                    ->where('estado_terminal', EstadoTerminal::CONFIRMADO->value)
                    ->whereBetween('fecha_declaracion', [$start, $end]),
                'notes as vendidas_count' => fn ($q) => $q
                    ->where('estado_terminal', EstadoTerminal::VENTA->value)
                    ->whereBetween('fecha_declaracion', [$start, $end]),
                'notes as aproduccion_count' => fn ($q) => $q
                    ->whereBetween('created_at', [$start, $end]),
            ])
            ->orderBy('empleado_id')
            ->distinct()
            ->get();
    }
}
