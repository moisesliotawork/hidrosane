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
            'customer',
            'comercial',
            'ventaOfertas.productos.producto',
        ]);

        $pdf = Pdf::setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('dpi', 96)
            ->loadView('pdf_pos', ['venta' => $venta])
            ->setPaper('a4');


        //  ⬇⬇⬇  **INLINE** para que el navegador lo muestre
        return $pdf->stream(
            'contrato-' . ($venta->nro_contrato) . '.pdf'
        );
    }
}
