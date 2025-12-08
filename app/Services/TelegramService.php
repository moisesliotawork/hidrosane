<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public function sendMessage(string $message, string $target = 'ventas'): void
    {
        $token = config('services.telegram.bot_token');

        // Elegimos grupo según el target
        $chatId = match ($target) {
            'cantico' => config('services.telegram.chat_id_canticos'),
            default => config('services.telegram.chat_id_ventas'),
        };

        if (blank($token) || blank($chatId)) {
            Log::warning("Telegram no configurado correctamente para el grupo '{$target}'");
            return;
        }

        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Throwable $e) {
            Log::error("Error enviando mensaje a Telegram ({$target}): " . $e->getMessage());
        }
    }
}
