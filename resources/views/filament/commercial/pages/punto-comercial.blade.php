<x-filament-panels::page>
<style>
    .pc-actions {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.75rem;
        padding-top: 0.5rem;
    }

    @media (min-width: 640px) {
        .pc-actions {
            grid-template-columns: 1fr 1fr;
        }
    }

    .pc-btn {
        display: inline-flex;
        width: 100%;
        align-items: center;
        justify-content: center;
        border-radius: 0.75rem;
        padding: 1rem;
        font-size: 0.95rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        cursor: pointer;
        transition: background-color 0.15s ease, opacity 0.15s ease;
    }

    .pc-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .pc-btn-enviar {
        background: #16a34a;
        color: #ffffff;
        border: 1px solid #15803d;
        box-shadow: 0 2px 8px rgba(22, 163, 74, 0.28);
    }

    .pc-btn-enviar:hover:not(:disabled) {
        background: #15803d;
    }

    .pc-btn-cancelar {
        background: #ffffff;
        color: #374151;
        border: 1px solid #d1d5db;
    }

    .pc-btn-cancelar:hover:not(:disabled) {
        background: #f3f4f6;
    }

    html.dark .pc-btn-enviar {
        background: #22c55e;
        color: #052e16;
        border-color: #16a34a;
    }

    html.dark .pc-btn-enviar:hover:not(:disabled) {
        background: #16a34a;
        color: #ffffff;
    }

    html.dark .pc-btn-cancelar {
        background: #1f2937;
        color: #e5e7eb;
        border-color: #4b5563;
    }

    html.dark .pc-btn-cancelar:hover:not(:disabled) {
        background: #374151;
    }
</style>

    <div class="mx-auto w-full max-w-xl space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Fecha de hoy</p>
            <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white">{{ $this->todayLabel }}</p>
        </div>

        <form class="space-y-4">
            {{ $this->form }}

            <div class="pc-actions">
                <button
                    type="button"
                    wire:loading.attr="disabled"
                    wire:target="enviar"
                    x-data="{
                        sending: @entangle('sending'),
                        enviarConGps() {
                            if (this.sending) return;
                            this.sending = true;
                            if (!navigator.geolocation) {
                                this.sending = false;
                                $wire.enviar(null, null);
                                return;
                            }
                            navigator.geolocation.getCurrentPosition(
                                (pos) => {
                                    $wire.enviar(
                                        String(pos.coords.latitude),
                                        String(pos.coords.longitude)
                                    );
                                },
                                () => {
                                    this.sending = false;
                                    $wire.enviar(null, null);
                                },
                                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
                            );
                        }
                    }"
                    x-on:click="enviarConGps()"
                    class="pc-btn pc-btn-enviar"
                >
                    <span wire:loading.remove wire:target="enviar">ENVIAR Punto/Com</span>
                    <span wire:loading wire:target="enviar">ENVIANDO…</span>
                </button>

                <button
                    type="button"
                    wire:click="cancelar"
                    wire:loading.attr="disabled"
                    class="pc-btn pc-btn-cancelar"
                >
                    CANCELAR
                </button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
