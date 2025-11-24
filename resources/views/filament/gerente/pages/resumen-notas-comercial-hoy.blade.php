<x-filament-panels::page>
    <div class="space-y-4">
        <div class="text-lg font-semibold">
            Comercial:
            {{ $this->comercial->empleado_id }}
            -
            {{ trim($this->comercial->name . ' ' . ($this->comercial->last_name ?? '')) }}
        </div>

        {{ $this->table }}

        <x-filament::button tag="a"
            href="{{ \App\Filament\Gerente\Pages\ComercialesResumenHoy::getUrl(panel: 'gerente') }}">
            Volver
        </x-filament::button>
    </div>
</x-filament-panels::page>