<?php

namespace App\Listeners;

use App\Events\NotaConfirmada;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;

class EnviarNotaConfirmadaATelegram implements ShouldQueue
{
    public function __construct(
        protected TelegramService $telegram
    ) {
    }

    public function handle(NotaConfirmada $event): void
    {
        // Cargamos relaciones necesarias
        $note = $event->note->loadMissing([
            'customer',
            'comercial',
        ]);

        $confirmation = $event->confirmation;
        $customer = $note->customer;
        $com = $note->comercial;

        // ───────── CABECERA ─────────
        $mensaje = "*NOTA CONFIRMADA* ✅\n"
            . "Nota: *#{$note->nro_nota}*\n"
            . "Cliente: *{$customer->first_names} {$customer->last_names}*\n";

        if (!empty($customer->phone)) {
            $mensaje .= "Teléfono: {$customer->phone}\n";
        }

        $mensaje .= "Comercial: " . ($com ? $com->display_name : 'N/D') . "\n";

        if ($confirmation->created_at) {
            $mensaje .= "Fecha confirmación: " . $confirmation->created_at->format('d/m/Y H:i') . "\n";
        }

        // ───────── INFO CREMA ─────────
        $mensaje .= "\n*Entrega de crema*: *" . ($confirmation->dio_crema ? 'SÍ 🧴' : 'NO') . "*";

        // ───────── OBSERVACIÓN ─────────
        if (!empty($confirmation->observation)) {
            $mensaje .= "\n*Observación*: {$confirmation->observation}";
        }

        // Enviar a Telegram
        $this->telegram->sendMessage($mensaje);
    }
}
