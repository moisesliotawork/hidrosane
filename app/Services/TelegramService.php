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
            'accion_ohana' => config('services.telegram.chat_id_accion_ohana'),
            default => config('services.telegram.chat_id_ventas'),
        };

        if (blank($token) || blank($chatId)) {
            Log::warning("Telegram no configurado correctamente para el grupo '{$target}'", [
                'target' => $target,
                'token_empty' => blank($token),
                'chat_id_empty' => blank($chatId),
            ]);
            return;
        }

        // 👇 Log de entrada siempre
        Log::info('TelegramService: enviando mensaje', [
            'target' => $target,
            'chat_id' => $chatId,
            // Para no petar el log, cortamos el mensaje si es muy largo
            'message_preview' => mb_substr($message, 0, 120),
        ]);

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);

            if ($response->successful()) {
                Log::info('TelegramService: mensaje enviado correctamente', [
                    'target' => $target,
                    'status' => $response->status(),
                ]);
            } else {
                Log::error('TelegramService: error HTTP al enviar mensaje', [
                    'target' => $target,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                // 👇 Si quieres que Horizon marque el job como FALLIDO descomenta:
                $response->throw();
            }
        } catch (\Throwable $e) {
            Log::error("TelegramService: excepción enviando mensaje a Telegram ({$target})", [
                'target' => $target,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Si quieres que el job falle:
            throw $e;
        }
    }

    public function sendMessageWithInlineUrlButton(
        string $message,
        string $buttonText,
        string $url,
        string $target = 'accion_ohana'
    ): void {
        $token = config('services.telegram.bot_token');

        $chatId = match ($target) {
            'cantico' => config('services.telegram.chat_id_canticos'),
            'accion_ohana' => config('services.telegram.chat_id_accion_ohana'),
            default => config('services.telegram.chat_id_ventas'),
        };

        if (blank($token) || blank($chatId)) {
            Log::warning("Telegram no configurado correctamente para el grupo '{$target}'", [
                'target' => $target,
                'token_empty' => blank($token),
                'chat_id_empty' => blank($chatId),
            ]);

            return;
        }

        Log::info('TelegramService: enviando mensaje con botón inline', [
            'target' => $target,
            'chat_id' => $chatId,
            'message_preview' => mb_substr($message, 0, 120),
            'button_text' => $buttonText,
            'url' => $url,
        ]);

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'reply_markup' => [
                    'inline_keyboard' => [[
                        [
                            'text' => $buttonText,
                            'url' => $url,
                        ],
                    ]],
                ],
            ]);

            if ($response->successful()) {
                Log::info('TelegramService: mensaje con botón enviado correctamente', [
                    'target' => $target,
                    'status' => $response->status(),
                ]);
            } else {
                Log::error('TelegramService: error HTTP al enviar mensaje con botón', [
                    'target' => $target,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $response->throw();
            }
        } catch (\Throwable $e) {
            Log::error("TelegramService: excepción enviando mensaje con botón a Telegram ({$target})", [
                'target' => $target,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
