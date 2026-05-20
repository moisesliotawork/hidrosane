<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;

class ContratoPreviewBController extends Controller
{
    public function __invoke(Venta $venta)
    {
        $venta->load([
            'note',
            'customer',
            'comercial',
            'repartidor',
            'ventaOfertas.productos.producto',
        ]);

        $pdf = Pdf::setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('dpi', 96)
            ->loadView('pdf_pos_b', ['venta' => $venta])
            ->setPaper('a4');

        return $pdf->stream(
            'contrato-' . $venta->nro_contr_adm . '.pdf'
        );
    }
}
