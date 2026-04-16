@php
    $isAdminLogin = request()->is('admin/login');
@endphp

<div class="flex items-center gap-3 {{ $isAdminLogin ? 'justify-center' : '' }}">
    {{-- Logo más grande --}}
    @if ($isAdminLogin)
        <img
            src="{{ asset('images/logo.png') }}"
            alt="Hidrosane"
            class="shrink-0 rounded"
            style="width: 230px; max-width: 70%; height: auto; transform: translateY(-12px);"
        >
    @else
        <img
            src="{{ asset('images/logo.png') }}"
            alt="Hidrosane"
            class="h-11 w-11 shrink-0 rounded"
        >
    @endif

    {{-- Texto más grande y adaptable a light/dark --}}
    @unless ($isAdminLogin)
        <span class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
            Hidrosane
        </span>
    @endunless
</div>
