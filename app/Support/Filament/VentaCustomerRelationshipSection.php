<?php

namespace App\Support\Filament;

/**
 * Ajustes para Section::relationship('customer') en formularios de contrato.
 *
 * Filament rellena la relación con attributesToArray() sin hooks de hidratación;
 * fecha_nac llega como ISO (p. ej. 1948-12-08T00:00:00.000000Z) y la máscara
 * dd/mm/aaaa del TextInput la corrompe al reabrir el contrato.
 */
class VentaCustomerRelationshipSection
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mutateDataBeforeFill(array $data): array
    {
        if (! array_key_exists('fecha_nac', $data)) {
            return $data;
        }

        $stored = FechaNacimientoField::normalizeStoredString($data['fecha_nac']);

        $data['fecha_nac'] = $stored !== null
            ? FechaNacimientoField::formatDisplay($stored, 'd/m/Y')
            : null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mutateDataBeforeSave(array $data): array
    {
        if (! array_key_exists('fecha_nac', $data)) {
            return $data;
        }

        $data['fecha_nac'] = FechaNacimientoField::normalizeForStorage($data['fecha_nac']);

        return $data;
    }
}
