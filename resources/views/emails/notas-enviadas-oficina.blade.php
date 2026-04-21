<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Notas enviadas a Oficina</title>
</head>
<body>
    <p>Se enviaron {{ $notes->count() }} nota(s) a Oficina.</p>

    @if($comercialName)
        <p>Comercial: {{ $comercialName }}</p>
    @endif

    <p>Se adjunta el PDF de las notas enviadas a Oficina que se encuentran no impresas.</p>
</body>
</html>
