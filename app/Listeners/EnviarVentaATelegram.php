<?php

namespace App\Listeners;

use App\Events\VentaCreada;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class EnviarVentaATelegram implements ShouldQueue
{
    public function __construct(
        protected TelegramService $telegram
    ) {
    }

    public function handle(VentaCreada $event): void
    {
        // 🔵 Log inicial: confirmar que el evento llegó
        Log::info('EnviarVentaATelegram: manejando evento VentaCreada', [
            'venta_id' => $event->venta->id,
            'note_id' => $event->venta->note_id ?? null,
            'customer_id' => $event->venta->customer_id ?? null,
        ]);

        // Cargamos lo necesario de la venta
        $venta = $event->venta->loadMissing([
            'customer',
            'comercial',
            'companion',
            'note',
            'ventaOfertas', // solo para contar
        ]);

        $customer = $venta->customer;
        $com = $venta->comercial;

        // ────────────── CABECERA BÁSICA ──────────────
        $mensaje = "*Nueva VENTA declarada* ✅\n"
            . "Cliente: *{$customer->first_names} {$customer->last_names}*\n"
            . "Importe: *" . number_format($venta->importe_total, 2, ',', '.') . " €*\n"
            . "Comercial: " . ($com ? $com->display_name : 'N/D') . "\n"
            . "Fecha venta: " . $venta->fecha_venta?->format('d/m/Y H:i') . "\n"
            . "Nota: #{$venta->note->nro_nota}\n"
            . "\nCompañero: {$venta->companion_label}\n";

        // ────────────── RESUMEN ECONÓMICO ──────────────
        $numOfertas = $venta->ventaOfertas->count();
        $numCuotas = $venta->num_cuotas;
        $cuotaMensual = $venta->cuota_mensual;
        $importeTotalF = number_format($venta->importe_total, 2, ',', '.');

        $mensaje .= "\n\n*Resumen económico*\n";
        $mensaje .= "Ofertas incluidas: *{$numOfertas}*\n";

        if ($numCuotas) {
            $mensaje .= "Nº de cuotas: *{$numCuotas}*\n";
        }

        if (!is_null($cuotaMensual)) {
            $cuotaMensualF = number_format($cuotaMensual, 2, ',', '.');
            $mensaje .= "Cuota mensual: *{$cuotaMensualF} €*\n";

            if ($numCuotas) {
                // Ejemplo: "Operación: 6 x 123,45 € = 740,70 €"
                $mensaje .= "Operación: {$numCuotas} x {$cuotaMensualF} € = {$importeTotalF} €";
            }
        }

        // ────────────── DOCUMENTOS SUBIDOS ──────────────
        $documentos = [
            'precontractual' => 'Precontractual',
            'dni_anverso' => 'DNI – Anverso',
            'dni_reverso' => 'DNI – Reverso',
            'documento_titularidad' => 'Documento de titularidad',
            'nomina' => 'Nómina',
            'pension' => 'Pensión',
            'contrato_firmado' => 'Otro Documento',
        ];

        $subidos = [];

        foreach ($documentos as $field => $label) {
            if (!empty($venta->$field)) {
                $subidos[] = $label;
            }
        }

        $mensaje .= "\n\n*Documentos subidos*\n";

        if (!empty($subidos)) {
            // En lista con viñetas
            foreach ($subidos as $doc) {
                $mensaje .= "• {$doc}\n";
            }
            // quitamos salto extra final por estética
            $mensaje = rtrim($mensaje, "\n");
        } else {
            $mensaje .= "Ninguno";
        }

        // 🔵 Log del mensaje construido (solo preview)
        Log::info('EnviarVentaATelegram: mensaje construido', [
            'venta_id' => $venta->id,
            'preview' => mb_substr($mensaje, 0, 180),
        ]);

        // ────────────── ENVÍO ──────────────
        $this->telegram->sendMessage($mensaje, 'ventas');

        // 🔵 Log final: se pidió el envío al servicio
        Log::info('EnviarVentaATelegram: envío solicitado a TelegramService', [
            'venta_id' => $venta->id,
        ]);
    }
}
