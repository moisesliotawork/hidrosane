<div class="text-sm text-gray-800 space-y-4">
    <p>
        Si sigues adelante con este proceso, vas a declarar este contrato
        <span class="text-green-600 bg-green-100 px-2 py-1 rounded">{{ $contrato ?? 'SIN CONTRATO' }}</span>
        como <strong>ENTREGADO</strong>.
    </p>

    <p class="font-semibold">Los requisitos para efectuar esta declaración de reparto son:</p>

    <ol class="list-decimal pl-5 space-y-1 text-sm">
        <li><span class="text-green-600 font-semibold">El cliente ha firmado algún contrato.</span></li>
        <li>Has entregado todos o parte de los artículos vendidos.</li>
        <li><span class="text-red-600 font-semibold">Has realizado alguna venta como repartidor.</span></li>
    </ol>

    <p class="text-xs text-gray-700">
        Si se cumplen <strong>TODOS</strong> los requisitos, adelante. Si no,
        <a href="#" class="underline text-red-600 hover:text-red-700">cancela</a>
        para efectuar otro tipo de declaración de reparto.
    </p>
</div>