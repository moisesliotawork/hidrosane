<div
    x-data
    x-init="
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(pos) {
                    $wire.dispatch('gpsCapturadoParaAccionNota', {
                        lat: String(pos.coords.latitude),
                        lng: String(pos.coords.longitude)
                    });
                },
                function() {}
            );
        }
    "
    style="display:none"
></div>
