<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public function sendMessage(string $message): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (blank($token) || blank($chatId)) {
            Log::warning('No se ha configurado Telegram (TOKEN o CHAT_ID vacío).');
            return;
        }

        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown', // opcional
            ]);
        } catch (\Throwable $e) {
            Log::error('Error enviando mensaje a Telegram: ' . $e->getMessage());
        }
    }
}
