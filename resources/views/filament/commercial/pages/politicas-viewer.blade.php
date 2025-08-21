<x-filament-panels::page>
    @php $url = $this->getPdfUrl(); @endphp

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Documento oficial</h2>

            @if ($url)
                <x-filament::button tag="a" href="{{ $url }}" target="_blank" icon="heroicon-o-arrow-down-tray">
                    Abrir / Descargar
                </x-filament::button>
            @endif
        </div>

        @if ($url)
            {{-- Visor embebido --}}
            <div class="w-full" style="height: 85vh;">
                <iframe src="{{ $url }}#toolbar=1&navpanes=0&scrollbar=1" class="w-full h-full rounded-lg border"
                    title="Políticas y Comisiones"></iframe>
            </div>
        @else
            <x-filament::section>
                <x-slot name="heading">Sin documento disponible</x-slot>
                <x-slot name="description">
                    Aún no se ha publicado el PDF de políticas y comisiones. Por favor, consulta con Gerencia.
                </x-slot>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>