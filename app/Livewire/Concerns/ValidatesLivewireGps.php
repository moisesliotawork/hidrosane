<?php

namespace App\Livewire\Concerns;

use App\Support\ActionGps;
use Filament\Notifications\Notification;

trait ValidatesLivewireGps
{
    /**
     * @return array{lat: string, lng: string}|null
     */
    protected function validatedGpsOrNotify(mixed $lat, mixed $lng): ?array
    {
        $coords = ActionGps::validateOperatingCoords($lat, $lng);

        if ($coords !== null) {
            return $coords;
        }

        Notification::make()
            ->title('Ubicación no válida')
            ->body('No se guardó la ubicación. Activa el GPS del móvil, permite el acceso a la ubicación y vuelve a intentarlo en España.')
            ->danger()
            ->send();

        return null;
    }
}
