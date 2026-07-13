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

        // Patch only GPS fields. Never call $this->form->fill() here: it races with
        // FileUpload on mobile and clears document paths from form state.
        if (! is_array($this->data ?? null)) {
            $this->data = [];
        }

        $this->data['gps_lat'] = $lat;
        $this->data['gps_lng'] = $lng;
    }
}
