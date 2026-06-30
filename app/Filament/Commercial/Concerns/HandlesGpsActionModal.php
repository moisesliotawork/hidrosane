<?php

namespace App\Filament\Commercial\Concerns;

use Livewire\Attributes\On;

trait HandlesGpsActionModal
{
    #[On('gpsCapturadoParaAccionNota')]
    public function setGpsParaAccion(string $lat, string $lng): void
    {
        $patch = ['gps_lat' => $lat, 'gps_lng' => $lng];

        if (is_array($this->mountedActionsData ?? null) && $this->mountedActionsData !== []) {
            $key = array_key_last($this->mountedActionsData);

            if ($key !== null && is_array($this->mountedActionsData[$key] ?? null)) {
                $this->mountedActionsData[$key] = array_merge($this->mountedActionsData[$key], $patch);
            }
        }

        if (property_exists($this, 'mountedTableBulkActionData') && is_array($this->mountedTableBulkActionData)) {
            $this->mountedTableBulkActionData = array_merge($this->mountedTableBulkActionData, $patch);
        }
    }
}
