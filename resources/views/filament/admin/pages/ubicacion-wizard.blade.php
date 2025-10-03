<x-filament-panels::page>
    {{ $this->form }}

    <x-filament-actions::actions :actions="$this->getFormActions()" alignment="right" class="mt-6" />
</x-filament-panels::page>