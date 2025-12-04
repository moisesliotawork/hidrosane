<?php

namespace App\Events;

use App\Models\Note;
use App\Models\NoteConfirmation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotaConfirmada
{
    use Dispatchable, SerializesModels;

    public Note $note;
    public NoteConfirmation $confirmation;

    public function __construct(Note $note, NoteConfirmation $confirmation)
    {
        $this->note = $note;
        $this->confirmation = $confirmation;
    }
}
