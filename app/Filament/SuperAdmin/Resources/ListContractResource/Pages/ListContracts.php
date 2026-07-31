<?php

namespace App\Filament\SuperAdmin\Resources\ListContractResource\Pages;

use App\Filament\SuperAdmin\Resources\ListContractResource;
use App\Models\Venta;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ListContracts extends ListRecords
{
    protected static string $resource = ListContractResource::class;

    protected static string $view = 'filament.super-admin.resources.list-contract.list-contracts';

    /** Mes seleccionado (Y-m). Null + showAllMonths = Todos. */
    public ?string $selectedYearMonth = null;

    public bool $showAllMonths = false;

    /** Año del filtro de meses (por defecto el actual). */
    public int $selectedYear;

    public function mount(): void
    {
        parent::mount();

        $today = Carbon::today();
        $this->selectedYear = (int) $today->year;
        $this->selectedYearMonth = $today->format('Y-m');
        $this->showAllMonths = false;
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if ($this->showAllMonths || blank($this->selectedYearMonth)) {
            return $query;
        }

        try {
            $date = Carbon::createFromFormat('Y-m', $this->selectedYearMonth)->startOfMonth();
        } catch (\Throwable) {
            return $query;
        }

        return $query
            ->whereYear('fecha_venta', $date->year)
            ->whereMonth('fecha_venta', $date->month);
    }

    /**
     * @return list<int>
     */
    public function availableYears(): array
    {
        $current = (int) Carbon::today()->year;
        $minYear = (int) (Venta::query()
            ->whereNotNull('fecha_venta')
            ->min(DB::raw('YEAR(fecha_venta)')) ?: $current);

        return range($current, min($minYear, $current - 5));
    }

    /**
     * @return array<int, array{label: string, bg: string, border: string, text: string}>
     */
    public function monthBadges(): array
    {
        return [
            1 => ['label' => 'ENERO', 'bg' => '#fde8e8', 'border' => '#f5c2c2', 'text' => '#9f1239'],
            2 => ['label' => 'FEBRERO', 'bg' => '#fce7f3', 'border' => '#f0abcf', 'text' => '#9d174d'],
            3 => ['label' => 'MARZO', 'bg' => '#f3e8ff', 'border' => '#d8b4fe', 'text' => '#6b21a8'],
            4 => ['label' => 'ABRIL', 'bg' => '#ede9fe', 'border' => '#c4b5fd', 'text' => '#5b21b6'],
            5 => ['label' => 'MAYO', 'bg' => '#e0e7ff', 'border' => '#a5b4fc', 'text' => '#3730a3'],
            6 => ['label' => 'JUNIO', 'bg' => '#e0f2fe', 'border' => '#7dd3fc', 'text' => '#075985'],
            7 => ['label' => 'JULIO', 'bg' => '#ccfbf1', 'border' => '#5eead4', 'text' => '#115e59'],
            8 => ['label' => 'AGOSTO', 'bg' => '#d1fae5', 'border' => '#6ee7b7', 'text' => '#065f46'],
            9 => ['label' => 'SEPTIEMBRE', 'bg' => '#ecfccb', 'border' => '#bef264', 'text' => '#3f6212'],
            10 => ['label' => 'OCTUBRE', 'bg' => '#fef9c3', 'border' => '#fde047', 'text' => '#854d0e'],
            11 => ['label' => 'NOVIEMBRE', 'bg' => '#ffedd5', 'border' => '#fdba74', 'text' => '#9a3412'],
            12 => ['label' => 'DICIEMBRE', 'bg' => '#fee2e2', 'border' => '#fca5a5', 'text' => '#991b1b'],
        ];
    }

    public function selectedBadgeMonth(): ?int
    {
        if ($this->showAllMonths || blank($this->selectedYearMonth)) {
            return null;
        }

        try {
            return (int) Carbon::createFromFormat('Y-m', $this->selectedYearMonth)->month;
        } catch (\Throwable) {
            return null;
        }
    }

    public function selectCalendarMonth(int $month): void
    {
        $month = max(1, min(12, $month));
        $this->selectedYearMonth = sprintf('%04d-%02d', $this->selectedYear, $month);
        $this->showAllMonths = false;
        $this->resetTable();
    }

    public function showAllPayments(): void
    {
        $this->showAllMonths = true;
        $this->selectedYearMonth = null;
        $this->resetTable();
    }

    public function updatedSelectedYear(mixed $value): void
    {
        $this->selectedYear = (int) $value;

        if (! $this->showAllMonths && filled($this->selectedYearMonth)) {
            try {
                $month = (int) Carbon::createFromFormat('Y-m', $this->selectedYearMonth)->month;
            } catch (\Throwable) {
                $month = (int) Carbon::today()->month;
            }

            $this->selectedYearMonth = sprintf('%04d-%02d', $this->selectedYear, $month);
        }

        $this->resetTable();
    }

    public function selectedPeriodLabel(): ?string
    {
        if ($this->showAllMonths || blank($this->selectedYearMonth)) {
            return 'Todos los contratos';
        }

        try {
            $selected = Carbon::createFromFormat('Y-m', $this->selectedYearMonth)->locale('es');

            return 'Contratos de ' . ucfirst($selected->translatedFormat('F Y'));
        } catch (\Throwable) {
            return null;
        }
    }
}
