<?php

namespace App\Support;

use App\Models\Venta;

class VentaTelegramDocuments
{
    /**
     * Etiquetas usadas en avisos Telegram de venta.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'precontractual' => 'Precontractual',
            'foto_sorteo' => 'Foto sorteo',
            'dni_anverso' => 'DNI – Anverso',
            'dni_reverso' => 'DNI – Reverso',
            'documento_titularidad' => 'Documento de titularidad',
            'nomina' => 'Nómina',
            'pension' => 'Pensión',
            'contrato_firmado' => 'Contrato Firmado',
            'otros_documentos' => 'Otros Documentos',
        ];
    }

    /**
     * @return list<string>
     */
    public static function fields(): array
    {
        return array_keys(self::labels());
    }

    public static function fieldHasFile(mixed $value): bool
    {
        if (is_array($value)) {
            return collect($value)->contains(fn ($item) => filled($item));
        }

        return filled($value);
    }

    /**
     * @return list<string> etiquetas de documentos presentes
     */
    public static function uploadedLabels(Venta $venta): array
    {
        $subidos = [];

        foreach (self::labels() as $field => $label) {
            if (self::fieldHasFile($venta->getAttribute($field))) {
                $subidos[] = $label;
            }
        }

        return $subidos;
    }
}
