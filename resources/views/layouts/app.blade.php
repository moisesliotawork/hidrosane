{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'Hidrosane')</title>

    {{-- Tailwind via CDN solo para estas páginas sueltas --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Fuente similar a Filament --}}
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "SF Pro Text",
                "Segoe UI", sans-serif;
        }
    </style>
</head>

<body class="min-h-screen bg-slate-950 text-slate-100">
    @yield('content')
</body>

</html>
