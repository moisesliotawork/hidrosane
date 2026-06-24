<?php

namespace App\Support;

use App\Enums\EstadoTerminal;
use App\Events\NotaEnviadaAOficina;
use App\Events\NotasEnviadasAOficinaBulk;
use App\Models\Note;
use App\Models\NoteSalaEvent;
use App\Models\NoteSalaObservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NoteSalaActions
{
    public static function sendIndividualToOffice(
        Note $note,
        int $authorId,
        string $observation,
        ?string $lat = null,
        ?string $lng = null,
    ): NoteSalaObservation {
        return DB::transaction(function () use ($note, $authorId, $observation, $lat, $lng) {
            $now = now();

            $salaObservation = NoteSalaObservation::create([
                'note_id' => $note->id,
                'author_id' => $authorId,
                'observation' => $observation,
            ]);

            $updates = [
                'estado_terminal' => EstadoTerminal::SALA,
                'sent_to_sala_at' => $now,
                'fecha_declaracion' => $now,
                'printed' => false,
                'reten' => false,
            ];

            if (filled($lat) && filled($lng)) {
                $updates['lat'] = $lat;
                $updates['lng'] = $lng;
            }

            $note->forceFill($updates)->save();

            NoteSalaEvent::create([
                'note_id' => $note->id,
                'sent_by_user_id' => $authorId,
                'via' => 'declaracion',
                'sent_at' => $now,
                'lat' => filled($lat) ? $lat : null,
                'lng' => filled($lng) ? $lng : null,
            ]);

            DB::afterCommit(function () use ($note, $salaObservation) {
                event(new NotaEnviadaAOficina(
                    $note->fresh(['customer', 'comercial']),
                    $salaObservation->fresh()
                ));
            });

            return $salaObservation;
        });
    }

    /**
     * @param  array<int>  $eligibleNoteIds
     * @return array{enviadas: int}
     */
    public static function sendBulkToOffice(
        array $eligibleNoteIds,
        int $userId,
        ?string $lat = null,
        ?string $lng = null,
        bool $addMassObservation = true,
    ): array {
        if ($eligibleNoteIds === []) {
            return ['enviadas' => 0];
        }

        DB::transaction(function () use ($eligibleNoteIds, $userId, $lat, $lng, $addMassObservation) {
            $now = now();

            $noteUpdates = [
                'estado_terminal' => EstadoTerminal::SALA->value,
                'printed' => false,
                'reten' => false,
                'sent_to_sala_at' => $now,
                'fecha_declaracion' => $now,
            ];

            if (filled($lat) && filled($lng)) {
                $noteUpdates['lat'] = $lat;
                $noteUpdates['lng'] = $lng;
            }

            Note::whereIn('id', $eligibleNoteIds)->update($noteUpdates);

            $rows = [];
            foreach ($eligibleNoteIds as $noteId) {
                $rows[] = [
                    'note_id' => $noteId,
                    'sent_by_user_id' => $userId,
                    'via' => 'masivo',
                    'sent_at' => $now,
                    'lat' => filled($lat) ? $lat : null,
                    'lng' => filled($lng) ? $lng : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            NoteSalaEvent::insert($rows);

            if ($addMassObservation && count($eligibleNoteIds) >= 2) {
                $obsRows = [];
                foreach ($eligibleNoteIds as $noteId) {
                    $obsRows[] = [
                        'note_id' => $noteId,
                        'author_id' => $userId,
                        'observation' => 'Envío Masivo a sala',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                NoteSalaObservation::insert($obsRows);
            }

            DB::afterCommit(function () use ($eligibleNoteIds, $userId) {
                event(new NotasEnviadasAOficinaBulk(
                    $eligibleNoteIds,
                    User::find($userId) ?? auth()->user()
                ));
            });
        });

        return ['enviadas' => count($eligibleNoteIds)];
    }
}
