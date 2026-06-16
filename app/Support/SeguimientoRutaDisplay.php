<?php

namespace App\Support;

use App\Models\Note;
use App\Models\NoteReassignmentLog;
use Illuminate\Support\Carbon;

class SeguimientoRutaDisplay
{
    /** Texto de anotación/observación que solo registra coordenadas GPS. */
    public static function isGpsLocationText(?string $text): bool
    {
        $text = trim((string) $text);
        if ($text === '') {
            return false;
        }

        if (preg_match('/^(Ubicación|Ubicacion)\s*(capturada|DENTRO|repartidor)/iu', $text)) {
            return true;
        }

        if (preg_match('/^\[\s*-?\d+[.,]?\d*\s*,\s*-?\d+[.,]?\d*\s*\]$/', $text)) {
            return true;
        }

        return (bool) preg_match('/^Latitud\s*-?\d+/iu', $text)
            && (bool) preg_match('/Longitud\s*-?\d+/iu', $text);
    }

    /** Quita coordenadas del texto visible; IR sigue usando gps_lat/gps_lng. */
    public static function sanitizeBody(?string $body): string
    {
        $body = trim((string) $body);

        if ($body === '' || self::isGpsLocationText($body)) {
            return '';
        }

        $body = preg_replace('/\s*\|\s*Latitud\s*-?\d+[.,]?\d*\s*,?\s*Longitud\s*-?\d+[.,]?\d*/iu', '', $body) ?? $body;
        $body = preg_replace('/\s*Latitud\s*-?\d+[.,]?\d*\s*,?\s*Longitud\s*-?\d+[.,]?\d*/iu', '', $body) ?? $body;
        $body = preg_replace('/\s*\[\s*-?\d+[.,]?\d*\s*,\s*-?\d+[.,]?\d*\s*\]/u', '', $body) ?? $body;

        return trim($body);
    }

    /** @return array{lat: string, lng: string}|null */
    public static function extractGpsFromText(?string $text): ?array
    {
        if (! preg_match('/Latitud\s*(-?\d+[.,]?\d*)\s*,?\s*Longitud\s*(-?\d+[.,]?\d*)/iu', trim((string) $text), $matches)) {
            return null;
        }

        return [
            'lat' => str_replace(',', '.', $matches[1]),
            'lng' => str_replace(',', '.', $matches[2]),
        ];
    }

    /** @return array{gps_lat: mixed, gps_lng: mixed} */
    public static function gpsCoordsForGpsText(?string $text, string $asunto, $note): array
    {
        $isDentro = strtoupper(trim($asunto)) === 'DENTRO'
            || preg_match('/DENTRO/iu', trim((string) $text));

        $lat = $isDentro ? ($note->lat_dentro ?? null) : ($note->lat ?? null);
        $lng = $isDentro ? ($note->lng_dentro ?? null) : ($note->lng ?? null);

        if (! filled($lat) || ! filled($lng)) {
            $parsed = self::extractGpsFromText($text);
            if ($parsed) {
                $lat = $parsed['lat'];
                $lng = $parsed['lng'];
            }
        }

        return [
            'gps_lat' => filled($lat) ? $lat : null,
            'gps_lng' => filled($lng) ? $lng : null,
        ];
    }

    public static function displayBody(?string $body, string $fallback = ''): string
    {
        $clean = self::sanitizeBody($body);

        return $clean !== '' ? $clean : $fallback;
    }

    public static function reassignmentLogForDate(Note $note, Carbon $date): ?NoteReassignmentLog
    {
        $logs = $note->relationLoaded('reassignmentLogs')
            ? ($note->getRelation('reassignmentLogs') ?? collect())
            : collect();

        return $logs
            ->filter(fn (NoteReassignmentLog $log) => $log->batch?->reassigned_at?->isSameDay($date))
            ->sortByDesc(fn (NoteReassignmentLog $log) => $log->batch?->reassigned_at?->timestamp ?? 0)
            ->first();
    }

    /** @return array{author_id: string, from_id: string, to_id: string, label: string, reassigned_at: Carbon}|null */
    public static function reassignmentBannerForDate(Note $note, Carbon $date): ?array
    {
        $log = self::reassignmentLogForDate($note, $date);
        if (! $log?->batch) {
            return null;
        }

        $batch = $log->batch;
        $authorId = $batch->author?->empleado_id ?? 'SIN-ID';
        $fromId = $log->fromComercial?->empleado_id ?? '—';
        $toId = $batch->to_reten
            ? 'RETÉN'
            : ($batch->toComercial?->empleado_id ?? '—');

        return [
            'author_id' => $authorId,
            'from_id' => $fromId,
            'to_id' => $toId,
            'label' => "Reasignada por: {$authorId} · Desde {$fromId} para com {$toId}",
            'reassigned_at' => $batch->reassigned_at,
        ];
    }
}
