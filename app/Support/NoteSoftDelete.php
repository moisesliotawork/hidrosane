<?php

namespace App\Support;

use App\Models\Note;

final class NoteSoftDelete
{
    public static function delete(Note $note, ?int $deletedByUserId = null): void
    {
        $note->forceFill([
            'deleted_by_user_id' => $deletedByUserId ?? auth()->id(),
        ])->saveQuietly();

        $note->delete();
    }
}
