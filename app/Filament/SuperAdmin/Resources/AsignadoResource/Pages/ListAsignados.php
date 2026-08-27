<?php

namespace App\Filament\SuperAdmin\Resources\AsignadoResource\Pages;

use App\Filament\SuperAdmin\Resources\AsignadoResource;
use App\Models\Note;
use Carbon\Carbon;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ListAsignados extends ListRecords
{
    protected static string $resource = AsignadoResource::class;

    public bool $tableGroupingCollapsed = false;

    public function isTableGroupingCollapsedByDefault(): bool
    {
        return false;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString(
            view('filament.superAdmin.resources.asignado.weekday-badges', [
                'badges' => $this->weekdayBadges(),
                'activeDate' => data_get($this->tableFilters, 'buscar_fecha.date'),
            ])->render()
        );
    }

    /**
     * @return array<int, array{date: string, label: string, short: string, bg: string, text: string, count: int}>
     */
    public function weekdayBadges(): array
    {
        $today = Note::businessToday();
        $colors = [
            Carbon::MONDAY => ['bg' => '#dbeafe', 'text' => '#1e40af'],
            Carbon::TUESDAY => ['bg' => '#d1fae5', 'text' => '#065f46'],
            Carbon::WEDNESDAY => ['bg' => '#fef3c7', 'text' => '#92400e'],
            Carbon::THURSDAY => ['bg' => '#ede9fe', 'text' => '#5b21b6'],
            Carbon::FRIDAY => ['bg' => '#fce7f3', 'text' => '#9d174d'],
            Carbon::SATURDAY => ['bg' => '#ccfbf1', 'text' => '#0f766e'],
            Carbon::SUNDAY => ['bg' => '#ffedd5', 'text' => '#9a3412'],
        ];

        $names = [
            Carbon::MONDAY => 'Lun',
            Carbon::TUESDAY => 'Mar',
            Carbon::WEDNESDAY => 'Mié',
            Carbon::THURSDAY => 'Jue',
            Carbon::FRIDAY => 'Vie',
            Carbon::SATURDAY => 'Sáb',
            Carbon::SUNDAY => 'Dom',
        ];

        $fullNames = [
            Carbon::MONDAY => 'Lunes',
            Carbon::TUESDAY => 'Martes',
            Carbon::WEDNESDAY => 'Miércoles',
            Carbon::THURSDAY => 'Jueves',
            Carbon::FRIDAY => 'Viernes',
            Carbon::SATURDAY => 'Sábado',
            Carbon::SUNDAY => 'Domingo',
        ];

        $badges = [];

        for ($i = 1; $i <= 7; $i++) {
            $day = $today->copy()->addDays($i);
            $dow = $day->dayOfWeek;
            $palette = $colors[$dow];

            $badges[] = [
                'date' => $day->toDateString(),
                'label' => $names[$dow],
                'full' => $fullNames[$dow],
                'short' => $day->format('d/m'),
                'bg' => $palette['bg'],
                'text' => $palette['text'],
                'count' => AsignadoResource::getEloquentQuery()
                    ->whereEffectiveAssignmentDate($day)
                    ->count(),
            ];
        }

        return $badges;
    }

    public function selectWeekdayDate(string $date): void
    {
        $this->tableFilters['buscar_fecha'] = [
            'date' => $date,
        ];

        $this->resetTable();
    }

    public function clearWeekdayDate(): void
    {
        $this->tableFilters['buscar_fecha'] = [
            'date' => null,
        ];

        $this->resetTable();
    }

    public function getTabs(): array
    {
        $today = Note::businessToday();
        $yesterday = $today->copy()->subDay();
        $tomorrow = $today->copy()->addDay();
        $dayAfter = $today->copy()->addDays(2);

        $countFor = fn (Carbon $date): int => AsignadoResource::getEloquentQuery()
            ->whereEffectiveAssignmentDate($date)
            ->count();

        $tabQuery = function (Builder $query, Carbon $date): Builder {
            if ($this->hasBuscarFechaFilter()) {
                return $query;
            }

            return $query->whereEffectiveAssignmentDate($date);
        };

        return [
            'ayer' => Tab::make('AYER')
                ->icon('heroicon-o-arrow-uturn-left')
                ->badge($countFor($yesterday))
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $tabQuery($query, $yesterday)),

            'hoy' => Tab::make('HOY')
                ->icon('heroicon-o-calendar')
                ->badge($countFor($today))
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $tabQuery($query, $today)),

            'manana' => Tab::make('MAÑANA')
                ->icon('heroicon-o-chevron-double-right')
                ->badge($countFor($tomorrow))
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $tabQuery($query, $tomorrow)),

            'pasado_manana' => Tab::make('PASADO MAÑANA')
                ->icon('heroicon-o-forward')
                ->badge($countFor($dayAfter))
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $tabQuery($query, $dayAfter)),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'manana';
    }

    private function hasBuscarFechaFilter(): bool
    {
        return filled(data_get($this->tableFilters, 'buscar_fecha.date'));
    }
}
