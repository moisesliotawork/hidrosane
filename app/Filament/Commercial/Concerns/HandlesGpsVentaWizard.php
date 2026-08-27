<?php

namespace App\Filament\Commercial\Concerns;

use App\Support\ActionGps;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;

trait HandlesGpsVentaWizard
{
    #[On('gpsCapturadoParaVentaWizard')]
    public function setGpsParaVentaWizard(string $lat, string $lng): bool
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

        // Patch only GPS fields. Never call $this->form->fill() here: it races with
        // FileUpload on mobile and clears document paths from form state.
        if (! is_array($this->data ?? null)) {
            $this->data = [];
        }

        $this->data['gps_lat'] = $validated['lat'];
        $this->data['gps_lng'] = $validated['lng'];

        return true;
    }
}
