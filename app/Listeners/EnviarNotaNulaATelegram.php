<?php

namespace App\Listeners;

use App\Events\NotaNula;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class EnviarNotaNulaATelegram implements ShouldQueue
{
    public function __construct(
        protected TelegramService $telegram
    ) {
    }

    public function handle(NotaNula $event): void
    {
        // 🔵 Log inicial: confirmar que el evento llegó al listener
        Log::info('EnviarNotaNulaATelegram: manejando evento NotaNula', [
            'note_id'      => $event->note->id,
            'null_reason_id' => $event->nullReason?->id,
        ]);

        // Cargar relaciones
        $note = $event->note->loadMissing([
            'customer',
            'comercial',
        ]);

        $nullReason = $event->nullReason;
        $customer = $note->customer;
        $com = $note->comercial;

        // ───────── CABECERA ─────────
        $mensaje = "*NOTA NULA* ⛔\n"
            . "Nota: *#{$note->nro_nota}*\n"
            . "Cliente: *{$customer->first_names} {$customer->last_names}*\n";

        if (!empty($customer->phone)) {
            $mensaje .= "Teléfono: {$customer->phone}\n";
        }

        // empleado_id + nombre + apellido (lo asumes ya en display_name)
        $mensaje .= "Comercial: " . ($com ? $com->display_name : 'N/D') . "\n";

        if ($note->fecha_declaracion) {
            $mensaje .= "Fecha confirmación: " . $note->fecha_declaracion->format('d/m/Y H:i') . "\n";
        }

        // ───────── MOTIVO ─────────
        if (!empty($nullReason?->reason)) {
            $mensaje .= "\n*Motivo de nulidad:*\n{$nullReason->reason}";
        }

        // 🔵 Log del mensaje construido (solo preview)
        Log::info('EnviarNotaNulaATelegram: mensaje construido', [
            'note_id' => $note->id,
            'preview' => mb_substr($mensaje, 0, 150),
        ]);

        // Enviar a Telegram
        $this->telegram->sendMessage($mensaje, 'cantico');

        // 🔵 Log final
        Log::info('EnviarNotaNulaATelegram: envío solicitado a TelegramService', [
            'note_id' => $note->id,
        ]);
    }
}
