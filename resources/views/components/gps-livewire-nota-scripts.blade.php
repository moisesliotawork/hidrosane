@include('components.gps-livewire-capture')

<script>
    function toggleDeCaminoConGps(notaId) {
        OhanaGpsCapture.dispatch('toggleDeCamino', { noteId: notaId });
    }

    function getUbicacion(notaId) {
        OhanaGpsCapture.dispatch('guardarUbicacion', { notaId });
    }

    function getUbicacionDentro(notaId) {
        OhanaGpsCapture.dispatch('guardarUbicacionDentro', { notaId });
    }

    function llevarme(notaId, lat, lng) {
        if (!lat || !lng) {
            Livewire.dispatch('avisarSinDentro', { notaId });
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
    }
</script>
