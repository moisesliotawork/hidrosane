<?php

namespace App\Support;

use App\Enums\EstadoTerminal;
use App\Enums\FuenteNotas;
use App\Enums\OrigenVenta;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Venta;

class VentaOrigenResolver
{
    /** Nota asignada por teleoperadora, aún sin venta (reutilizable al cerrar contrato). */
    public static function findReusableAssignedNote(Customer $customer): ?Note
    {
        return Note::query()
            ->where('customer_id', $customer->id)
            ->whereDoesntHave('venta')
            ->whereNotNull('assignment_date')
            ->where(function ($query) {
                $query
                    ->where('fuente', '!=', FuenteNotas::PTA_FRIA->value)
                    // Nota teleoperadora mal etiquetada como PtaFria (creador ≠ comercial asignado)
                    ->orWhere(function ($q) {
                        $q->whereNotNull('user_id')
                            ->whereNotNull('comercial_id')
                            ->whereColumn('user_id', '!=', 'comercial_id');
                    });
            })
            ->where(function ($query) {
                $query
                    ->whereNull('estado_terminal')
                    ->orWhere('estado_terminal', '')
                    ->orWhereRaw('LOWER(TRIM(estado_terminal)) = ?', [EstadoTerminal::AUSENTE->value]);
            })
            ->orderByDesc('assignment_date')
            ->first();
    }

    public static function origenForCreateFromNote(Note $note): OrigenVenta
    {
        if ($note->fuente === FuenteNotas::PTA_FRIA) {
            return OrigenVenta::PUERTA_FRIA;
        }

        return OrigenVenta::VENTA_NORMAL;
    }

    /** Fuente visible en listados de contratos (teleoperadora vs puerta fría). */
    public static function fuenteDisplayForVenta(Venta $venta): FuenteNotas
    {
        $venta->loadMissing('note');

        if ($venta->origen_venta === OrigenVenta::PUERTA_FRIA) {
            return FuenteNotas::PTA_FRIA;
        }

        if ($venta->origen_venta === OrigenVenta::EXCEL) {
            return FuenteNotas::EXCEL;
        }

        $noteFuente = $venta->note?->fuente;

        if ($noteFuente instanceof FuenteNotas && $noteFuente !== FuenteNotas::PTA_FRIA) {
            return $noteFuente;
        }

        if ($venta->origen_venta === OrigenVenta::VENTA_NORMAL) {
            // origen_venta manda: no mostrar Puerta Fría si la venta es desde nota asignada
            return self::inferTeleoperatorFuente($venta->note) ?? FuenteNotas::CALLE;
        }

        return FuenteNotas::PTA_FRIA;
    }

    /** Nota teleoperadora con fuente mal guardada como PtaFria. */
    public static function inferTeleoperatorFuente(?Note $note): ?FuenteNotas
    {
        if (! $note) {
            return null;
        }

        $note->loadMissing('user');

        if ($note->fuente instanceof FuenteNotas && $note->fuente !== FuenteNotas::PTA_FRIA) {
            return $note->fuente;
        }

        $fromObservations = self::inferFuenteFromObservations($note);

        if ($fromObservations) {
            return $fromObservations;
        }

        // Creada por teleoperadora/jefa de sala y asignada a comercial distinto → no es puerta fría real
        if ($note->user_id && $note->comercial_id && (int) $note->user_id !== (int) $note->comercial_id) {
            return FuenteNotas::CALLE;
        }

        return null;
    }

    /** Busca VIP en observaciones (p. ej. "IR CON ITABLE - VIP EXTERNO"). */
    public static function inferFuenteFromObservations(Note $note): ?FuenteNotas
    {
        foreach ($note->observations()->orderBy('created_at')->get() as $observation) {
            $inferred = self::inferFuenteFromText((string) ($observation->observation ?? ''));

            if ($inferred) {
                return $inferred;
            }
        }

        $legacy = $note->getAttributes()['observations'] ?? null;

        if (is_string($legacy)) {
            $legacy = json_decode($legacy, true);
        }

        if (is_array($legacy)) {
            foreach ($legacy as $row) {
                $text = is_array($row) ? (string) ($row['observation'] ?? '') : (string) $row;
                $inferred = self::inferFuenteFromText($text);

                if ($inferred) {
                    return $inferred;
                }
            }
        }

        return null;
    }

    private static function inferFuenteFromText(string $text): ?FuenteNotas
    {
        $normalized = strtoupper(trim($text));

        if ($normalized === '') {
            return null;
        }

        if (
            str_contains($normalized, 'VIP EXT')
            || str_contains($normalized, 'VIP-EXT')
            || str_contains($normalized, 'VIP EXTERNO')
        ) {
            return FuenteNotas::VIP_EXT;
        }

        if (
            str_contains($normalized, 'VIP INT')
            || str_contains($normalized, 'VIP-INT')
            || str_contains($normalized, 'VIP INTERNO')
        ) {
            return FuenteNotas::VIP_INT;
        }

        return null;
    }

    /** Corrige fuente PtaFria errónea en notas teleoperadoras al cerrar venta normal. */
    public static function repairMislabeledFuente(Note $note): void
    {
        if ($note->fuente !== FuenteNotas::PTA_FRIA) {
            return;
        }

        $inferred = self::inferTeleoperatorFuente($note);

        if ($inferred && $inferred !== FuenteNotas::PTA_FRIA) {
            $note->update(['fuente' => $inferred->value]);
        }
    }
}
