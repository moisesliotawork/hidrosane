<?php

namespace App\Events;

use App\Models\Note;
use App\Models\NoteSalaObservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotaEnviadaAOficina
{
    use Dispatchable, SerializesModels;

    public Note $note;
    public NoteSalaObservation $salaObservation;

    public function __construct(Note $note, NoteSalaObservation $salaObservation)
    {
        $this->note = $note;
        $this->salaObservation = $salaObservation;
    }
}
