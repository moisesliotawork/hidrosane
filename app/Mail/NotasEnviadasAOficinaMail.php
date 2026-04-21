<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class NotasEnviadasAOficinaMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Collection<int, \App\Models\Note> $notes
     */
    public function __construct(
        public Collection $notes,
        public string $pdfContent,
        public string $filename,
        public ?string $comercialName = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $fecha = now()->format('d/m/Y H:i');
        $cantidad = $this->notes->count();

        return new Envelope(
            subject: "Notas enviadas a Oficina - {$cantidad} nota(s) - {$fecha}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notas-enviadas-oficina',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn() => $this->pdfContent,
                $this->filename,
            )->withMime('application/pdf'),
        ];
    }
}
