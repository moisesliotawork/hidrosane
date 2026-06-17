<?php

namespace App\Listeners;

use App\Events\PuntoComercialEnviado;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class EnviarPuntoComercialATelegram implements ShouldQueue
{
    public function __construct(
        protected TelegramService $telegram
    ) {
    }

    public function handle(PuntoComercialEnviado $event): void
    {
        $report = $event->report->loadMissing('teamLeader');
        $leader = $report->teamLeader;

        if (! $leader) {
            Log::warning('EnviarPuntoComercialATelegram: reporte sin jefe de equipo', [
                'report_id' => $report->id,
            ]);

            return;
        }

        $fechaHora = $report->submitted_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');
        $texto = $this->escapeMarkdown($report->texto);
        $leaderLabel = trim("{$leader->empleado_id} {$leader->name} {$leader->last_name}");

        $mensaje = "*Punto Comercial de:* {$this->escapeMarkdown($leaderLabel)}\n"
            . "Fecha: *{$fechaHora}*\n\n"
            . "*Escrito:*\n{$texto}";

        $mapsUrl = $report->mapsUrl();

        if ($mapsUrl) {
            $this->telegram->sendMessageWithInlineUrlButton(
                message: $mensaje,
                buttonText: 'IR',
                url: $mapsUrl,
                target: 'accion_ohana',
            );
        } else {
            $this->telegram->sendMessage($mensaje, 'accion_ohana');
        }

        Log::info('EnviarPuntoComercialATelegram: envío solicitado', [
            'report_id' => $report->id,
            'team_leader_id' => $leader->id,
        ]);
    }

    private function escapeMarkdown(string $text): string
    {
        return str_replace(
            ['_', '*', '[', '`'],
            ['\\_', '\\*', '\\[', '\\`'],
            $text
        );
    }
}
