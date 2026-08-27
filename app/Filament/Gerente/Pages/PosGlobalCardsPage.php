<?php

namespace App\Filament\Gerente\Pages;

use App\Filament\Gerente\Resources\CustomerResource;
use App\Filament\Support\CustomerPosicionGlobalTable;
use App\Models\Customer;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

class PosGlobalCardsPage extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationLabel = 'PosGlobal.Cards';

    protected static ?string $title = 'PosGlobal.Cards';

    protected static ?string $slug = 'pos-global-cards';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.gerente.pages.pos-global-cards';

    public string $search = '';

    public int $perPage = 20;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function customers()
    {
        return CustomerPosicionGlobalTable::applySearch(
            CustomerPosicionGlobalTable::applyEagerLoads(Customer::query()),
            $this->search
        )
            ->orderByDesc('id')
            ->paginate($this->perPage);
    }

    public function viewUrl(Customer $customer): string
    {
        return CustomerResource::getUrl('view', ['record' => $customer], panel: 'gerente');
    }
}
