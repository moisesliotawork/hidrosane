<?php

namespace App\Filament\Commercial\Concerns;

use App\Support\ActionGps;
use Livewire\Attributes\On;

trait HandlesGpsVentaWizard
{
    #[On('gpsCapturadoParaVentaWizard')]
    public function setGpsParaVentaWizard(string $lat, string $lng): void
    {
        if (! ActionGps::shouldRegisterGps()) {
            return;
        }

        // Patch only GPS on the server. Avoid $wire.set('data.*') from Alpine,
        // which can race with FileUpload and clear document paths in form state.
        $state = $this->form->getRawState();
        $state['gps_lat'] = $lat;
        $state['gps_lng'] = $lng;
        $this->form->fill($state);
    }
}
