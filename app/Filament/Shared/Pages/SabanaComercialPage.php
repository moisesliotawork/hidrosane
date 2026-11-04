<?php

namespace App\Filament\Shared\Pages;

use App\Models\User;
use App\Support\SabanaComercialReport;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class SabanaComercialPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Sábana Comercial';

    protected static ?string $title = 'Sábana Comercial';

    protected static ?string $slug = 'sabana-comercial';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.sabana-comercial';

    public ?int $userId = null;

    /** @var 'este'|'anterior' */
    public string $periodoTab = 'este';

    public bool $seccionEntregadas = true;

    public bool $seccionNeutras = true;

    public bool $seccionDescontar = true;

    public function mount(): void
    {
        $userId = request()->integer('user');
        $this->userId = $userId > 0 ? $userId : null;
        $this->periodoTab = 'este';
        $this->abrirTodasSecciones();
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole([
            'app_support',
            'gerente_general',
            'gerente',
        ]);
    }

    public function openUser(int $userId): void
    {
        $this->userId = $userId;
        $this->periodoTab = 'este';
        $this->abrirTodasSecciones();
    }

    public function backToList(): void
    {
        $this->userId = null;
        $this->periodoTab = 'este';
        $this->abrirTodasSecciones();
    }

    public function setPeriodoTab(string $tab): void
    {
        if (in_array($tab, ['este', 'anterior'], true)) {
            $this->periodoTab = $tab;
        }
    }

    public function hayAlgunaSeccionAbierta(): bool
    {
        return $this->seccionEntregadas || $this->seccionNeutras || $this->seccionDescontar;
    }

    public function toggleDetalles(): void
    {
        if ($this->hayAlgunaSeccionAbierta()) {
            $this->seccionEntregadas = false;
            $this->seccionNeutras = false;
            $this->seccionDescontar = false;
        } else {
            $this->abrirTodasSecciones();
        }
    }

    public function toggleSeccion(string $seccion): void
    {
        match ($seccion) {
            'entregadas' => $this->seccionEntregadas = ! $this->seccionEntregadas,
            'neutras' => $this->seccionNeutras = ! $this->seccionNeutras,
            'descontar' => $this->seccionDescontar = ! $this->seccionDescontar,
            default => null,
        };
    }

    protected function abrirTodasSecciones(): void
    {
        $this->seccionEntregadas = true;
        $this->seccionNeutras = true;
        $this->seccionDescontar = true;
    }

    public function selectedUser(): ?User
    {
        if (! $this->userId) {
            return null;
        }

        return User::query()->find($this->userId);
    }

    /**
     * @return array{jefes: Collection, comerciales: Collection, repartidores: Collection}
     */
    public function people(): array
    {
        return app(SabanaComercialReport::class)->people();
    }

    /**
     * @return array{entregadas: Collection, neutras: Collection, descontar: Collection}
     */
    public function secciones(): array
    {
        $user = $this->selectedUser();

        if (! $user) {
            return [
                'entregadas' => collect(),
                'neutras' => collect(),
                'descontar' => collect(),
            ];
        }

        return app(SabanaComercialReport::class)->sectionsForUser($user, $this->periodoTab);
    }

    public function userLabel(?User $user): string
    {
        if (! $user) {
            return '';
        }

        return trim(($user->empleado_id ?? '').' '.mb_strtoupper(trim($user->name.' '.($user->last_name ?? ''))));
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = $this->selectedUser();
        $secciones = $user
            ? $this->secciones()
            : ['entregadas' => collect(), 'neutras' => collect(), 'descontar' => collect()];
        $todas = $secciones['entregadas']
            ->concat($secciones['neutras'])
            ->concat($secciones['descontar']);
        $report = app(SabanaComercialReport::class);
        $periodos = $report->periodosFromRows($todas);
        $resumen = $user
            ? $report->resumenFromSections($secciones, $user, $this->periodoTab)
            : null;

        return [
            'people' => $this->people(),
            'selectedUser' => $user,
            'userLabel' => $this->userLabel($user),
            'filasEntregadas' => $secciones['entregadas'],
            'filasNeutras' => $secciones['neutras'],
            'filasDescontar' => $secciones['descontar'],
            'periodoReparto' => $periodos['reparto'],
            'periodoVenta' => $periodos['venta'],
            'resumen' => $resumen,
        ];
    }
}
