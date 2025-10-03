<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <x-filament-actions::actions
            :actions="$this->getFormActions()"
            alignment="right"
            class="mt-6"
        />
    </form>
</x-filament-panels::page>