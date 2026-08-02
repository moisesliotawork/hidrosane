<div
    x-data="recoveryVoiceDictation()"
    x-init="init()"
    class="rounded-xl border border-sky-200 bg-sky-50/80 p-4 dark:border-sky-800 dark:bg-sky-950/40"
>
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-bold text-sky-950 dark:text-sky-100">Dictar con el micrófono</p>
            <p class="text-xs text-sky-800/80 dark:text-sky-200/80">
                Usa <strong>REANUDAR DICTADO</strong> para hablar, <strong>DETENER DICTADO</strong> para pausar,
                revisa el texto y luego «Procesar dictado». Mejor en Chrome o Safari (macOS).
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                x-show="!listening"
                x-cloak
                x-on:click="resume()"
                x-bind:disabled="!supported"
                class="fi-btn relative inline-flex items-center justify-center gap-1 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold uppercase tracking-wide text-white shadow-sm outline-none transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <x-filament::icon icon="heroicon-m-microphone" class="h-4 w-4" />
                REANUDAR DICTADO
            </button>
            <button
                type="button"
                x-show="listening"
                x-cloak
                x-on:click="stop()"
                class="fi-btn relative inline-flex items-center justify-center gap-1 rounded-lg bg-danger-600 px-3 py-2 text-sm font-semibold uppercase tracking-wide text-white shadow-sm outline-none transition hover:bg-danger-500"
            >
                <span class="relative flex h-2.5 w-2.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-white"></span>
                </span>
                DETENER DICTADO
            </button>
            <button
                type="button"
                x-on:click="clearText()"
                x-bind:disabled="listening || !draft"
                class="fi-btn relative inline-flex items-center justify-center rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-950/10 transition hover:bg-gray-50 disabled:opacity-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10"
            >
                Limpiar texto
            </button>
        </div>
    </div>

    <p
        class="mb-2 text-xs font-medium"
        x-bind:class="listening ? 'text-danger-600 dark:text-danger-400' : 'text-gray-500 dark:text-gray-400'"
        x-text="status"
    ></p>

    <template x-if="!supported">
        <p class="mb-2 text-xs text-amber-800 dark:text-amber-200">
            Este navegador no soporta dictado en vivo. Usa Safari/Chrome o sube un audio / pega el texto.
        </p>
    </template>

    <label class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">
        Texto del dictado (editable)
    </label>
    <textarea
        x-model="draft"
        x-on:input="sync()"
        rows="5"
        placeholder="Aquí aparecerá lo que digas… Ej.: Cliente José Entenza DNI 52490318V contrato 1189 fecha promo 2 de octubre…"
        class="block w-full rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
    ></textarea>
</div>

<script>
    function recoveryVoiceDictation() {
        return {
            listening: false,
            hasPaused: false,
            supported: false,
            recognition: null,
            draft: '',
            baseText: '',
            status: 'Listo. Pulsa REANUDAR DICTADO para empezar.',
            init() {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                this.supported = !!SpeechRecognition;
                this.draft = @js($initialTranscript ?? '');
                if (!this.supported) {
                    this.status = 'Dictado en vivo no disponible en este navegador.';
                    return;
                }
                this.recognition = new SpeechRecognition();
                this.recognition.lang = 'es-ES';
                this.recognition.continuous = true;
                this.recognition.interimResults = true;
                this.recognition.onresult = (event) => {
                    let interim = '';
                    let finalChunk = '';
                    for (let i = event.resultIndex; i < event.results.length; i++) {
                        const result = event.results[i];
                        if (result.isFinal) {
                            finalChunk += result[0].transcript;
                        } else {
                            interim += result[0].transcript;
                        }
                    }
                    if (finalChunk) {
                        this.baseText = (this.baseText + ' ' + finalChunk).replace(/\s+/g, ' ').trim();
                    }
                    this.draft = (this.baseText + (interim ? ' ' + interim : '')).replace(/\s+/g, ' ').trim();
                    this.sync();
                };
                this.recognition.onerror = (event) => {
                    if (event.error === 'aborted') {
                        return;
                    }
                    this.listening = false;
                    this.hasPaused = true;
                    this.status = 'Error de micrófono: ' + (event.error || 'desconocido') + '. Puedes REANUDAR DICTADO.';
                };
                this.recognition.onend = () => {
                    if (this.listening) {
                        try {
                            this.recognition.start();
                        } catch (e) {
                            this.listening = false;
                            this.hasPaused = true;
                            this.status = 'Dictado detenido. Pulsa REANUDAR DICTADO o Procesar dictado.';
                            this.sync();
                        }
                    } else {
                        this.status = 'Dictado detenido. Pulsa REANUDAR DICTADO o Procesar dictado.';
                        this.sync();
                    }
                };
            },
            resume() {
                if (!this.recognition) return;
                this.baseText = (this.draft || '').trim();
                this.listening = true;
                this.status = 'Escuchando… habla los datos del contrato. Pulsa DETENER DICTADO para pausar.';
                try {
                    this.recognition.start();
                } catch (e) {
                    this.listening = false;
                    this.hasPaused = true;
                    this.status = 'No se pudo iniciar el micrófono. Revisa permisos del Mac/navegador.';
                }
            },
            stop() {
                this.listening = false;
                this.hasPaused = true;
                try {
                    this.recognition && this.recognition.stop();
                } catch (e) {}
                this.status = 'Dictado detenido. Pulsa REANUDAR DICTADO para continuar o Procesar dictado.';
                this.sync();
            },
            clearText() {
                this.draft = '';
                this.baseText = '';
                this.hasPaused = false;
                this.sync();
                this.status = 'Texto limpiado. Pulsa REANUDAR DICTADO para empezar.';
            },
            sync() {
                $wire.set('voiceData.transcript_manual', this.draft || '');
            },
        };
    }
</script>
