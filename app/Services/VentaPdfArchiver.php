<?php

namespace App\Services;

use App\Models\Venta;
use App\Models\VentaPdfDownload;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Archiva en segundo plano una copia de cualquier PDF de contrato que se genere
 * hacia el usuario (descarga directa o vista previa en el visor del navegador),
 * para que SuperAdmin pueda auditar quién y cuándo lo obtuvo. No debe alterar
 * en nada la respuesta que recibe el usuario actual.
 */
class VentaPdfArchiver
{
    public static function archive(Venta $venta, string $pdfBytes, string $tipo, string $origen): void
    {
        try {
            $path = 'pdf-descargas/' . $venta->id . '/' . now()->format('YmdHis') . '_' . Str::random(6) . '.pdf';

            Storage::disk('local')->put($path, $pdfBytes);

            VentaPdfDownload::create([
                'venta_id' => $venta->id,
                'user_id' => Auth::id(),
                'tipo' => $tipo,
                'origen' => $origen,
                'file_path' => $path,
            ]);
        } catch (Throwable $exception) {
            Log::warning('No se pudo archivar la copia del PDF de contrato.', [
                'venta_id' => $venta->id,
                'origen' => $origen,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
