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
            const isSafariDesktop =
                /^((?!chrome|android).)*safari/i.test(navigator.userAgent) &&
                !/iphone|ipad|ipod/i.test(navigator.userAgent);

            window.FilePond.create = function (input, options = {}) {
                const wrapper =
                    input?.closest?.('[data-venta-document-upload]') ||
                    input?.closest?.('.venta-document-upload');

                if (wrapper) {
                    const previousRemove = options.beforeRemoveFile;
                    const previousAddFile = options.onaddfile;
                    const previousBeforeAdd = options.beforeAddFile;
                    const maxBytes = 25600 * 1024;
                    const rawExt = ['dng', 'raw', 'cr2', 'nef', 'arw', 'orf', 'rw2'];
                    const okExt = ['png', 'jpg', 'jpeg', 'webp', 'heic', 'heif', 'pdf'];

                    options = {
                        ...options,
                        // Safari escritorio a veces aplaza/omite previews grandes.
                        imagePreviewMaxInstantPreviewFileSize: Number.MAX_SAFE_INTEGER,
                        // Rechazo claro ANTES de colgarse en “Subiendo 1%” (ProRAW/DNG, etc.).
                        beforeAddFile: (item) => {
                            if (typeof previousBeforeAdd === 'function') {
                                const prior = previousBeforeAdd(item);
                                if (prior === false) {
                                    return false;
                                }
                            }

                            const name = (item?.filename || item?.file?.name || '').toString();
                            const ext = (name.split('.').pop() || '').toLowerCase();
                            const size = Number(item?.fileSize || item?.file?.size || 0);

                            if (rawExt.includes(ext)) {
                                window.alert(
                                    'No se puede subir foto RAW/ProRAW (.' +
                                        ext +
                                        ').\n\nEn el iPhone: Ajustes → Cámara → Formatos → desactiva Apple ProRAW.\nO usa una foto JPEG/HEIC normal.',
                                );
                                return false;
                            }

                            if (ext && !okExt.includes(ext)) {
                                window.alert(
                                    'Formato no permitido (.' +
                                        ext +
                                        '). Usa JPEG, HEIC, PNG o PDF.',
                                );
                                return false;
                            }

                            if (size > maxBytes) {
                                const mb = (size / 1048576).toFixed(1);
                                window.alert(
                                    'Archivo demasiado grande (' +
                                        mb +
                                        ' MB). Máximo 25 MB.\nPrueba otra foto o baja la calidad.',
                                );
                                return false;
                            }

                            return true;
                        },
                        beforeRemoveFile: async (item) => {
                            if (typeof previousRemove === 'function') {
                                const prior = await previousRemove(item);
                                if (prior === false) {
                                    return false;
                                }
                            }
                            return askConfirm();
                        },
                        onaddfile: (error, file) => {
                            if (typeof previousAddFile === 'function') {
                                previousAddFile(error, file);
                            }

                            if (error || !isSafariDesktop || !file) {
                                return;
                            }

                            const type = file.fileType || file.type || '';
                            const source = typeof file.source === 'string' ? file.source : null;
                            if (!source || !/^image\//.test(type)) {
                                return;
                            }

                            // Fallback: si FilePond no dibuja canvas/img, inyectar <img>.
                            window.setTimeout(() => {
                                const root = input?.closest?.('.filepond--root') || wrapper;
                                const itemEl =
                                    root?.querySelector?.(`.filepond--item[id="${file.id}"]`) ||
                                    root?.querySelector?.('.filepond--item');
                                const preview =
                                    itemEl?.querySelector?.('.filepond--image-preview') ||
                                    itemEl?.querySelector?.('.filepond--image-preview-wrapper');

                                if (!preview) {
                                    return;
                                }

                                if (preview.querySelector('canvas, img.venta-doc-safari-fallback')) {
                                    return;
                                }

                                const img = document.createElement('img');
                                img.className = 'venta-doc-safari-fallback';
                                img.src = source;
                                img.alt = '';
                                img.style.cssText =
                                    'position:absolute;inset:0;width:100%;height:100%;object-fit:contain;z-index:1;';
                                preview.style.position = preview.style.position || 'relative';
                                preview.appendChild(img);
                            }, 400);
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
