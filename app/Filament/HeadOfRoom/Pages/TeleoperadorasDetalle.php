<?php

namespace App\Filament\HeadOfRoom\Pages;

use App\Enums\EstadoTerminal;
use App\Models\User;
use Carbon\Carbon;
use Filament\Pages\Page;
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
                    ->whereNotNull('comercial_id')
                    ->whereBetween('created_at', [$start, $end]),
            ])
            ->orderBy('empleado_id')
            ->distinct()
            ->get();
    }
}
