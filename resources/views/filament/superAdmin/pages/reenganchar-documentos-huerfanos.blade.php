<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-sky-300 bg-sky-50 p-4 text-sm text-sky-950 dark:border-sky-700 dark:bg-sky-950/40 dark:text-sky-100">
            <strong>Paso 2 · Re-enganchar documentos huérfanos.</strong>
            Este paso es independiente del alta del contrato.
            Completa primero el <strong>Paso 1 · Recuperar contrato</strong>; aquí solo se inventarian ficheros sueltos en
            <code>storage/app/public/ventas</code> y se proponen enlaces a slots vacíos (sin sobrescribir).
            Nada se escribe en BD hasta pulsar <strong>Aplicar matches claros</strong>.
        </div>

        <form wire:submit.prevent="buscarPropuestas" class="space-y-4">
            {{ $this->filterForm }}

            <div class="flex flex-wrap gap-3">
                <x-filament::button type="submit" color="warning" wire:loading.attr="disabled">
                    Buscar huérfanos / propuestas
                </x-filament::button>

                <x-filament::button
                    type="button"
                    color="success"
                    wire:click="aplicarMatchesClaros"
                    wire:loading.attr="disabled"
                    wire:confirm="¿Enlazar solo los matches marcados como auto? No sobrescribe documentos ya presentes."
                >
                    Aplicar matches claros
                </x-filament::button>
            </div>
        </form>

        @if ($lastError)
            <div class="rounded-lg border border-danger-300 bg-danger-50 p-3 text-sm text-danger-800 dark:border-danger-700 dark:bg-danger-950/40 dark:text-danger-100">
                {{ $lastError }}
            </div>
        @endif

        @if ($searched)
            <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-700 dark:bg-gray-900">
                <p>
                    <strong>Ventas objetivo:</strong> {{ $targetVentaCount }}
                    · <strong>Huérfanos inventariados:</strong> {{ $orphanCount }}
                    · <strong>Propuestas:</strong> {{ count($proposals) }}
                </p>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full min-w-[900px] divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Acción</th>
                            <th class="px-3 py-2 text-left font-semibold">Venta</th>
                            <th class="px-3 py-2 text-left font-semibold">Nº</th>
                            <th class="px-3 py-2 text-left font-semibold">Campo</th>
                            <th class="px-3 py-2 text-left font-semibold">Score</th>
                            <th class="px-3 py-2 text-left font-semibold">OCR DNI</th>
                            <th class="px-3 py-2 text-left font-semibold">OCR fecha</th>
                            <th class="px-3 py-2 text-left font-semibold">Path</th>
                            <th class="px-3 py-2 text-left font-semibold">Motivo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($proposals as $p)
                            @php
                                $action = $p['action'] ?? 'skip';
                                $badge = match ($action) {
                                    'auto' => 'bg-emerald-100 text-emerald-900',
                                    'review' => 'bg-amber-100 text-amber-900',
                                    default => 'bg-gray-100 text-gray-700',
                                };
                            @endphp
                            <tr>
                                <td class="px-3 py-2">
                                    <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $badge }}">{{ $action }}</span>
                                </td>
                                <td class="px-3 py-2">{{ $p['venta_id'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $p['nro_contr_adm'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $p['field'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $p['score'] ?? 0 }}</td>
                                <td class="px-3 py-2">{{ $p['ocr_dni'] ?: '—' }}</td>
                                <td class="px-3 py-2">{{ $p['ocr_fecha'] ?: '—' }}</td>
                                <td class="max-w-xs truncate px-3 py-2 font-mono text-xs" title="{{ $p['path'] ?? '' }}">{{ $p['path'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-300">{{ $p['reason'] ?? '' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-6 text-center text-gray-500">
                                    No hay propuestas. Revisa slots vacíos abajo o el mes de carga.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        <div class="space-y-2">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                Contratos recuperados con slots vacíos (vista previa, sin inventariar disco)
            </h3>
            <p class="text-xs text-gray-500">
                Campos contemplados: {{ collect($this->documentSlotLabels())->pluck('label')->implode(', ') }}.
            </p>
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full min-w-[640px] divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Venta</th>
                            <th class="px-3 py-2 text-left font-semibold">Nº</th>
                            <th class="px-3 py-2 text-left font-semibold">Cliente</th>
                            <th class="px-3 py-2 text-left font-semibold">Pendientes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->recoveredPendingPreview() as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row['venta_id'] }}</td>
                                <td class="px-3 py-2">{{ $row['nro'] }}</td>
                                <td class="px-3 py-2">{{ $row['cliente'] }}</td>
                                <td class="px-3 py-2 text-xs">{{ $row['pendientes'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-6 text-center text-gray-500">
                                    No hay contratos recuperados con documentos pendientes, o aún no hay recuperaciones.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
