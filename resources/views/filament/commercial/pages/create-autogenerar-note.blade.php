<x-filament-panels::page
    @class([
        'fi-resource-create-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'notas-page',
    ])
>
    <x-commercial.note-page-surface />

    <style>
        .fi-resource-autogenerar-notes.fi-page > section {
            gap: 0.75rem;
            padding-top: 1rem;
            padding-bottom: 1rem;
        }

        .fi-resource-autogenerar-notes .fi-fo-component-ctn {
            gap: 0.45rem !important;
        }

        .fi-resource-autogenerar-notes .fi-section-content {
            padding: 0.55rem 0.85rem !important;
        }

        .fi-resource-autogenerar-notes .fi-section-header {
            padding: 0.4rem 0.85rem !important;
        }

        .fi-resource-autogenerar-notes .fi-fo-field-wrp > .grid {
            gap: 0.15rem !important;
        }

        .fi-resource-autogenerar-notes .fi-input-wrp {
            min-height: 2.15rem;
        }

        .fi-resource-autogenerar-notes .fi-input,
        .fi-resource-autogenerar-notes .fi-select-input,
        .fi-resource-autogenerar-notes input:not([type="checkbox"]):not([type="radio"]),
        .fi-resource-autogenerar-notes select,
        .fi-resource-autogenerar-notes textarea {
            padding-top: 0.35rem;
            padding-bottom: 0.35rem;
        }

        .fi-resource-autogenerar-notes textarea {
            min-height: 3.25rem;
        }

        .fi-resource-autogenerar-notes .fi-fo-repeater {
            gap: 0.4rem !important;
        }

        .fi-resource-autogenerar-notes .fi-fo-repeater-item-header {
            padding-top: 0.35rem;
            padding-bottom: 0.35rem;
        }

        .fi-resource-autogenerar-notes .fi-fo-field-wrp-label {
            margin-bottom: 0;
        }

        .fi-resource-autogenerar-notes .fi-section-header-heading {
            font-weight: 800 !important;
        }

        .fi-resource-autogenerar-notes .fi-header-heading {
            font-weight: 800 !important;
        }

        .fi-resource-autogenerar-notes .fi-fo-field-wrp-label .fi-fo-field-wrp-label-text {
            font-size: 0.8rem;
            line-height: 1.15;
            font-weight: 700 !important;
        }

        .fi-resource-autogenerar-notes .fi-form-actions {
            margin-top: 0.25rem;
        }

        .fi-resource-autogenerar-notes input[wire\\:model*="phone"],
        .fi-resource-autogenerar-notes input[id*="phone"] {
            font-weight: 800 !important;
            color: #1e3a8a !important;
        }
    </style>

    <x-filament-panels::form
        id="form"
        :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
        wire:submit="create"
    >
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
