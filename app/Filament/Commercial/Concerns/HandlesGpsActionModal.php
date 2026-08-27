<?php

namespace App\Filament\Commercial\Concerns;

use App\Support\ActionGps;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;

trait HandlesGpsActionModal
{
    #[On('gpsCapturadoParaAccionNota')]
    public function setGpsParaAccion(string $lat, string $lng): bool
    {
        if (! ActionGps::shouldRegisterGps()) {
            return true;
        }

        $validated = ActionGps::validateOperatingCoords($lat, $lng);

        if ($validated === null) {
            Notification::make()
                ->title('Ubicación GPS no válida')
                ->body('La ubicación no está en zona de operación. Permite el GPS del navegador e inténtalo de nuevo.')
                ->danger()
                ->persistent()
                ->send();

            return false;
        }

        $patch = [
            'gps_lat' => $validated['lat'],
            'gps_lng' => $validated['lng'],
        ];

        if (is_array($this->mountedActionsData ?? null) && $this->mountedActionsData !== []) {
            $key = array_key_last($this->mountedActionsData);

            if ($key !== null && is_array($this->mountedActionsData[$key] ?? null)) {
                $this->mountedActionsData[$key] = array_merge($this->mountedActionsData[$key], $patch);
            }
        }

        if (property_exists($this, 'mountedTableBulkActionData') && is_array($this->mountedTableBulkActionData)) {
            $this->mountedTableBulkActionData = array_merge($this->mountedTableBulkActionData, $patch);
        }

        return true;
    }
}
