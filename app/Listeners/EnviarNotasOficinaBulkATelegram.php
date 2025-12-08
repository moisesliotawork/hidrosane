<?php

namespace App\Listeners;

use App\Events\NotasEnviadasAOficinaBulk;
use App\Models\Note;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;

class EnviarNotasOficinaBulkATelegram implements ShouldQueue
{
    public function __construct(
        protected TelegramService $telegram
    ) {
    }

    public function handle(NotasEnviadasAOficinaBulk $event): void
    {
        // Cargar notas + customer para obtener la ciudad
        $notes = Note::query()
            ->whereIn('id', $event->noteIds)
            ->with('customer:id,ciudad')
            ->orderBy('nro_nota')
            ->get(['id', 'nro_nota', 'customer_id']);

        if ($notes->isEmpty()) {
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

        $this->telegram->sendMessage($mensaje, 'cantico');
    }

}
