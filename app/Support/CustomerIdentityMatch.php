<?php

namespace App\Support;

/**
 * Compara el nombre y el DNI nuevos con otros clientes ya existentes.
 * No modifica fichas ni contratos.
 */
final class CustomerIdentityMatch
{
    /**
     * @param  array{first_names?: mixed, last_names?: mixed, dni?: mixed}  $despues
     * @param  list<array{id: int, first_names?: mixed, last_names?: mixed, dni?: mixed}>  $otros
     * @return list<array{id: int, nombre: string, dni: string, via: string}>
     */
    public static function buscar(array $despues, array $otros, int $excludeId): array
    {
        $dniNuevo = self::dni($despues['dni'] ?? null);
        $nombreNuevoCompleto = self::nombreCompleto($despues['first_names'] ?? null, $despues['last_names'] ?? null);
        $matches = [];

        foreach ($otros as $otro) {
            $id = (int) ($otro['id'] ?? 0);
            if ($id === 0 || $id === $excludeId) {
                continue;
            }

            $nombreOtro = self::nombreCompleto($otro['first_names'] ?? null, $otro['last_names'] ?? null);
            $dniOtro = self::dni($otro['dni'] ?? null);
            $porDni = $dniNuevo !== '' && $dniNuevo === $dniOtro;
            $porNombre = $nombreNuevoCompleto !== '' && $nombreNuevoCompleto === $nombreOtro;

            if (! $porDni && ! $porNombre) {
                continue;
            }

            $matches[] = [
                'id' => $id,
                'nombre' => trim((string) ($otro['first_names'] ?? '').' '.(string) ($otro['last_names'] ?? '')),
                'dni' => trim((string) ($otro['dni'] ?? '')),
                'via' => $porDni && $porNombre ? 'ambos' : ($porDni ? 'dni' : 'nombre'),
            ];
        }

        return $matches;
    }

    public static function nombreCompleto(mixed $first, mixed $last): string
    {
        $first = trim((string) $first);
        $last = trim((string) $last);

        if ($first === '' || $last === '') {
            return '';
        }

        return mb_strtolower($first).' '.mb_strtolower($last);
    }

    public static function dni(mixed $dni): string
    {
        return mb_strtoupper(trim((string) $dni));
    }
}
