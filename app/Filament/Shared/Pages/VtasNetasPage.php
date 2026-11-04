<?php

namespace App\Filament\Shared\Pages;

use App\Models\MesComercial;
use App\Support\VtasNetasReport;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class VtasNetasPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Vtas Netas';

    protected static ?string $title = 'Ventas Netas';

    protected static ?string $slug = 'vtas-netas';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.vtas-netas';

    public int $anio;

    public int $mes;

    /** @var list<int> */
    public array $anios = [2025, 2026, 2027];

    public function mount(): void
    {
        $anio = (int) request()->integer('anio', now()->year);
        $mes = (int) request()->integer('mes', now()->month);

        $this->anio = in_array($anio, $this->anios, true) ? $anio : 2026;
        $this->mes = max(1, min(12, $mes));
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole([
            'admin',
            'app_support',
            'gerente_general',
            'gerente',
        ]);
    }

    public function setAnio(int $anio): void
    {
        if (in_array($anio, $this->anios, true)) {
            $this->anio = $anio;
        }
    }

    public function setMes(int $mes): void
    {
        $this->mes = max(1, min(12, $mes));
    }

    /**
     * @return list<array{n:int, label:string}>
     */
    public function meses(): array
    {
        return [
            ['n' => 1, 'label' => 'Ener'],
            ['n' => 2, 'label' => 'Febr'],
            ['n' => 3, 'label' => 'Marz'],
            ['n' => 4, 'label' => 'Abri'],
            ['n' => 5, 'label' => 'Mayo'],
            ['n' => 6, 'label' => 'Juni'],
            ['n' => 7, 'label' => 'Juli'],
            ['n' => 8, 'label' => 'Agos'],
            ['n' => 9, 'label' => 'Sept'],
            ['n' => 10, 'label' => 'Octu'],
            ['n' => 11, 'label' => 'Novi'],
            ['n' => 12, 'label' => 'Dici'],
        ];
    }

    public function mesComercialActual(): ?MesComercial
    {
        return MesComercial::forYearMonth($this->anio, $this->mes);
    }

    /**
     * @return list<int>
     */
    public function mesesDefinidos(): array
    {
        return MesComercial::definedMonths($this->anio);
    }

    public function badgeMesComercial(): string
    {
        $row = $this->mesComercialActual();

        if (! $row) {
            return 'Fecha de mes comercial sin definir';
        }

        return $row->fecha_inicio->format('d/m/Y').' — '.$row->fecha_fin->format('d/m/Y');
    }

    public function badgeDefinido(): bool
    {
        return $this->mesComercialActual() !== null;
    }

    /**
     * Parpadeo gris/rojo: mes sin fechas y estamos a ≤40 días del inicio de ese mes.
     */
    public function badgeUrgente(): bool
    {
        if ($this->badgeDefinido()) {
            return false;
        }

        $inicioMes = Carbon::create($this->anio, $this->mes, 1)->startOfDay();
        $avisoDesde = $inicioMes->copy()->subDays(40);
        $avisoHasta = $inicioMes->copy()->endOfMonth();

        return now()->gte($avisoDesde) && now()->lte($avisoHasta);
    }

    /**
     * Tachado: este mes es el anterior o el posterior al seleccionado y no tiene fechas.
     */
    public function mesVecinoSinDefinir(int $mes): bool
    {
        [$prevAnio, $prevMes] = MesComercial::previousKey($this->anio, $this->mes);
        [$nextAnio, $nextMes] = MesComercial::nextKey($this->anio, $this->mes);

        $esAnterior = $prevAnio === $this->anio && $prevMes === $mes;
        $esPosterior = $nextAnio === $this->anio && $nextMes === $mes;

        if (! $esAnterior && ! $esPosterior) {
            return false;
        }

        return MesComercial::forYearMonth($this->anio, $mes) === null;
    }

    public function editarMesComercialAction(): Action
    {
        return Action::make('editarMesComercial')
            ->label('Mes comercial')
            ->modalHeading(fn (): string => 'Mes comercial — '.$this->nombreMes($this->mes).' '.$this->anio)
            ->modalDescription('Define el rango de fechas del mes comercial. No puede solaparse con el mes anterior ni el siguiente.')
            ->modalSubmitActionLabel('Guardar')
            ->form([
                DatePicker::make('fecha_inicio')
                    ->label('Fecha de inicio')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->closeOnDateSelection(),
                DatePicker::make('fecha_fin')
                    ->label('Fecha de final')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->closeOnDateSelection(),
            ])
            ->fillForm(function (): array {
                $row = $this->mesComercialActual();

                return [
                    'fecha_inicio' => $row?->fecha_inicio?->toDateString(),
                    'fecha_fin' => $row?->fecha_fin?->toDateString(),
                ];
            })
            ->action(function (array $data): void {
                $inicio = Carbon::parse($data['fecha_inicio'])->startOfDay();
                $fin = Carbon::parse($data['fecha_fin'])->startOfDay();

                try {
                    MesComercial::assertNoOverlap($this->anio, $this->mes, $inicio, $fin);
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('Fechas no válidas')
                        ->body(collect($e->errors())->flatten()->first() ?: 'Revisa el rango.')
                        ->danger()
                        ->send();

                    throw $e;
                }

                MesComercial::query()->updateOrCreate(
                    ['anio' => $this->anio, 'mes' => $this->mes],
                    [
                        'fecha_inicio' => $inicio->toDateString(),
                        'fecha_fin' => $fin->toDateString(),
                    ],
                );

                Notification::make()
                    ->title('Mes comercial actualizado')
                    ->success()
                    ->send();
            });
    }

    protected function nombreMes(int $mes): string
    {
        return match ($mes) {
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
            default => (string) $mes,
        };
    }

    /**
     * @return Collection<int, array{id:int, label:string, netas:int, brut:int, neut:int, entr:int, desc:int}>
     */
    public function filas(): Collection
    {
        return app(VtasNetasReport::class)->rows($this->anio, $this->mes);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'filas' => $this->filas(),
            'mesesDefinidos' => $this->mesesDefinidos(),
            'badgeTexto' => $this->badgeMesComercial(),
            'badgeDefinido' => $this->badgeDefinido(),
            'badgeUrgente' => $this->badgeUrgente(),
        ];
    }
}
