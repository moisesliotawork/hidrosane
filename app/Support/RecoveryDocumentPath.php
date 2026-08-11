<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Resuelve rutas de documentos de recuperación.
 *
 * El disco "local" apunta a storage/app/private, pero lotes antiguos / scripts
 * guardaron ficheros en storage/app/recovery/... Hay que mirar ambos sitios.
 */
class RecoveryDocumentPath
{
    public static function absolute(string $relativePath): ?string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        if ($relativePath === '') {
            return null;
        }

        if (
            str_starts_with($relativePath, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $relativePath)
        ) {
            return is_file($relativePath) ? $relativePath : null;
        }

        foreach (['local', 'public'] as $disk) {
            $full = Storage::disk($disk)->path($relativePath);
            if (is_file($full)) {
                return $full;
            }
        }

        $legacy = storage_path('app/'.$relativePath);
        if (is_file($legacy)) {
            return $legacy;
        }

        return null;
    }

    public static function exists(string $relativePath): bool
    {
        return self::absolute($relativePath) !== null;
    }

    public static function get(string $relativePath): ?string
    {
        $absolute = self::absolute($relativePath);
        if ($absolute === null) {
            return null;
        }

        $contents = @file_get_contents($absolute);

        return $contents === false ? null : $contents;
    }
}
