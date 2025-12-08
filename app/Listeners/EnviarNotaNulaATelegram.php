<?php

namespace App\Listeners;

use App\Events\NotaNula;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;

class EnviarNotaNulaATelegram implements ShouldQueue
{
    public function __construct(
        protected TelegramService $telegram
    ) {
    }

    public function handle(NotaNula $event): void
    {
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

        // 👇 empleado_id + nombre + apellido
        $mensaje .= "Comercial: " . ($com ? $com->display_name : 'N/D') . "\n";

        if ($nullReason->created_at) {
            $mensaje .= "Fecha de nulidad: " . $nullReason->created_at->format('d/m/Y H:i') . "\n";
        }

        // ───────── MOTIVO ─────────
        if (!empty($nullReason->reason)) {
            $mensaje .= "\n*Motivo de nulidad:*\n{$nullReason->reason}";
        }

        $this->telegram->sendMessage($mensaje, 'cantico');
    }
}
