<!-- resources/views/filament/modals/entrega-simple.blade.php -->
<h2 class="text-lg font-bold mb-2">
    Declarar ENTREGA SIMPLE<br>
    <strong>HABIENDO FIRMADO el contrato
        <span class="text-green-600 bg-green-100 px-2 py-1 rounded">
            {{ $contrato ?? 'SIN CONTRATO' }}
        </span>
    </strong>
</h2>

<p class="text-sm text-gray-800 mb-3">
    Si sigues adelante con este proceso, vas a declarar este contrato como <strong>ENTREGADO</strong>.
</p>

<p class="font-semibold">Los requisitos para efectuar esta declaración de reparto son:</p>
<ol class="list-decimal pl-5 text-sm mt-1 mb-3 space-y-1">
    <li><span class="text-green-600 font-semibold">El cliente ha firmado algún contrato.</span></li>
    <li>Has entregado todos o parte de los artículos vendidos.</li>
    <li><span class="text-red-600 font-semibold">No has realizado ninguna venta como repartidor.</span></li>
</ol>

<p class="text-xs text-gray-300">
    Si se cumplen <strong>TODOS</strong> los requisitos, adelante. Si no,
    <a href="#" class="underline text-red-600 hover:text-red-700">cancela</a> para efectuar otro tipo de declaración de
    reparto.
</p>