<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Services\VentaPdfArchiver;
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

        $bytes = $pdf->output();

        // El visor del navegador (Acrobat/Chrome) permite descargar el PDF desde
        // aquí sin pasar por el botón "Contrato PDF", así que archivamos también
        // en la vista previa para no perder rastro de esa vía de descarga.
        VentaPdfArchiver::archive($venta, $bytes, 'normal', 'vista_previa');

        $filename = 'contrato-' . ($venta->nro_contrato) . '.pdf';

        //  ⬇⬇⬇  **INLINE** para que el navegador lo muestre
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
