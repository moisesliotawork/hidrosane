<?php

namespace App\Support\Storage;

/**
 * Copia local de un documento, utilizable con funciones que exigen una ruta
 * física real (Imagick, pdftoppm, gs, maatwebsite/excel, cURL multipart…).
 *
 * Si el documento ya vivía en un disco local, se devuelve su ruta real y NO se
 * borra nada al liberar. Si venía de un disco remoto, se descarga a un temporal
 * que se elimina solo al destruirse el objeto.
 *
 * Uso típico:
 *
 *     $copy = DocumentStorage::localCopy($venta->dni_anverso);
 *     if ($copy === null) { ... }
 *     $texto = ocr($copy->path());
 *     // el temporal se limpia cuando $copy sale de ámbito
 */
final class LocalCopy
{
    private bool $released = false;

    public function __construct(
        private readonly string $path,
        private readonly bool $temporary,
    ) {
    }

    /**
     * Envuelve una ruta local ya existente: no se borra al liberar.
     */
    public static function existing(string $path): self
    {
        return new self($path, temporary: false);
    }

    /**
     * Materializa un contenido remoto en un temporal que sí se borra al liberar.
     */
    public static function temporary(string $contents, string $extension = ''): self
    {
        $suffix = $extension !== '' ? '.'.ltrim($extension, '.') : '';
        $path = tempnam(sys_get_temp_dir(), 'ohana-doc-');

        if ($path === false) {
            throw new \RuntimeException('No se pudo crear un fichero temporal para el documento.');
        }

        // tempnam() no admite sufijo; renombramos para conservar la extensión,
        // de la que dependen mime_content_type() y varios binarios externos.
        if ($suffix !== '') {
            $withExt = $path.$suffix;
            if (@rename($path, $withExt)) {
                $path = $withExt;
            }
        }

        file_put_contents($path, $contents);

        return new self($path, temporary: true);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isTemporary(): bool
    {
        return $this->temporary;
    }

    /**
     * Borra el temporal si procede. Idempotente.
     */
    public function release(): void
    {
        if ($this->released) {
            return;
        }

        $this->released = true;

        if ($this->temporary && is_file($this->path)) {
            @unlink($this->path);
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
