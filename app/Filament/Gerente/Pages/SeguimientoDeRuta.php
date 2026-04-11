<?php

namespace App\Filament\Gerente\Pages;

use App\Enums\EstadoTerminal;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SeguimientoDeRuta extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Seguimiento de ruta';
    protected static ?string $slug = 'seguimiento-de-ruta';
    protected static ?int $navigationSort = 11;

    protected static string $view = 'filament.gerente.pages.seguimiento-de-ruta';

    public string $selectedDay = 'hoy';

    protected ?Collection $comercialesCache = null;

    public function getTitle(): string
    {
        return 'Seguimiento de ruta';
    }

    public function getReportDaysProperty(): array
    {
        return [
            [
                'key' => 'hoy',
                'label' => 'HOY',
                'date' => today(),
            ],
            [
                'key' => 'ayer',
                'label' => 'AYER',
                'date' => today()->subDay(),
            ],
        ];
    }

    public function getSelectedReportDayProperty(): array
    {
        return collect($this->reportDays)
            ->firstWhere('key', $this->selectedDay)
            ?? $this->reportDays[0];
    }

    public function setSelectedDay(string $day): void
    {
        if (! in_array($day, ['hoy', 'ayer'], true)) {
            return;
        }

        $this->selectedDay = $day;
    }

    public function getComercialesProperty(): Collection
    {
        if ($this->comercialesCache instanceof Collection) {
            return $this->comercialesCache;
        }

        $today = today();
        $yesterday = today()->subDay();

        return $this->comercialesCache = User::query()
            ->role(['commercial', 'team_leader', 'sales_manager'])
            ->whereNull('baja')
            ->with([
                'notasDeclaradas' => fn($query) => $this->activeNotesQuery($query, $yesterday, $today),
                'notasDeclaradas.customer',
                'notasDeclaradas.venta',
                'notasDeclaradas.anotacionesVisitas.autor',
            ])
            ->orderBy('empleado_id')
            ->orderBy('name')
            ->orderBy('last_name')
            ->get();
    }

    protected function activeNotesQuery($query, Carbon $from, Carbon $to): void
    {
        $query
            ->whereDate('assignment_date', '>=', $from->toDateString())
            ->whereDate('assignment_date', '<=', $to->toDateString())
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query
                        ->where(function ($query) {
                            $query
                                ->whereNull('estado_terminal')
                                ->orWhere('estado_terminal', '')
                                ->orWhereRaw('LOWER(TRIM(estado_terminal)) = ?', [EstadoTerminal::AUSENTE->value]);
                        })
                        ->whereDoesntHave('venta')
                        ->where(function ($query) {
                            $query
                                ->whereNull('reten')
                                ->orWhere('reten', false);
                        });
                })
                    ->orWhereDate('fecha_declaracion', today()->toDateString());
            })
            ->orderBy('assignment_date')
            ->orderByRaw('CAST(nro_nota AS UNSIGNED) ASC');
    }
}
