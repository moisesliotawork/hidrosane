<div id="hor-crear-nota" hidden>
    <div class="hor-crear-nota-backdrop" onclick="window.horCerrarCrearNota()"></div>
    <div class="hor-crear-nota-panel" role="dialog" aria-modal="true" aria-labelledby="hor-crear-nota-title">
        <div data-step="form">
            <h2 id="hor-crear-nota-title">Crear nota</h2>
            <label>
                Nombre
                <input type="text" data-nombre maxlength="255" autocomplete="off">
            </label>
            <label>
                Teléfono
                <input type="tel" data-phone inputmode="numeric" placeholder="999 999 999" autocomplete="off">
            </label>
            <div class="hor-crear-nota-actions">
                <button type="button" class="hor-crear-nota-secondary" onclick="window.horCerrarCrearNota()">Cancelar</button>
                <button type="button" class="hor-crear-nota-primary" onclick="window.horContinuarCrearNota()">Continuar</button>
            </div>
        </div>
        <div data-step="matches" hidden>
            <h2>Selecciona a cual cliente ya existente agregar la nota</h2>
            <p class="hor-crear-nota-legend">En rojo los datos que coinciden</p>
            <div class="hor-crear-nota-matches" data-matches></div>
            <p class="hor-crear-nota-venta" data-venta hidden></p>
            <div class="hor-crear-nota-actions">
                <button type="button" class="hor-crear-nota-secondary" data-venta-cancel hidden onclick="window.horCerrarCrearNota()">Cancelar</button>
                <button type="button" class="hor-crear-nota-primary" data-asignar onclick="window.horAsignarNota()">Agrego la nota al cliente seleccionado</button>
                <button type="button" class="hor-crear-nota-warning" onclick="window.horNotaClienteNuevo()">No asignar: Cliente nuevo</button>
            </div>
        </div>
    </div>
</div>

<style>
    #hor-crear-nota {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    #hor-crear-nota[hidden] {
        display: none !important;
    }
    .hor-crear-nota-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(3, 7, 18, .55);
    }
    .hor-crear-nota-panel {
        position: relative;
        width: min(44rem, 100%);
        max-height: calc(100vh - 32px);
        overflow: auto;
        border-radius: 12px;
        padding: 24px;
        background: #fff;
        color: #111827;
        box-shadow: 0 20px 40px rgba(0, 0, 0, .25);
    }
    .dark .hor-crear-nota-panel {
        background: #111827;
        color: #f9fafb;
    }
    .hor-crear-nota-panel h2 {
        margin: 0 0 16px;
        font-size: 1rem;
        font-weight: 600;
        line-height: 1.4;
    }
    .hor-crear-nota-panel label {
        display: block;
        margin-bottom: 14px;
        font-size: .875rem;
        font-weight: 500;
    }
    .hor-crear-nota-panel input[type="text"],
    .hor-crear-nota-panel input[type="tel"] {
        display: block;
        width: 100%;
        margin-top: 6px;
        box-sizing: border-box;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 1rem;
        background: #fff;
        color: #111827;
    }
    .dark .hor-crear-nota-panel input[type="text"],
    .dark .hor-crear-nota-panel input[type="tel"] {
        background: #1f2937;
        border-color: #374151;
        color: #f9fafb;
    }
    .hor-crear-nota-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 8px;
    }
    .hor-crear-nota-primary,
    .hor-crear-nota-secondary,
    .hor-crear-nota-warning {
        border: 0;
        border-radius: 8px;
        padding: 8px 14px;
        font-size: .875rem;
        font-weight: 600;
        cursor: pointer;
    }
    .hor-crear-nota-primary {
        background: #65a30d;
        color: #fff;
        white-space: normal;
        text-align: center;
    }
    .hor-crear-nota-warning {
        background: #ea580c;
        color: #fff;
    }
    .hor-crear-nota-secondary {
        background: #e5e7eb;
        color: #111827;
    }
    .dark .hor-crear-nota-secondary {
        background: #374151;
        color: #f9fafb;
    }
    .hor-crear-nota-legend {
        margin: -8px 0 12px;
        color: #6b7280;
        font-size: .8125rem;
    }
    .hor-crear-nota-venta {
        margin: 0 0 12px;
        padding: 10px 12px;
        border-radius: 8px;
        background: #fef3c7;
        color: #92400e;
        font-size: .875rem;
        font-weight: 700;
        line-height: 1.4;
    }
    .dark .hor-crear-nota-venta {
        background: #78350f;
        color: #fef3c7;
    }
    .hor-crear-nota-hit,
    .hor-crear-nota-matches small .hor-crear-nota-hit {
        color: #dc2626;
        font-weight: 700;
    }
    .hor-crear-nota-matches {
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-height: 50vh;
        overflow: auto;
        margin-bottom: 16px;
    }
    .hor-crear-nota-matches label {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        margin: 0;
        padding: 10px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
    }
    .dark .hor-crear-nota-matches label {
        border-color: #374151;
    }
    .hor-crear-nota-matches small {
        display: block;
        font-weight: 400;
        color: #6b7280;
    }
</style>

