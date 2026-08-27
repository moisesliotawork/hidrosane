<?php

namespace App\Support;

use App\Models\AnotacionVisita;
use App\Models\Note;

class NoteRouteGps
{
    public static function appendCoordsToText(string $text, ?string $lat, ?string $lng): string
    {
        if (! filled($lat) || ! filled($lng)) {
            return $text;
        }

        if (preg_match('/Latitud\s*-?\d+/iu', $text) && preg_match('/Longitud\s*-?\d+/iu', $text)) {
            return $text;
        }

        $text = trim($text);

        return $text === '' ? "Latitud {$lat}, Longitud {$lng}" : "{$text} | Latitud {$lat}, Longitud {$lng}";
    }

    public static function ausenteCuerpo(?string $observacion, ?string $lat, ?string $lng): string
    {
        $base = filled(trim((string) $observacion)) ? trim($observacion) : 'Marcado como AUSENTE';

        return self::appendCoordsToText($base, $lat, $lng);
    }

    public static function deCaminoCuerpo(bool $enCamino, ?string $lat, ?string $lng): string
    {
        if (! $enCamino) {
            return 'No va de camino';
        }

        return self::appendCoordsToText('Va de camino', $lat, $lng);
    }

    /**
     * Marca o desmarca DE CAMINO. Al activar, exige coordenadas GPS.
     *
     * @return bool false si se intentó activar sin GPS
     */
    public static function toggleDeCamino(Note $note, int $authorId, ?string $lat = null, ?string $lng = null): bool
    {
        $enCamino = ! $note->de_camino;

        if ($enCamino && (! filled($lat) || ! filled($lng))) {
            return false;
        }

        $note->de_camino = $enCamino;

        if ($enCamino) {
            $coords = ActionGps::validateOperatingCoords($lat, $lng);

            if ($coords === null) {
                return false;
            }

            $note->lat = $coords['lat'];
            $note->lng = $coords['lng'];
            $lat = $coords['lat'];
            $lng = $coords['lng'];
        }

        $note->save();

        AnotacionVisita::create([
            'nota_id' => $note->id,
            'author_id' => $authorId,
            'asunto' => 'DE CAMINO',
            'cuerpo' => self::deCaminoCuerpo($enCamino, $lat, $lng),
        ]);

        return true;
    }
}
