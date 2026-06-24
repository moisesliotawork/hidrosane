<?php

namespace App\Support;

use App\Models\Note;
use App\Models\NoteReassignmentLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SeguimientoRutaDisplay
{
    /** Texto de anotación/observación que solo registra coordenadas GPS. */
    public static function isGpsLocationText(?string $text): bool
    {
        $text = trim((string) $text);
        if ($text === '') {
            return false;
        }

        if (preg_match('/^(Ubicación|Ubicacion)\s*(capturada|DENTRO|DE CAMINO|repartidor)/iu', $text)) {
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

    /** @return array{gps_lat: mixed, gps_lng: mixed} */
    public static function gpsCoordsForSala(mixed $event, Note $note): array
    {
        $lat = $event->lat ?? null;
        $lng = $event->lng ?? null;

        if (! filled($lat) || ! filled($lng)) {
            $lat = $note->lat ?? null;
            $lng = $note->lng ?? null;
        }

        return [
            'gps_lat' => filled($lat) ? $lat : null,
            'gps_lng' => filled($lng) ? $lng : null,
        ];
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

    /** @return array{gps_lat: mixed, gps_lng: mixed} */
    public static function gpsCoordsForAnotacion(string $asunto, ?string $cuerpo, Note $note): array
    {
        $asunto = strtoupper(trim($asunto));
        $cuerpo = trim((string) $cuerpo);

        if ($asunto === 'DE CAMINO') {
            if (preg_match('/no\s+va\s+de\s+camino/iu', $cuerpo)) {
                return ['gps_lat' => null, 'gps_lng' => null];
            }

            $parsed = self::extractGpsFromText($cuerpo);
            if ($parsed) {
                return ['gps_lat' => $parsed['lat'], 'gps_lng' => $parsed['lng']];
            }

            return self::gpsCoordsForGpsText($cuerpo, $asunto, $note);
        }

        if (self::isGpsLocationText($cuerpo)) {
            return self::gpsCoordsForGpsText($cuerpo, $asunto, $note);
        }

        return ['gps_lat' => null, 'gps_lng' => null];
    }

    /** @return array{gps_lat: mixed, gps_lng: mixed} */
    public static function gpsCoordsForAusente(?string $observacion, ?string $cuerpo, Note $note, mixed $latitud = null, mixed $longitud = null): array
    {
        $lat = filled($latitud) ? $latitud : null;
        $lng = filled($longitud) ? $longitud : null;

        if (! filled($lat) || ! filled($lng)) {
            $parsed = self::extractGpsFromText($cuerpo ?? $observacion);
            if ($parsed) {
                $lat = $parsed['lat'];
                $lng = $parsed['lng'];
            }
        }

        if (! filled($lat) || ! filled($lng)) {
            $lat = $note->lat_dentro ?? null;
            $lng = $note->lng_dentro ?? null;
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

    public static function displayAnotacionBody(?string $asunto, ?string $cuerpo, string $fallback = 'Sin contenido'): string
    {
        $asuntoNorm = strtoupper(trim((string) $asunto));
        $cuerpo = trim((string) $cuerpo);

        if ($asuntoNorm === 'DE CAMINO' && ! preg_match('/no\s+va\s+de\s+camino/iu', $cuerpo)) {
            return 'Comercial:';
        }

        if (self::isGpsLocationText($cuerpo) && $asuntoNorm !== 'DE CAMINO') {
            return '';
        }

        return self::displayBody($cuerpo, $fallback);
    }

    public static function oficinaActivitiesForNote(Note $note, Carbon $date): Collection
    {
        $events = $note->relationLoaded('salaEvents')
            ? ($note->getRelation('salaEvents') ?? collect())
            : $note->salaEvents()->with('sentBy')->get();

        $observations = $note->relationLoaded('salaObservations')
            ? ($note->getRelation('salaObservations') ?? collect())
            : collect();

        $mapped = $events
            ->filter(fn ($event) => ($event->sent_at ?? $event->created_at)?->isSameDay($date))
            ->map(function ($event) use ($note, $observations) {
                $at = $event->sent_at ?? $event->created_at;
                $obs = $observations->first(
                    fn ($o) => $o->created_at && $at && abs($o->created_at->diffInSeconds($at)) <= 10
                ) ?? $observations->first(fn ($o) => $o->created_at?->isSameDay($at));

                $gps = self::gpsCoordsForSala($event, $note);

                return [
                    'type' => 'oficina',
                    'created_at' => $at,
                    'topic' => 'ENVIADA A OFICINA',
                    'body' => self::displayBody($obs?->observation, 'Enviada a oficina'),
                    'author' => $event->sentBy?->display_name ?? $event->sentBy?->full_name ?? '—',
                    'meta_label' => 'Enviada a oficina',
                    'gps_lat' => $gps['gps_lat'],
                    'gps_lng' => $gps['gps_lng'],
                ];
            });

        if ($mapped->isNotEmpty()) {
            return $mapped->values();
        }

        if (strtolower(trim((string) $note->getRawOriginal('estado_terminal'))) !== 'sala'
            || ! $note->sent_to_sala_at?->isSameDay($date)) {
            return collect();
        }

        $obs = $observations->first(fn ($o) => $o->created_at?->isSameDay($date));
        $gps = self::gpsCoordsForSala((object) ['lat' => null, 'lng' => null], $note);

        return collect([[
            'type' => 'oficina',
            'created_at' => $note->sent_to_sala_at,
            'topic' => 'ENVIADA A OFICINA',
            'body' => self::displayBody($obs?->observation, 'Enviada a oficina'),
            'author' => $obs?->author?->display_name ?? $obs?->author?->full_name ?? '—',
            'meta_label' => 'Enviada a oficina',
            'gps_lat' => $gps['gps_lat'],
            'gps_lng' => $gps['gps_lng'],
        ]]);
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
