<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Contrato {{ $nro }}</title>
    <style>
        @page {
            margin: 8mm;
        }
        body {
            margin: 0;
            font-family: sans-serif;
        }
        .doc-page {
            page-break-after: always;
            text-align: center;
        }
        .doc-page:last-child {
            page-break-after: auto;
        }
        .doc-page img {
            max-width: 100%;
            max-height: 100%;
        }
    </style>
</head>
<body>
    @foreach ($images as $image)
        <div class="doc-page">
            <img src="{{ $image }}">
        </div>
    @endforeach
</body>
</html>
