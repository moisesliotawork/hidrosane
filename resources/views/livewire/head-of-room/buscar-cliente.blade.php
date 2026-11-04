<div>
    {{ $this->form }}

    @if (count($customerChoices) > 0)
        <div class="mt-6 space-y-3">
            @if ($searchedDigits)
                <div>
                    <button
                        type="button"
                        wire:click="createNoteForNewCustomerWithSamePhone"
                        style="display:inline-flex;align-items:center;justify-content:center;border-radius:0.5rem;padding:0.5rem 0.9rem;font-size:0.875rem;font-weight:700;background:#16a34a;color:#ffffff;border:1px solid #15803d;cursor:pointer;"
                    >
                        Crear Nota / Cliente Nuevo
                    </button>
                </div>
            @endif

            <ul class="space-y-2">
                @foreach ($customerChoices as $choice)
                    <li
                        class="rounded-lg border border-gray-200 dark:border-gray-700"
                        style="position:relative;background:#ffffff;transition:background-color .15s ease;padding:0.75rem 3.5rem 0.85rem 0.75rem;"
                        onmouseover="this.style.backgroundColor='#e5e7eb'"
                        onmouseout="this.style.backgroundColor='#ffffff'"
                    >
                        <span
                            style="position:absolute;top:0.5rem;right:0.5rem;display:inline-flex;align-items:center;border-radius:9999px;padding:0.15rem 0.55rem;font-size:11px;font-weight:800;letter-spacing:0.03em;background:#f9a8d4;color:#831843;border:1px solid #db2777;"
                        >ID {{ $choice['id'] }}</span>

                        <div>
                            <div style="display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);align-items:center;column-gap:0.75rem;">
                                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:0.5rem;min-width:0;">
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $choice['label'] }}</span>
                                    @if (! empty($choice['match_phone']))
                                        <span
                                            style="display:inline-flex;align-items:center;border-radius:0.375rem;padding:0.15rem 0.5rem;font-size:10px;font-weight:800;letter-spacing:0.04em;text-transform:uppercase;background:#f9a8d4;color:#831843;border:1px solid #db2777;"
                                        >teléfono</span>
                                    @endif
                                    @if (! empty($choice['match_address']))
                                        <span
                                            style="display:inline-flex;align-items:center;border-radius:0.375rem;padding:0.15rem 0.5rem;font-size:10px;font-weight:800;letter-spacing:0.04em;text-transform:uppercase;background:#bfdbfe;color:#1e3a8a;border:1px solid #2563eb;"
                                        >dirección</span>
                                    @endif
                                </div>
                                <div style="justify-self:center;">
                                    <x-filament::button
                                        size="sm"
                                        color="warning"
                                        wire:click="chooseCustomer({{ $choice['id'] }})"
                                    >
                                        Crear Nota para este cliente
                                    </x-filament::button>
                                </div>
                                <div aria-hidden="true"></div>
                            </div>
                            @if (! empty($choice['phones']))
                                <div style="margin-top:0.1rem;font-size:1rem;font-weight:600;color:#111827;letter-spacing:0.01em;">
                                    {{ $choice['phones'] }}
                                </div>
                            @endif
                            @if (! empty($choice['address']))
                                <div style="margin-top:0.15rem;font-size:0.975rem;font-weight:500;color:#374151;">
                                    {{ $choice['address'] }}
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-filament-actions::modals />
</div>
