@php
    $registerGps = \App\Support\ActionGps::shouldRegisterGps();
@endphp

<div
    x-data="{
        gpsReady: @js(! $registerGps),
        gpsStatus: @js($registerGps ? 'Obteniendo ubicación para la venta…' : ''),
        syncCreateButton() {
            if (! @js($registerGps)) {
                return;
            }

            const wizard = $el.closest('.fi-fo-wizard');
            if (! wizard) {
                return;
            }

            wizard.querySelectorAll('button').forEach((btn) => {
                if (btn.getAttribute('wire:click') !== 'create') {
                    return;
                }

                btn.disabled = ! this.gpsReady;
                btn.classList.toggle('opacity-50', ! this.gpsReady);
                btn.classList.toggle('pointer-events-none', ! this.gpsReady);
            });
        },
    }"
    x-init="
        $watch('gpsReady', () => syncCreateButton());

        if (gpsReady) {
            return;
        }

        syncCreateButton();

        if (! navigator.geolocation) {
            gpsStatus = 'Este dispositivo no soporta geolocalización.';
            return;
        }

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                const lat = String(pos.coords.latitude);
                const lng = String(pos.coords.longitude);
                gpsStatus = 'Ubicación capturada para la venta.';
                gpsReady = true;
                syncCreateButton();
                $wire.set('data.gps_lat', lat);
                $wire.set('data.gps_lng', lng);
            },
            function (err) {
                gpsStatus = 'Sin GPS: permite la ubicación en el navegador (' + (err.message || 'denegado') + ').';
                syncCreateButton();
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    "
    class="text-xs text-gray-600 dark:text-gray-400 mt-1"
    x-show="gpsStatus !== ''"
    x-text="gpsStatus"
></div>
