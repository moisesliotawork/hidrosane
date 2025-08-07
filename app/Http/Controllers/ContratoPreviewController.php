<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;

class ContratoPreviewController extends Controller
{
    public function __invoke(Venta $venta)
    {
        $venta->load([
            'note',
            'customer.postalCode.city',
            'comercial',
            'ventaOfertas.productos.producto',
        ]);

        $pdf = Pdf::loadView('pdf', ['venta' => $venta])
                  ->setPaper('letter');        // o 'a4'

        //  ⬇⬇⬇  **INLINE** para que el navegador lo muestre
        return $pdf->stream(
            'contrato-' . ($venta->nro_contrato) . '.pdf'
        );
    }
}
