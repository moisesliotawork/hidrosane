<?php

namespace App\Listeners;

use App\Events\NotasEnviadasAOficinaBulk;
use App\Models\Note;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class EnviarNotasOficinaBulkATelegram implements ShouldQueue
{
    public function __construct(
        protected TelegramService $telegram
    ) {
    }

    public function handle(NotasEnviadasAOficinaBulk $event): void
    {
        // 🔵 Log inicial: confirmar que el evento llegó
        Log::info('EnviarNotasOficinaBulkATelegram: manejando evento NotasEnviadasAOficinaBulk', [
            'note_ids' => $event->noteIds,
            'comercial_id' => $event->comercial?->id,
        ]);

        // Cargar notas + customer para obtener la ciudad
        $notes = Note::query()
            ->whereIn('id', $event->noteIds)
            ->with('customer:id,ciudad')
            ->orderBy('nro_nota')
            ->get(['id', 'nro_nota', 'customer_id']);

        if ($notes->isEmpty()) {

            // 🔴 Log si por algún motivo las notas no existen
            Log::warning('EnviarNotasOficinaBulkATelegram: listado de notas vacío. No se envía nada.', [
                'note_ids' => $event->noteIds,
            ]);

            return;
        }

        $comercial = $event->comercial;

        $hora = now()->format('d/m/Y H:i');
        $cantidad = $notes->count();

        // ───────── CABECERA ─────────
        $mensaje = "*ENVÍO MASIVO A OFICINA* 🏢\n"
            . "Hora: {$hora}\n"
            . "Notas enviadas: *{$cantidad}*\n"
            . "Comercial: " . ($comercial ? $comercial->display_name : 'N/D') . "\n";

        // ───────── LISTADO DE NOTAS ─────────
        $mensaje .= "\n*Listado de notas enviadas:*\n";

        foreach ($notes as $note) {
            $ciudad = $note->customer?->ciudad ?? '—';
            $mensaje .= "• #{$note->nro_nota} — {$ciudad}\n";
        }

        $mensaje = rtrim($mensaje, "\n");

        // 🔵 Log del mensaje construido (preview)
        Log::info('EnviarNotasOficinaBulkATelegram: mensaje construido', [
            'cantidad_notas' => $cantidad,
            'preview' => mb_substr($mensaje, 0, 150),
        ]);

        // Enviar a Telegram
        $this->telegram->sendMessage($mensaje, 'cantico');

        // 🔵 Log final
        Log::info('EnviarNotasOficinaBulkATelegram: envío solicitado a TelegramService', [
            'cantidad_notas' => $cantidad,
        ]);
    }
}