<script>
    (function () {
        function modal() {
            return document.getElementById('hor-crear-nota');
        }

        function rememberLivewire() {
            const el = modal();
            if (!el || el.dataset.livewireId) {
                return el ? el.dataset.livewireId : '';
            }
            const root = el.closest('[wire\\:id]') || document.querySelector('[wire\\:id]');
            el.dataset.livewireId = root ? root.getAttribute('wire:id') : '';
            return el.dataset.livewireId;
        }

        function placeOnBody() {
            const el = modal();
            if (!el) {
                return;
            }
            rememberLivewire();
            if (el.parentElement !== document.body) {
                document.body.appendChild(el);
            }
        }

        function showStep(step) {
            const el = modal();
            el.querySelector('[data-step="form"]').hidden = step !== 'form';
            el.querySelector('[data-step="matches"]').hidden = step !== 'matches';
        }

        function mostrarVentaReciente(fecha) {
            const el = modal();
            const aviso = el.querySelector('[data-venta]');
            const cancelar = el.querySelector('[data-venta-cancel]');
            const asignar = el.querySelector('[data-asignar]');
            if (fecha) {
                aviso.hidden = false;
                aviso.textContent = 'VENTA RECIENTE DE ESTE CLIENTE en fecha ' + fecha;
                cancelar.hidden = false;
                asignar.textContent = 'Entendido, pero voy a crear otra nota nueva al cliente';
                return;
            }
            aviso.hidden = true;
            aviso.textContent = '';
            cancelar.hidden = true;
            asignar.textContent = 'Agrego la nota al cliente seleccionado';
        }

        window.horAbrirCrearNota = function () {
            const el = modal();
            if (!el) {
                return;
            }
            placeOnBody();
            el.querySelector('[data-nombre]').value = '';
            el.querySelector('[data-phone]').value = '';
            el.dataset.customerId = '';
            mostrarVentaReciente('');
            showStep('form');
            el.hidden = false;
            el.querySelector('[data-nombre]').focus();
        };

        window.horCerrarCrearNota = function () {
            const el = modal();
            if (el) {
                el.hidden = true;
            }
        };

        window.horContinuarCrearNota = function () {
            const el = modal();
            const component = window.Livewire.find(rememberLivewire());
            component.call(
                'continuarCrearNota',
                el.querySelector('[data-nombre]').value,
                el.querySelector('[data-phone]').value,
            );
        };

        window.horAsignarNota = function () {
            window.Livewire.find(rememberLivewire()).call(
                'asignarNotaACliente',
                modal().dataset.customerId || null,
            );
        };

        window.horNotaClienteNuevo = function () {
            window.Livewire.find(rememberLivewire()).call('crearNotaClienteNuevo');
        };

        function appendHitParts(parent, parts) {
            parts.forEach(function (part) {
                const text = part.text || '';
                if (!part.hit) {
                    parent.appendChild(document.createTextNode(text));
                    return;
                }
                const mark = document.createElement('span');
                mark.className = 'hor-crear-nota-hit';
                mark.textContent = text;
                parent.appendChild(mark);
            });
        }

        window.addEventListener('hor-crear-nota-matches', function (event) {
            const matches = (event.detail && event.detail.matches) || [];
            const el = modal();
            placeOnBody();
            const list = el.querySelector('[data-matches]');
            list.replaceChildren();
            matches.forEach(function (match) {
                const label = document.createElement('label');
                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'hor-crear-nota-customer';
                radio.value = match.id;
                radio.addEventListener('change', function () {
                    el.dataset.customerId = String(match.id);
                    mostrarVentaReciente(match.ventaFecha || '');
                });
                const text = document.createElement('span');
                const strong = document.createElement('strong');
                appendHitParts(strong, match.nameParts || []);
                text.appendChild(strong);
                if ((match.phones || []).length || match.place) {
                    const small = document.createElement('small');
                    (match.phones || []).forEach(function (phone, index) {
                        if (index > 0) {
                            small.appendChild(document.createTextNode(' / '));
                        }
                        appendHitParts(small, [phone]);
                    });
                    if (match.place) {
                        if ((match.phones || []).length) {
                            small.appendChild(document.createTextNode(' · '));
                        }
                        small.appendChild(document.createTextNode(match.place));
                    }
                    text.appendChild(small);
                }
                label.appendChild(radio);
                label.appendChild(text);
                list.appendChild(label);
            });
            const first = list.querySelector('input[type="radio"]');
            if (first) {
                first.checked = true;
                el.dataset.customerId = first.value;
                mostrarVentaReciente(matches[0] && matches[0].ventaFecha ? matches[0].ventaFecha : '');
            } else {
                el.dataset.customerId = '';
                mostrarVentaReciente('');
            }
            showStep('matches');
            el.hidden = false;
        });

        const phone = modal().querySelector('[data-phone]');
        phone.addEventListener('input', function () {
            const digits = phone.value.replace(/\D/g, '').slice(0, 9);
            const formatted = digits.replace(/(\d{3})(?=\d)/g, '$1 ');
            if (phone.value !== formatted) {
                phone.value = formatted;
            }
        });

        placeOnBody();

        document.addEventListener('livewire:init', function () {
            Livewire.hook('commit', function ({ succeed }) {
                succeed(function () {
                    const nodes = [...document.querySelectorAll('#hor-crear-nota')];
                    if (nodes.length === 0) {
                        return;
                    }
                    const keep = nodes.find(function (node) {
                        return node.parentElement === document.body;
                    }) || nodes[0];
                    nodes.forEach(function (node) {
                        if (node !== keep) {
                            node.remove();
                        }
                    });
                    if (keep.parentElement !== document.body) {
                        document.body.appendChild(keep);
                    }
                });
            });
        });
    })();
</script>
