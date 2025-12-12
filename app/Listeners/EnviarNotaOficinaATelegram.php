<?php

namespace App\Listeners;

use App\Events\NotaEnviadaAOficina;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class EnviarNotaOficinaATelegram implements ShouldQueue
{
    public function __construct(
        protected TelegramService $telegram
    ) {
    }

    public function handle(NotaEnviadaAOficina $event): void
    {
        // 🔵 Log inicial: confirma que el evento llegó
        Log::info('EnviarNotaOficinaATelegram: manejando evento NotaEnviadaAOficina', [
            'note_id' => $event->note->id,
            'sala_observation_id' => $event->salaObservation?->id,
        ]);

        $note = $event->note->loadMissing([
            'customer',
            'comercial',
        ]);

        $salaObs = $event->salaObservation;
        $customer = $note->customer;
        $com = $note->comercial;

        // ───────── CABECERA ─────────
        $mensaje = "*NOTA ENVIADA A OFICINA* 🏢\n"
            . "Nota: *#{$note->nro_nota}*\n"
            . "Cliente: *{$customer->first_names} {$customer->last_names}*\n";

        if (!empty($customer->phone)) {
            $mensaje .= "Teléfono: {$customer->phone}\n";
        }

        // Comercial como "empleado_id - nombre apellido"
        $mensaje .= "Comercial: " . ($com ? $com->display_name : 'N/D') . "\n";

        // Fecha/hora de envío a oficina
        if ($note->sent_to_sala_at) {
            $mensaje .= "Fecha envío oficina: " . $note->sent_to_sala_at->format('d/m/Y H:i') . "\n";
        } elseif ($salaObs?->created_at) {
            $mensaje .= "Fecha envío oficina: " . $salaObs->created_at->format('d/m/Y H:i') . "\n";
        }

        // ───────── OBSERVACIÓN DE OFICINA ─────────
        if (!empty($salaObs?->observation)) {
            $mensaje .= "\n*Observación de Oficina:*\n{$salaObs->observation}";
        }

        // 🔵 Log del mensaje construido (solo preview)
        Log::info('EnviarNotaOficinaATelegram: mensaje construido', [
            'note_id' => $note->id,
            'preview' => mb_substr($mensaje, 0, 150),
        ]);

        // Enviar a Telegram
        $this->telegram->sendMessage($mensaje, 'cantico');

        // 🔵 Log final
        Log::info('EnviarNotaOficinaATelegram: envío solicitado a TelegramService', [
            'note_id' => $note->id,
        ]);
    }
}
