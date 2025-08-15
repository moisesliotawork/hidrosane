<x-filament-panels::page>
    {{-- Barra de búsqueda (como en tu captura) --}}
    <div class="rounded-lg border p-4 bg-white/5 dark:bg-gray-900/30">
        {{ $this->form }}
        <script>
            window.addEventListener('open-url', e => window.open(e.detail.url, '_blank'));
        </script>
    </div>

    {{-- Resultado --}}
    @if ($resultado)
        <div class="mt-6 grid gap-3">
            <x-filament::section>
                <x-slot name="heading">
                    Resultado para CP: <strong>{{ $resultado->code }}</strong>
                </x-slot>

                <dl class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <dt class="text-sm text-gray-500">Ciudad</dt>
                        <dd class="text-base font-medium">
                            {{ $resultado->city?->title ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Provincia (State)</dt>
                        <dd class="text-base font-medium">
                            {{ $resultado->city?->state?->title ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">País</dt>
                        <dd class="text-base font-medium">
                            {{ $resultado->city?->state?->country?->title ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </x-filament::section>
        </div>
    @elseif(isset($data['cp']) && $data['cp'] !== null)
        <div class="mt-6">
            <x-filament::badge color="danger">No se encontró el CP en tu base de datos.</x-filament::badge>
        </div>
    @endif
</x-filament-panels::page>