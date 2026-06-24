<script>
    function enviarAOficinaConGps(livewireMethod) {
        if (!confirm('Estás a punto de enviar notas a oficina. ¿ESTÁS SEGURO DE QUE QUIERES ENVIAR A OFICINA?')) {
            return;
        }

        function ejecutar(lat, lng) {
            @this.call(
                livewireMethod,
                lat != null ? String(lat) : null,
                lng != null ? String(lng) : null
            );
        }

        if (location.protocol !== 'https:') {
            ejecutar(10.4806, -66.9036);
            return;
        }

        if (!navigator.geolocation) {
            ejecutar(null, null);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (pos) => ejecutar(pos.coords.latitude, pos.coords.longitude),
            () => ejecutar(null, null),
            { enableHighAccuracy: true, timeout: 15000 }
        );
    }
</script>
