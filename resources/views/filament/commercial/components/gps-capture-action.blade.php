@php
    $registerGps = \App\Support\ActionGps::shouldRegisterGps();
    $livewire = $getLivewire();
    $noteHasGps = false;

    if ($registerGps && is_object($livewire) && isset($livewire->record)) {
        $filled = \App\Support\Filament\GpsActionForm::fillFromNote($livewire->record);
        $noteHasGps = $filled !== [];
    }
@endphp

<div
    x-data="{
        gpsReady: @js(! $registerGps || $noteHasGps),
        gpsStatus: @js(
            ! $registerGps
                ? ''
                : ($noteHasGps ? 'Ubicación ya registrada en la nota.' : 'Obteniendo ubicación…')
        ),
        syncSubmit() {
            if (! @js($registerGps)) {
                return;
            }

            const modal = $el.closest('.fi-modal') ?? $el.closest('[role=dialog]');
            if (! modal) {
                return;
            }

            modal.querySelectorAll('.fi-modal-footer-actions button').forEach((btn) => {
                const label = (btn.textContent || '').trim().toLowerCase();
                const isCancel = btn.classList.contains('fi-btn-color-gray')
                    || label.includes('cancel')
                    || label.includes('cancelar');

                if (isCancel) {
                    return;
                }

                btn.disabled = ! this.gpsReady;
                btn.classList.toggle('opacity-50', ! this.gpsReady);
                btn.classList.toggle('pointer-events-none', ! this.gpsReady);
            });
        },
    }"
    x-init="
        $watch('gpsReady', () => syncSubmit());

        if (gpsReady) {
            syncSubmit();
            return;
        }

        syncSubmit();

        if (! navigator.geolocation) {
            gpsStatus = 'Este dispositivo no soporta geolocalización.';
            return;
        }

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                const lat = String(pos.coords.latitude);
                const lng = String(pos.coords.longitude);

                const save = (typeof $wire.setGpsParaAccion === 'function')
                    ? $wire.setGpsParaAccion(lat, lng)
                    : $wire.dispatch('gpsCapturadoParaAccionNota', { lat, lng });

                Promise.resolve(save).then((ok) => {
                    if (ok === false) {
                        gpsStatus = 'La ubicación no es válida. Permite el GPS e inténtalo de nuevo.';
                        gpsReady = false;
                        syncSubmit();
                        return;
                    }

                    gpsStatus = 'Ubicación capturada.';
                    gpsReady = true;
                    syncSubmit();
                }).catch(() => {
                    gpsStatus = 'No se pudo registrar la ubicación. Recarga e inténtalo de nuevo.';
                    syncSubmit();
                });
            },
            function (err) {
                gpsStatus = 'Sin GPS: permite la ubicación en el navegador (' + (err.message || 'denegado') + ').';
                syncSubmit();
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    "
    class="text-xs text-gray-600 dark:text-gray-400 mt-1"
    x-show="gpsStatus !== ''"
    x-text="gpsStatus"
></div>
