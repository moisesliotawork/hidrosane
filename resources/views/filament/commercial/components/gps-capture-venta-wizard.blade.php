@php
    $registerGps = \App\Support\ActionGps::shouldRegisterGps();
    $livewire = $getLivewire();
    $noteHasGps = false;

    if ($registerGps && property_exists($livewire, 'noteId')) {
        $note = \App\Models\Note::query()->find($livewire->noteId);
        $noteHasGps = $note !== null
            && \App\Support\ActionGps::validateOperatingCoords($note->lat, $note->lng) !== null;
    }
@endphp

<div
    x-data="{
        gpsReady: @js(! $registerGps || $noteHasGps),
        gpsStatus: @js(
            ! $registerGps
                ? ''
                : ($noteHasGps ? 'Ubicación registrada desde la nota.' : 'Obteniendo ubicación para la venta…')
        ),
        syncCreateButton() {
            if (! @js($registerGps)) {
                return;
            }

            const wizard = $el.closest('.fi-fo-wizard') ?? $el.closest('form');
            if (! wizard) {
                return;
            }

            wizard.querySelectorAll('button').forEach((btn) => {
                const wireClick = btn.getAttribute('wire:click') ?? '';

                if (! wireClick.includes('create')) {
                    return;
                }

                btn.disabled = ! this.gpsReady;
                btn.classList.toggle('opacity-50', ! this.gpsReady);
                btn.classList.toggle('pointer-events-none', ! this.gpsReady);
            });
        },
        markGpsReady(status) {
            gpsStatus = status;
            gpsReady = true;
            this.syncCreateButton();
        },
        captureGpsOnServer(lat, lng) {
            return $wire.setGpsParaVentaWizard(lat, lng);
        },
    }"
    x-init="
        $watch('gpsReady', () => syncCreateButton());

        if (typeof Livewire !== 'undefined') {
            Livewire.hook('commit', ({ succeed }) => {
                succeed(() => {
                    $nextTick(() => syncCreateButton());
                });
            });
        }

        if (gpsReady) {
            syncCreateButton();
            return;
        }

        const existingLat = $wire.get('data.gps_lat');
        const existingLng = $wire.get('data.gps_lng');

        if (existingLat && existingLng) {
            markGpsReady('Ubicación capturada para la venta.');
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

                captureGpsOnServer(lat, lng).then(() => {
                    markGpsReady('Ubicación capturada para la venta.');
                }).catch(() => {
                    gpsStatus = 'No se pudo registrar la ubicación en el servidor. Recarga e inténtalo de nuevo.';
                    syncCreateButton();
                });
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
