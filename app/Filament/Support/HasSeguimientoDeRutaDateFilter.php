<?php

namespace App\Filament\Support;

use Illuminate\Support\Carbon;

trait HasSeguimientoDeRutaDateFilter
{
    public ?string $fechaEspecifica = null;

    protected $queryString = [
        'fechaEspecifica' => ['except' => ''],
    ];

    public function updatingFechaEspecifica(?string $value): void
    {
        if (filled($value)) {
            $this->selectedDay = 'fecha';
        }
    }

    public function clearFechaEspecifica(): void
    {
        $this->fechaEspecifica = null;
        $this->selectedDay = 'hoy';
    }

    public function setSelectedDay(string $day): void
    {
        if (! in_array($day, ['hoy', 'ayer'], true)) {
            return;
        }

        $this->fechaEspecifica = null;
        $this->selectedDay = $day;
    }

    public function getSelectedReportDayProperty(): array
    {
        if (filled($this->fechaEspecifica)) {
            try {
                $parsed = Carbon::parse($this->fechaEspecifica)->startOfDay();

                return [
                    'key' => 'fecha',
                    'label' => $parsed->format('d/m/Y'),
                    'date' => $parsed,
                ];
            } catch (\Throwable) {
                $this->fechaEspecifica = null;
            }
        }

        return collect($this->getReportDaysList())
            ->firstWhere('key', $this->selectedDay)
            ?? $this->getReportDaysList()[0];
    }

    /** @return array<int, array{key: string, label: string, date: Carbon}> */
    protected function getReportDaysList(): array
    {
        return [
            [
                'key' => 'hoy',
                'label' => 'HOY',
                'date' => today(),
            ],
            [
                'key' => 'ayer',
                'label' => 'AYER',
                'date' => today()->subDay(),
            ],
        ];
    }

    public function getReportDaysProperty(): array
    {
        return $this->getReportDaysList();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function getNotesQueryDateRange(): array
    {
        if (filled($this->fechaEspecifica)) {
            try {
                $date = Carbon::parse($this->fechaEspecifica)->startOfDay();

                return [$date, $date];
            } catch (\Throwable) {
                $this->fechaEspecifica = null;
            }
        }

        $today = today();
        $yesterday = today()->subDay();

        return [$yesterday, $today];
    }
}
