<?php

namespace App\Support\Storage;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Punto único de acceso a los documentos de cliente (DNIs, nóminas, pensiones,
 * contratos firmados, precontractuales, fotos de sorteo…).
 *
 * Existe para que el resto de la app no sepa —ni le importe— si los ficheros
 * están en storage/app/public o en DigitalOcean Spaces. El disco se elige en
 * config('filesystems.documents').
 *
 * Durante la migración se puede definir config('filesystems.documents_fallback')
 * para que un documento que aún no esté en el disco nuevo se sirva desde el
 * viejo, en vez de romperse.
 */
final class DocumentStorage
{
    public static function diskName(): string
    {
        return (string) config('filesystems.documents', 'public');
    }

    public static function fallbackDiskName(): ?string
    {
        $fallback = config('filesystems.documents_fallback');

        if (! is_string($fallback) || $fallback === '' || $fallback === self::diskName()) {
            return null;
        }

        return $fallback;
    }

    public static function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter */
        return Storage::disk(self::diskName());
    }

    public static function driverFor(?string $disk = null): ?string
    {
        $driver = config('filesystems.disks.'.($disk ?? self::diskName()).'.driver');

        return is_string($driver) ? $driver : null;
    }

    /**
     * Visibilidad con la que deben escribir los FileUpload de Filament.
     *
     * En un disco remoto tiene que ser 'private': el default de Filament es
     * 'public' y escribiría cada documento en Spaces con ACL public-read,
     * accesible desde internet por URL directa. Además Filament sólo genera
     * temporaryUrl() firmada cuando la visibilidad es 'private'.
     *
     * En el disco local histórico se mantiene 'public', porque ahí las URLs
     * salen del symlink public/storage y no hay temporaryUrl().
     */
    public static function uploadVisibility(): string
    {
        return self::driverFor() === 's3' ? 'private' : 'public';
    }

    /**
     * Normaliza las rutas que guarda la BD.
     *
     * El histórico trae variantes: con barra inicial, con prefijo 'public/'
     * (herencia de storage/app/public) y con separadores de Windows en algún
     * import antiguo. Todo eso apunta al mismo objeto.
     */
    public static function normalize(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');
        $path = preg_replace('#^public/#', '', $path) ?? $path;

        return $path === '' ? null : $path;
    }

    /**
     * Localiza en qué disco vive realmente un documento.
     *
     * @return array{disk: FilesystemAdapter, name: string, path: string}|null
     */
    public static function locate(?string $path): ?array
    {
        $path = self::normalize($path);

        if ($path === null) {
            return null;
        }

        foreach (array_filter([self::diskName(), self::fallbackDiskName()]) as $name) {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk($name);

            try {
                if ($disk->exists($path)) {
                    return ['disk' => $disk, 'name' => $name, 'path' => $path];
                }
            } catch (\Throwable $e) {
                // Un disco remoto caído no debe tumbar la petición: se intenta
                // el siguiente y, si no hay más, el llamante recibe null.
                report($e);
            }
        }

        return null;
    }

    public static function exists(?string $path): bool
    {
        return self::locate($path) !== null;
    }

    /**
     * URL para mostrar el documento al usuario.
     *
     * En discos remotos privados devuelve una URL firmada de vida corta, para
     * que un enlace filtrado deje de servir en minutos. En discos locales cae
     * en la URL pública de siempre.
     */
    public static function url(?string $path): ?string
    {
        $found = self::locate($path);

        if ($found === null) {
            return null;
        }

        if (self::driverFor($found['name']) === 's3') {
            $ttl = max(1, (int) config('filesystems.documents_url_ttl', 15));

            try {
                return $found['disk']->temporaryUrl($found['path'], now()->addMinutes($ttl));
            } catch (\Throwable $e) {
                report($e);

                return null;
            }
        }

        return $found['disk']->url($found['path']);
    }

    public static function get(?string $path): ?string
    {
        $found = self::locate($path);

        if ($found === null) {
            return null;
        }

        try {
            return $found['disk']->get($found['path']);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Ruta física local para pasar a binarios externos o librerías que no
     * aceptan streams. Descarga a un temporal si el documento es remoto.
     */
    public static function localCopy(?string $path): ?LocalCopy
    {
        $found = self::locate($path);

        if ($found === null) {
            return null;
        }

        if (self::driverFor($found['name']) === 'local') {
            return LocalCopy::existing($found['disk']->path($found['path']));
        }

        $contents = self::get($found['path']);

        if ($contents === null) {
            return null;
        }

        return LocalCopy::temporary($contents, pathinfo($found['path'], PATHINFO_EXTENSION));
    }

    /**
     * Escribe siempre en el disco principal: el fallback es sólo de lectura.
     */
    public static function put(string $path, mixed $contents): bool
    {
        $normalized = self::normalize($path);

        if ($normalized === null) {
            return false;
        }

        return (bool) self::disk()->put($normalized, $contents);
    }

    /**
     * Borra en todos los discos configurados, para no dejar copias del
     * documento de un cliente atrás tras una eliminación.
     */
    public static function delete(?string $path): bool
    {
        $normalized = self::normalize($path);

        if ($normalized === null) {
            return false;
        }

        $deleted = false;

        foreach (array_filter([self::diskName(), self::fallbackDiskName()]) as $name) {
            try {
                $disk = Storage::disk($name);

                if ($disk->exists($normalized)) {
                    $deleted = $disk->delete($normalized) || $deleted;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $deleted;
    }

    /**
     * Lista recursiva de ficheros bajo un prefijo. Sustituye a los
     * RecursiveDirectoryIterator, que sólo funcionan en discos locales.
     *
     * @return list<string>
     */
    public static function allFiles(string $directory = ''): array
    {
        $prefix = self::normalize($directory);

        try {
            return self::disk()->allFiles($prefix ?? '');
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }
}
