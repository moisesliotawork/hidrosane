<?php

namespace App\Support;

use App\Models\Note;

final class NoteSoftRestore
{
    public static function restore(Note $note): void
    {
        $note->restore();

        $note->forceFill([
            'deleted_by_user_id' => null,
        ])->saveQuietly();
    }
}
