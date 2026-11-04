<?php

namespace App\Filament\Shared\Pages;

use App\Support\TotalVtasComReport;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class TotalVtasComPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'TotalVtasCom';

    protected static ?string $title = 'Total ventas en solitario';

    protected static ?string $slug = 'total-vtas-com';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.total-vtas-com';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole([
            'admin',
            'app_support',
            'gerente_general',
            'gerente',
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function filas(): Collection
    {
        return app(TotalVtasComReport::class)->rows();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $filas = $this->filas();

        return [
            'filas' => $filas,
            'totalContratos' => $filas->sum('contratos'),
            'totalClientes' => $filas->sum('clientes'),
        ];
    }
}
