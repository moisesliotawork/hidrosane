<x-filament-panels::page>
    {{ $this->form }}

    @if ($searched && count($results) >= 2)
        <div class="mt-6 overflow-hidden rounded-xl border border-white/10 bg-gray-900 shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-white/10 text-sm text-white">
                    <thead class="bg-white/5">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-200">ID</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-200">Nombre</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-200">Teléfonos</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-200">Email</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-200">DNI</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-200">Notas</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-200">Ventas</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-200">Creado</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-200">Actualizado</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-200">Rol en fusión</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-white/10">
                        @foreach ($results as $row)
                            <tr class="hover:bg-white/5 transition">
                                <td class="px-4 py-3 text-gray-100">{{ $row['id'] }}</td>

                                <td class="px-4 py-3 text-gray-100">
                                    {{ $row['name'] ?: '-' }}
                                </td>

                                <td class="px-4 py-3 text-gray-300">
                                    <div class="space-y-1">
                                        @foreach (['phone', 'secondary_phone', 'third_phone', 'phone1_commercial', 'phone2_commercial'] as $field)
                                            @if (!empty($row[$field]))
                                                <div>
                                                    <span class="font-medium text-gray-200">{{ $field }}:</span>
                                                    {{ $row[$field] }}
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-gray-300">{{ $row['email'] ?: '-' }}</td>
                                <td class="px-4 py-3 text-gray-300">{{ $row['dni'] ?: '-' }}</td>
                                <td class="px-4 py-3 text-gray-100">{{ $row['notes_count'] }}</td>
                                <td class="px-4 py-3 text-gray-100">{{ $row['ventas_count'] }}</td>
                                <td class="px-4 py-3 text-gray-300">{{ $row['created_at'] ?: '-' }}</td>
                                <td class="px-4 py-3 text-gray-300">{{ $row['updated_at'] ?: '-' }}</td>

                                <td class="px-4 py-3">
                                    <div class="flex flex-col gap-2">
                                        @if ($row['is_oldest'])
                                            <span
                                                class="inline-flex w-fit rounded-full bg-success-500/15 px-2 py-1 text-xs font-medium text-success-400">
                                                Se conserva
                                            </span>
                                        @endif

                                        @if ($row['is_latest_updated'])
                                            <span
                                                class="inline-flex w-fit rounded-full bg-primary-500/15 px-2 py-1 text-xs font-medium text-primary-400">
                                                Fuente de datos
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($searched)
        <div class="mt-6 rounded-xl border border-warning-500/20 bg-warning-500/10 p-4 text-sm text-warning-300">
            No se encontraron al menos 2 customers activos con coincidencia exacta en ese teléfono.
        </div>
    @endif
</x-filament-panels::page>