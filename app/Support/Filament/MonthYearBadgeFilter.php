<?php

namespace App\Support\Filament;

use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class MonthYearBadgeFilter
{
    /**
     * @return array<int, array{label: string, bg: string, border: string, text: string}>
     */
    public static function monthBadges(): array
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

    /**
     * @return list<int>
     */
    public static function availableYears(): array
    {
        $current = (int) Carbon::today()->year;
        $minYear = (int) (Venta::query()
            ->whereNotNull('fecha_venta')
            ->min(DB::raw('YEAR(fecha_venta)')) ?: $current);

        return range($current, min($minYear, $current - 5));
    }

    public static function periodLabel(?string $yearMonth, bool $showAll, string $allLabel = 'Todos'): ?string
    {
        if ($showAll || blank($yearMonth)) {
            return $allLabel;
        }

        try {
            $selected = Carbon::createFromFormat('Y-m', $yearMonth)->locale('es');

            return ucfirst($selected->translatedFormat('F Y'));
        } catch (\Throwable) {
            return null;
        }
    }
}
