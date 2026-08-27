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
    /**
     * Ventana de deduplicación: el visor de PDF nativo del navegador (Chrome/Edge/
     * Safari) suele repetir la petición del mismo PDF al abrirlo inline (comprobación
     * de "fast web view" / inicialización de su motor de render). Sin esta ventana,
     * una sola acción del usuario generaría 2+ registros idénticos en la auditoría.
     */
    protected const DEDUPE_SECONDS = 10;

    public static function archive(Venta $venta, string $pdfBytes, string $tipo, string $origen): void
    {
        try {
            $userId = Auth::id();

            $yaArchivadoHaceInstantes = VentaPdfDownload::query()
                ->where('venta_id', $venta->id)
                ->when($userId, fn ($q) => $q->where('user_id', $userId), fn ($q) => $q->whereNull('user_id'))
                ->where('tipo', $tipo)
                ->where('origen', $origen)
                ->where('created_at', '>=', now()->subSeconds(self::DEDUPE_SECONDS))
                ->exists();

            if ($yaArchivadoHaceInstantes) {
                return;
            }

            $path = 'pdf-descargas/' . $venta->id . '/' . now()->format('YmdHis') . '_' . Str::random(6) . '.pdf';

            Storage::disk('local')->put($path, $pdfBytes);

            VentaPdfDownload::create([
                'venta_id' => $venta->id,
                'user_id' => $userId,
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
