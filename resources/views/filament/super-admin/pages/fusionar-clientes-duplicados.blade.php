<x-filament-panels::page>
    {{ $this->form }}

    @if ($searched && count($results) >= 2)
        <div class="mt-6 overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">ID</th>
                        <th class="px-4 py-3 text-left font-semibold">Nombre</th>
                        <th class="px-4 py-3 text-left font-semibold">Teléfonos</th>
                        <th class="px-4 py-3 text-left font-semibold">Email</th>
                        <th class="px-4 py-3 text-left font-semibold">DNI</th>
                        <th class="px-4 py-3 text-left font-semibold">Notas</th>
                        <th class="px-4 py-3 text-left font-semibold">Ventas</th>
                        <th class="px-4 py-3 text-left font-semibold">Creado</th>
                        <th class="px-4 py-3 text-left font-semibold">Actualizado</th>
                        <th class="px-4 py-3 text-left font-semibold">Rol en fusión</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @foreach ($results as $row)
                        <tr>
                            <td class="px-4 py-3">{{ $row['id'] }}</td>
                            <td class="px-4 py-3">{{ $row['name'] ?: '-' }}</td>

                            <td class="px-4 py-3">
                                <div class="space-y-1">
                                    @foreach (['phone', 'secondary_phone', 'third_phone', 'phone1_commercial', 'phone2_commercial'] as $field)
                                        @if (!empty($row[$field]))
                                            <div>
                                                <span class="font-medium">{{ $field }}:</span>
                                                {{ $row[$field] }}
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </td>

                            <td class="px-4 py-3">{{ $row['email'] ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $row['dni'] ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $row['notes_count'] }}</td>
                            <td class="px-4 py-3">{{ $row['ventas_count'] }}</td>
                            <td class="px-4 py-3">{{ $row['created_at'] ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $row['updated_at'] ?: '-' }}</td>

                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-2">
                                    @if ($row['is_oldest'])
                                        <span
                                            class="inline-flex w-fit rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">
                                            Se conserva
                                        </span>
                                    @endif

                                    @if ($row['is_latest_updated'])
                                        <span
                                            class="inline-flex w-fit rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-700">
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
    @elseif($searched)
        <div class="mt-6 rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800">
            No se encontraron al menos 2 customers activos con coincidencia exacta en ese teléfono.
        </div>
    @endif
</x-filament-panels::page>