<div class="space-y-3">
    <div class="text-sm text-gray-600 dark:text-gray-300">
        Revisa el histórico. Si corresponde, crea una nueva nota usando el botón de la derecha.
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr class="text-left">
                    <th class="px-3 py-2 font-semibold">Cliente</th>
                    <th class="px-3 py-2 font-semibold">Teléfono</th>
                    <th class="px-3 py-2 font-semibold">Dirección</th>
                    <th class="px-3 py-2 font-semibold">Fecha nota</th>
                    <th class="px-3 py-2 font-semibold">Estado</th>
                    <th class="px-3 py-2 font-semibold">Resumen</th>
                    <th class="px-3 py-2 font-semibold text-right">Acción</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                @forelse($rows as $row)
                    <tr>
                        <td class="px-3 py-2">
                            <div class="font-semibold text-gray-900 dark:text-gray-100">
                                {{ $row['customer_name'] }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                ID: {{ $row['customer_id'] }}
                            </div>
                        </td>

                        <td class="px-3 py-2">
                            {{ $row['customer_phone'] ?? '-' }}
                        </td>

                        <td class="px-3 py-2">
                            {{ $row['customer_address'] ?: '-' }}
                        </td>

                        <td class="px-3 py-2">
                            {{ $row['note_date'] ?? '-' }}
                        </td>

                        <td class="px-3 py-2">
                            {{ $row['note_status'] ?? '-' }}
                        </td>

                        <td class="px-3 py-2">
                            {{ $row['note_excerpt'] ?? '-' }}
                        </td>

                        <td class="px-3 py-2 text-right">
                            <a
                                href="{{ $row['create_url'] }}"
                                class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-semibold
                                       bg-primary-600 text-white hover:bg-primary-500"
                            >
                                Crear nota
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center text-gray-500">
                            No hay coincidencias para mostrar.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
