<?php

namespace App\Filament\SuperAdmin\Resources\ListaAmanoResource\Pages;

use App\Filament\SuperAdmin\Resources\ListaAmanoResource;
use App\Models\ListaAmano;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;

class ListListaAmanos extends ListRecords
{
    protected static string $resource = ListaAmanoResource::class;

    protected static string $view = 'filament.super-admin.resources.lista-amano.list-lista-amanos';

    /** Mes seleccionado (Y-m). Null + showAllMonths = Todos. */
    public ?string $selectedYearMonth = null;

    public bool $showAllMonths = true;

    public int $selectedYear;

    public function mount(): void
    {
        parent::mount();

        $this->selectedYear = (int) (session('lista_amano.selectedYear') ?: now()->year);
        $this->selectedYearMonth = session('lista_amano.selectedYearMonth');
        $this->showAllMonths = (bool) session('lista_amano.showAllMonths', true);

        if ($this->showAllMonths) {
            $this->selectedYearMonth = null;
        }
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo registro'),
        ];
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if ($this->showAllMonths || blank($this->selectedYearMonth)) {
            return $query;
        }

        try {
            [$year, $month] = array_map('intval', explode('-', $this->selectedYearMonth));
        } catch (\Throwable) {
            return $query;
        }

        return $query
            ->where('anio', $year)
            ->where('mes', $month);
    }

    /**
     * Recarga la tabla sin borrar el filtro de cliente.
     */
    protected function refreshTableKeepingFilters(): void
    {
        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    protected function persistMonthSelection(): void
    {
        session([
            'lista_amano.selectedYear' => $this->selectedYear,
            'lista_amano.selectedYearMonth' => $this->selectedYearMonth,
            'lista_amano.showAllMonths' => $this->showAllMonths,
        ]);
    }

    public function clienteSearchQuery(): string
    {
        return trim((string) data_get($this->tableFilters, 'cliente_nombre.q', ''));
    }

    /**
     * Meses (1-12) del año seleccionado donde el cliente buscado tiene registros.
     *
     * @return list<int>
     */
    public function monthsWithClienteActivity(): array
    {
        $q = $this->clienteSearchQuery();
        if ($q === '') {
            return [];
        }

        return ListaAmano::query()
            ->where('anio', $this->selectedYear)
            ->where('cliente', 'like', '%'.$q.'%')
            ->distinct()
            ->orderBy('mes')
            ->pluck('mes')
            ->map(fn ($m) => (int) $m)
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function availableYears(): array
    {
        $current = (int) now()->year;
        $years = ListaAmano::query()
            ->distinct()
            ->orderByDesc('anio')
            ->pluck('anio')
            ->map(fn ($y) => (int) $y)
            ->all();

        if ($years === []) {
            return [$current, $current - 1];
        }

        if (! in_array($current, $years, true)) {
            array_unshift($years, $current);
        }

        return $years;
    }

    /**
     * @return array<int, array{label: string, bg: string, border: string, text: string}>
     */
    public function monthBadges(): array
    {
        return [
            1 => ['label' => 'ENE', 'full' => 'Enero', 'bg' => '#fde8e8', 'border' => '#f5c2c2', 'text' => '#9f1239'],
            2 => ['label' => 'FEB', 'full' => 'Febrero', 'bg' => '#fce7f3', 'border' => '#f0abcf', 'text' => '#9d174d'],
            3 => ['label' => 'MAR', 'full' => 'Marzo', 'bg' => '#f3e8ff', 'border' => '#d8b4fe', 'text' => '#6b21a8'],
            4 => ['label' => 'ABR', 'full' => 'Abril', 'bg' => '#ede9fe', 'border' => '#c4b5fd', 'text' => '#5b21b6'],
            5 => ['label' => 'MAY', 'full' => 'Mayo', 'bg' => '#e0e7ff', 'border' => '#a5b4fc', 'text' => '#3730a3'],
            6 => ['label' => 'JUN', 'full' => 'Junio', 'bg' => '#e0f2fe', 'border' => '#7dd3fc', 'text' => '#075985'],
            7 => ['label' => 'JUL', 'full' => 'Julio', 'bg' => '#ccfbf1', 'border' => '#5eead4', 'text' => '#115e59'],
            8 => ['label' => 'AGO', 'full' => 'Agosto', 'bg' => '#d1fae5', 'border' => '#6ee7b7', 'text' => '#065f46'],
            9 => ['label' => 'SEP', 'full' => 'Septiembre', 'bg' => '#ecfccb', 'border' => '#bef264', 'text' => '#3f6212'],
            10 => ['label' => 'OCT', 'full' => 'Octubre', 'bg' => '#fef9c3', 'border' => '#fde047', 'text' => '#854d0e'],
            11 => ['label' => 'NOV', 'full' => 'Noviembre', 'bg' => '#ffedd5', 'border' => '#fdba74', 'text' => '#9a3412'],
            12 => ['label' => 'DIC', 'full' => 'Diciembre', 'bg' => '#fee2e2', 'border' => '#fca5a5', 'text' => '#991b1b'],
        ];
    }

    public function selectedBadgeMonth(): ?int
    {
        if ($this->showAllMonths || blank($this->selectedYearMonth)) {
            return null;
        }

        try {
            return (int) explode('-', $this->selectedYearMonth)[1];
        } catch (\Throwable) {
            return null;
        }
    }

    public function selectCalendarMonth(int $month): void
    {
        $month = max(1, min(12, $month));
        $this->selectedYearMonth = sprintf('%04d-%02d', $this->selectedYear, $month);
        $this->showAllMonths = false;
        $this->persistMonthSelection();
        $this->refreshTableKeepingFilters();
    }

    public function showAllPayments(): void
    {
        $this->showAllMonths = true;
        $this->selectedYearMonth = null;
        $this->persistMonthSelection();
        $this->refreshTableKeepingFilters();
    }

    public function updatedSelectedYear(mixed $value): void
    {
        $this->selectedYear = (int) $value;

        if (! $this->showAllMonths && filled($this->selectedYearMonth)) {
            $month = $this->selectedBadgeMonth() ?? (int) now()->month;
            $this->selectedYearMonth = sprintf('%04d-%02d', $this->selectedYear, $month);
        }

        $this->persistMonthSelection();
        $this->refreshTableKeepingFilters();
    }

    public function selectedPeriodLabel(): ?string
    {
        if ($this->showAllMonths || blank($this->selectedYearMonth)) {
            return 'Todos los registros';
        }

        $badges = $this->monthBadges();
        $month = $this->selectedBadgeMonth();
        $label = $badges[$month]['full'] ?? ($badges[$month]['label'] ?? 'MES');

        return $label.' '.$this->selectedYear;
    }
}
