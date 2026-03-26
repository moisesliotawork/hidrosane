<div class="p-2">
    <table class="w-full text-sm text-left border-collapse">
        <thead>
            <tr class="border-b border-gray-700">
                <th class="py-2 px-1">Nº Nota</th>
                <th class="py-2 px-1">Cliente</th>
                <th class="py-2 px-1">Comercial</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $record)
                <tr class="border-b border-gray-800">
                    <td class="py-2 px-1 font-mono text-pink-500">{{ $record->nro_nota }}</td>
                    <td class="py-2 px-1 font-bold">{{ strtoupper($record->customer?->name) }}</td>
                    <td class="py-2 px-1 text-blue-400">{{ $record->comercial?->name }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="mt-4 p-2 bg-gray-900 rounded-lg text-xs">
        Total seleccionados: <strong>{{ $records->count() }}</strong>
    </div>
</div>