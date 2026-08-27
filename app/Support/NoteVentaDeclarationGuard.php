<?php

namespace App\Support;

use App\Enums\EstadoTerminal;
use App\Models\Note;
use App\Models\Venta;

/**
 * Impide declaraciones de venta “de mentira”: TN en venta sin contrato,
 * o reutilizar una nota que ya tiene contratos activos.
 */
class NoteVentaDeclarationGuard
{
    /**
     * No se puede poner TN = venta con un clic de ciclo / cambio manual.
     * La venta solo se marca al grabar el contrato (CreateVenta / Puerta Fría).
     */
    public static function blockReasonForManualTerminalVenta(?Note $note = null): string
    {
        $nro = $note?->nro_nota ? " #{$note->nro_nota}" : '';

        return "No se puede marcar la nota{$nro} como VENTA desde aquí. "
            .'Eso no crea el contrato y aparece como falsa declaración en reportes. '
            .'Para registrar una venta usa el botón «Venta» (wizard) o «Puerta Fría».';
    }

    /**
     * ¿Se puede abrir el wizard de venta desde esta nota?
     *
     * @return string|null  Motivo de bloqueo, o null si se permite
     */
    public static function blockReasonForStartingVentaFromNote(Note $note): ?string
    {
        $contracts = self::activeContractsSummary($note);

        if ($contracts === []) {
            return null;
        }

        $list = implode(', ', $contracts);
        $nro = $note->nro_nota ?: $note->id;

        return "No puedes declarar una venta nueva desde la nota #{$nro}: "
            ."ya tiene contrato(s) activo(s) ({$list}). "
            .'Usa «Puerta Fría» para una venta nueva de este cliente, '
            .'o contacta a soporte si necesitas un contrato adicional ligado a esta nota.';
    }

    /**
     * @return list<string>
     */
    public static function activeContractsSummary(Note $note): array
    {
        return Venta::query()
            ->where('note_id', $note->id)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['nro_contr_adm', 'id'])
            ->map(function (Venta $venta): string {
                $nro = trim((string) ($venta->nro_contr_adm ?? ''));

                return $nro !== '' ? $nro : '#'.$venta->id;
            })
            ->values()
            ->all();
    }

    public static function wouldBecomeVenta(EstadoTerminal|string|null $next): bool
    {
        if ($next instanceof EstadoTerminal) {
            return $next === EstadoTerminal::VENTA;
        }

        return strtolower(trim((string) $next)) === EstadoTerminal::VENTA->value;
    }
}
