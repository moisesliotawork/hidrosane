<?php

namespace App\Filament\Gerente\Pages;

use App\Models\PuntoComercialReport;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

class PuntoComercialPage extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'PUNTO COMERCIAL';

    protected static ?string $title = 'PUNTO COMERCIAL';

    protected static ?string $slug = 'punto-comercial';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.gerente.pages.punto-comercial';

    public string $selectedTab = 'hoy';

    public ?string $fechaFiltro = null;

    protected $queryString = [
        'fechaFiltro' => ['except' => ''],
    ];

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['todos', 'hoy', 'ayer'], true)) {
            return;
        }

        $this->selectedTab = $tab;
        $this->resetPage();
    }

    public function updatingFechaFiltro(): void
    {
        $this->resetPage();
    }

    public function clearFechaFiltro(): void
    {
        $this->fechaFiltro = null;
        $this->resetPage();
    }

    #[Computed]
    public function tabCounts(): array
    {
        $base = PuntoComercialReport::query();
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        return [
            'todos' => (clone $base)->count(),
            'hoy' => (clone $base)->whereDate('report_date', $today)->count(),
            'ayer' => (clone $base)->whereDate('report_date', $yesterday)->count(),
        ];
    }

    #[Computed]
    public function reports()
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        return PuntoComercialReport::query()
            ->with('teamLeader:id,name,last_name,empleado_id')
            ->when($this->selectedTab === 'hoy', fn($q) => $q->whereDate('report_date', $today))
            ->when($this->selectedTab === 'ayer', fn($q) => $q->whereDate('report_date', $yesterday))
            ->when(filled($this->fechaFiltro), fn($q) => $q->whereDate('report_date', $this->fechaFiltro))
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(12);
    }
}
