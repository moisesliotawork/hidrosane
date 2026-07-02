@once
    <script>
        if (!window.OhanaGpsCapture) {
            window.OhanaGpsCapture = {
                options: { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },

                capture(onSuccess, onError) {
                    if (!navigator.geolocation) {
                        onError('Este dispositivo no soporta geolocalización. Usa un móvil con GPS activado.');
                        return;
                    }

                    navigator.geolocation.getCurrentPosition(
                        (pos) => onSuccess(pos.coords.latitude, pos.coords.longitude),
                        (err) => onError(
                            'No se pudo obtener tu ubicación real. Activa el GPS y permite el acceso en el navegador: '
                            + (err.message || 'permiso denegado')
                        ),
                        this.options
                    );
                },

                dispatch(event, payload) {
                    this.capture(
                        (lat, lng) => Livewire.dispatch(event, { ...payload, lat, lng }),
                        (message) => alert(message)
                    );
                },
            };
        }
    </script>
@endonce
