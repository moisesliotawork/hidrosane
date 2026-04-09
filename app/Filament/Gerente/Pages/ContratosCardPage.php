<?php

namespace App\Filament\Gerente\Pages;

use App\Models\Venta;
use Filament\Pages\Page;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;

class ContratosCardPage extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Acción Contratos';
    protected static ?string $title = 'Acción de Contratos';
    protected static ?string $slug = 'contratos-cards';
    protected static string $view = 'filament.gerente.pages.contratos-card-page';
    protected static ?int $navigationSort = -1;

    public string $search = '';
    public string $sortBy = 'fecha_venta';
    public string $sortDir = 'desc';
    public int $perPage = 24;

    protected $queryString = ['search', 'sortBy', 'sortDir'];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function setSort(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDir = 'desc';
        }
        $this->resetPage();
    }

    #[Computed]
    public function ventas()
    {
        return Venta::query()
            ->with(['customer', 'comercial', 'companion', 'note.user', 'note.observations.author', 'note.observacionesSala.author', 'ventaOfertas.oferta', 'ventaOfertas.productos.producto'])
            ->when($this->search, function ($q) {
                $s = $this->search;
                $q->where(function ($q) use ($s) {
                    $q->where('nro_contr_adm', 'like', "%{$s}%")
                      ->orWhere('nro_cliente_adm', 'like', "%{$s}%")
                      ->orWhereHas('customer', fn($cq) => $cq
                            ->where('first_names', 'like', "%{$s}%")
                            ->orWhere('last_names', 'like', "%{$s}%")
                            ->orWhere('phone', 'like', "%{$s}%")
                            ->orWhere('secondary_phone', 'like', "%{$s}%")
                            ->orWhere('third_phone', 'like', "%{$s}%")
                            ->orWhere('phone1_commercial', 'like', "%{$s}%")
                            ->orWhere('phone2_commercial', 'like', "%{$s}%")
                      )
                      ->orWhereHas('note', fn($nq) => $nq->where('nro_nota', 'like', "%{$s}%"));
                });
            })
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->perPage);
    }
}
