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
        actionHasGps() {
            const rows = $wire.mountedActionsData || [];
            return Object.values(rows).some(function (row) {
                return row && row.gps_lat && row.gps_lng;
            });
        },
        markReady(status) {
            this.gpsStatus = status;
            this.gpsReady = true;
            this.syncSubmit();
        },
        syncSubmit() {
            if (! @js($registerGps)) {
                return;
            }

            const modal = $el.closest('.fi-modal') ?? $el.closest('[role=dialog]');
            if (! modal) {
                return;
            }

            const ready = this.gpsReady || this.actionHasGps();
            if (ready && ! this.gpsReady) {
                this.gpsReady = true;
                this.gpsStatus = 'Ubicación capturada.';
            }

            modal.querySelectorAll('.fi-modal-footer-actions button').forEach((btn) => {
                const label = (btn.textContent || '').trim().toLowerCase();
                const isCancel = btn.classList.contains('fi-btn-color-gray')
                    || label.includes('cancel')
                    || label.includes('cancelar');

                if (isCancel) {
                    return;
                }

                btn.disabled = ! ready;
                btn.classList.toggle('opacity-50', ! ready);
                btn.classList.toggle('pointer-events-none', ! ready);
            });
        },
    }"
    x-init="
        $watch('gpsReady', () => syncSubmit());

        if (typeof Livewire !== 'undefined') {
            Livewire.hook('commit', ({ succeed }) => {
                succeed(() => {
                    $nextTick(() => syncSubmit());
                });
            });
        }

        if (actionHasGps()) {
            markReady('Ubicación capturada.');
            return;
        }

        if (gpsReady) {
            $nextTick(() => syncSubmit());
            return;
        }

        $nextTick(() => syncSubmit());

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
