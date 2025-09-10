<x-filament::page>
    <div class="mb-4 text-lg font-semibold">
        Total de anotaciones: {{ $this->total }}
    </div>

    {{ $this->table }}
</x-filament::page>