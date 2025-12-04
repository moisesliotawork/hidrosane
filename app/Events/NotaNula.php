<?php

namespace App\Events;

use App\Models\Note;
use App\Models\NoteNullReason;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotaNula
{
    use Dispatchable, SerializesModels;

    public Note $note;
    public NoteNullReason $nullReason;

    public function __construct(Note $note, NoteNullReason $nullReason)
    {
        $this->note = $note;
        $this->nullReason = $nullReason;
    }
}
