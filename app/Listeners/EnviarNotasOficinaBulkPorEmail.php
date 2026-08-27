<?php

namespace App\Listeners;

use App\Enums\EstadoTerminal;
use App\Events\NotasEnviadasAOficinaBulk;
use App\Mail\NotasEnviadasAOficinaMail;
use App\Models\Note;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarNotasOficinaBulkPorEmail implements ShouldQueue
{
    public function handle(NotasEnviadasAOficinaBulk $event): void
    {
        Log::info('EnviarNotasOficinaBulkPorEmail: manejando evento NotasEnviadasAOficinaBulk', [
            'note_ids' => $event->noteIds,
            'comercial_id' => $event->comercial?->id,
        ]);

        $notes = Note::query()
            ->whereIn('id', $event->noteIds)
            ->where('estado_terminal', EstadoTerminal::SALA->value)
            ->where('printed', false)
            ->with([
                'customer',
                'user',
                'comercial',
                'observations.author',
                'observacionesSala.author',
            ])
            ->orderBy('nro_nota')
            ->get();

        if ($notes->isEmpty()) {
            Log::info('EnviarNotasOficinaBulkPorEmail: no hay notas SALA no impresas para enviar.', [
                'note_ids' => $event->noteIds,
            ]);

            return;
        }

        $pdfContent = Pdf::loadView('pdf.notas-sala', ['notes' => $notes])
            ->setPaper('a4')
            ->output();

        $filename = 'notas-oficina-' . now()->format('Ymd-His') . '.pdf';

        Mail::to('info@ohanadistribucion.com')->send(
            new NotasEnviadasAOficinaMail(
                notes: $notes,
                pdfContent: $pdfContent,
                filename: $filename,
                comercialName: $event->comercial?->display_name,
            )
        );

        Log::info('EnviarNotasOficinaBulkPorEmail: correo enviado a Resend.', [
            'cantidad_notas' => $notes->count(),
            'filename' => $filename,
        ]);
    }
}
