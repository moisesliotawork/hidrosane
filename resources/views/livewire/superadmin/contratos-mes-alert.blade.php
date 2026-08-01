<div>
    @if ($visible)
        <div
            class="contratos-mes-global-alert"
            style="position: sticky; top: 0; z-index: 40; margin: 0.75rem 1rem 0;"
            wire:key="contratos-mes-alert-banner"
        >
            <style>
                @keyframes contratos-mes-global-blink {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.55; }
                }
                .contratos-mes-global-alert__inner {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    justify-content: space-between;
                    gap: 0.75rem;
                    padding: 0.7rem 1rem;
                    border-radius: 0.65rem;
                    border: 2px solid #dc2626;
                    background: #fef2f2;
                    color: #991b1b;
                    box-shadow: 0 1px 3px rgb(0 0 0 / 0.08);
                    animation: contratos-mes-global-blink 1.6s ease-in-out infinite;
                }
                .contratos-mes-global-alert__title {
                    font-weight: 800;
                    font-size: 0.9rem;
                    letter-spacing: 0.01em;
                }
                .contratos-mes-global-alert__meta {
                    font-size: 0.8rem;
                    font-weight: 600;
                    opacity: 0.95;
                }
                .contratos-mes-global-alert__actions {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    gap: 0.5rem;
                }
                .contratos-mes-global-alert__link {
                    flex: 0 0 auto;
                    display: inline-flex;
                    align-items: center;
                    padding: 0.35rem 0.75rem;
                    border-radius: 0.4rem;
                    background: #dc2626;
                    color: #fff;
                    font-size: 0.75rem;
                    font-weight: 800;
                    text-decoration: none;
                    white-space: nowrap;
                    border: 0;
                    cursor: pointer;
                }
                .contratos-mes-global-alert__link:hover {
                    background: #b91c1c;
                }
                .contratos-mes-global-alert__dismiss {
                    flex: 0 0 auto;
                    display: inline-flex;
                    align-items: center;
                    padding: 0.35rem 0.75rem;
                    border-radius: 0.4rem;
                    background: #fff;
                    color: #991b1b;
                    font-size: 0.75rem;
                    font-weight: 800;
                    text-decoration: none;
                    white-space: nowrap;
                    border: 1px solid #dc2626;
                    cursor: pointer;
                }
                .contratos-mes-global-alert__dismiss:hover {
                    background: #fee2e2;
                }
                html.dark .contratos-mes-global-alert__inner {
                    background: #450a0a;
                    border-color: #f87171;
                    color: #fecaca;
                }
                html.dark .contratos-mes-global-alert__dismiss {
                    background: #7f1d1d;
                    color: #fecaca;
                    border-color: #f87171;
                }
            </style>

            <div class="contratos-mes-global-alert__inner">
                <div>
                    <div class="contratos-mes-global-alert__title">
                        Contratos/MES: hay menos contratos en {{ $count === 1 ? '1 mes' : "{$count} meses" }}
                    </div>
                    <div class="contratos-mes-global-alert__meta">{{ $monthsList }}</div>
                </div>
                <div class="contratos-mes-global-alert__actions">
                    <a href="{{ $url }}" class="contratos-mes-global-alert__link">
                        Ver Contratos/MES
                    </a>
                    <button
                        type="button"
                        class="contratos-mes-global-alert__dismiss"
                        wire:click="dismiss"
                        wire:loading.attr="disabled"
                    >
                        DESCARTAR ESTE ALERTA
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
