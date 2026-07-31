{{-- Confirmación al quitar documentos de contrato (FilePond X). No borra el archivo del disco. --}}
<div
    id="venta-doc-remove-modal"
    class="fixed inset-0 z-[100] hidden items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="venta-doc-remove-title"
>
    <div class="absolute inset-0 bg-gray-950/50" data-venta-doc-cancel></div>
    <div class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h2 id="venta-doc-remove-title" class="text-lg font-bold text-danger-600 dark:text-danger-400">
            ATENCION!!
        </h2>
        <p class="mt-2 text-sm text-gray-700 dark:text-gray-200">
            Estás seguro de querer borrar este documento ?
        </p>
        <div class="mt-6 flex justify-end gap-3">
            <button
                type="button"
                data-venta-doc-cancel
                class="fi-btn fi-btn-size-md fi-btn-color-gray relative inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold outline-none bg-white text-gray-700 shadow-sm ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/20 dark:hover:bg-white/10"
            >
                Cancelar
            </button>
            <button
                type="button"
                data-venta-doc-confirm
                class="fi-btn fi-btn-size-md relative inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold outline-none bg-danger-600 text-white hover:bg-danger-500"
            >
                Sí, borrar
            </button>
        </div>
    </div>
</div>

<script>
    (function () {
        document.querySelectorAll('#venta-doc-remove-modal').forEach((el, index) => {
            if (index > 0) {
                el.remove();
            }
        });

        if (window.__ventaDocUploadConfirmInit) {
            if (typeof window.__ventaDocPatchFilePond === 'function') {
                window.__ventaDocPatchFilePond();
            }
            return;
        }
        window.__ventaDocUploadConfirmInit = true;

        const MESSAGE = 'ATENCION!! Estás seguro de querer borrar este documento ?';
        let resolveAsk = null;

        function modalEl() {
            return document.getElementById('venta-doc-remove-modal');
        }

        function closeModal(result) {
            const modal = modalEl();
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
            if (resolveAsk) {
                const done = resolveAsk;
                resolveAsk = null;
                done(result);
            }
        }

        function askConfirm() {
            return new Promise((resolve) => {
                const modal = modalEl();
                if (!modal) {
                    resolve(window.confirm(MESSAGE));
                    return;
                }
                resolveAsk = resolve;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            });
        }

        document.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }
            if (target.closest('[data-venta-doc-confirm]')) {
                event.preventDefault();
                closeModal(true);
                return;
            }
            if (target.closest('[data-venta-doc-cancel]')) {
                event.preventDefault();
                closeModal(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && resolveAsk) {
                closeModal(false);
            }
        });

        function patchFilePond() {
            if (!window.FilePond || window.FilePond.create.__ventaDocPatched) {
                return Boolean(window.FilePond?.create?.__ventaDocPatched);
            }

            const originalCreate = window.FilePond.create.bind(window.FilePond);

            window.FilePond.create = function (input, options = {}) {
                const wrapper =
                    input?.closest?.('[data-venta-document-upload]') ||
                    input?.closest?.('.venta-document-upload');

                if (wrapper) {
                    const previous = options.beforeRemoveFile;
                    options = {
                        ...options,
                        beforeRemoveFile: async (item) => {
                            if (typeof previous === 'function') {
                                const prior = await previous(item);
                                if (prior === false) {
                                    return false;
                                }
                            }
                            return askConfirm();
                        },
                    };
                }

                return originalCreate(input, options);
            };

            window.FilePond.create.__ventaDocPatched = true;

            return true;
        }

        window.__ventaDocPatchFilePond = patchFilePond;

        const timer = setInterval(() => {
            if (patchFilePond()) {
                clearInterval(timer);
            }
        }, 50);

        document.addEventListener('livewire:navigated', () => {
            document.querySelectorAll('#venta-doc-remove-modal').forEach((el, index) => {
                if (index > 0) {
                    el.remove();
                }
            });
            patchFilePond();
        });
    })();
</script>
