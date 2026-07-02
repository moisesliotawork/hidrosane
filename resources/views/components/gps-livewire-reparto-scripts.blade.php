@include('components.gps-livewire-capture')

<script>
    window.getUbicacion = function (repartoId) {
        OhanaGpsCapture.dispatch('guardarUbicacion', { repartoId });
    };

    window.getUbicacionDentro = function (repartoId) {
        OhanaGpsCapture.dispatch('guardarUbicacionDentro', { repartoId });
    };

    window.llevarme = function (ventaId, lat, lng) {
        if (!lat || !lng) {
            Livewire.dispatch('avisarSinGPS', { ventaId });
            return;
        }

        const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

        if (isMobile) {
            const geoUrl = `geo:${lat},${lng}?q=${lat},${lng}`;
            window.location.href = geoUrl;
            setTimeout(() => {
                const webUrl = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
                window.open(webUrl, '_blank');
            }, 600);

            return;
        }

        const webUrl = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
        window.open(webUrl, '_blank');
    };
</script>
