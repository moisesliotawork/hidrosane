@php
    $ohanaRegisterGps = \App\Support\ActionGps::shouldRegisterGps();
@endphp

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

        @if (! $ohanaRegisterGps)
            ejecutar(null, null);
            return;
        @endif

        if (!window.OhanaGpsCapture) {
            alert('No se pudo cargar el módulo de geolocalización. Recarga la página.');
            return;
        }

        OhanaGpsCapture.capture(
            (lat, lng) => ejecutar(lat, lng),
            (message) => alert(message)
        );
    }
</script>
