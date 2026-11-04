<?php

namespace App\Support\Filament;

use App\Models\User;
use App\Support\ContractsCommercialUser;
use App\Support\Storage\DocumentStorage;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\View;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class VentaDocumentUpload
{
    /**
     * Campos de documento del wizard comercial de creación (venta / Puerta Fría).
     * Debe coincidir con VentaResource / VentaDesdeCeroResource step documentos
     * y con CreateVenta::fileFields().
     *
     * @return list<string>
     */
    public static function creationFormDocumentFields(): array
    {
        return [
            'precontractual',
            'foto_sorteo',
            'dni_anverso',
            'dni_reverso',
            'documento_titularidad',
            'nomina',
            'pension',
            'otros_documentos',
        ];
    }

    /**
     * Slots de documento a re-enganchar (formulario de creación + contrato firmado
     * del panel Admin / recuperación por imagen).
     *
     * @return list<string>
     */
    public static function recoveryDocumentSlots(): array
    {
        return array_values(array_unique(array_merge(
            self::creationFormDocumentFields(),
            ['contrato_firmado'],
        )));
    }

    /**
     * Sufijos de fichero en disco que mapean a un slot de venta.
     * p.ej. *_albaran.* → columna precontractual (otros paneles).
     *
     * @return array<string, string> filename_token => venta_column
     */
    public static function filenameFieldAliases(): array
    {
        return [
            'albaran' => 'precontractual',
        ];
    }

    /**
     * Tokens reconocibles en el nombre de fichero (slots + aliases),
     * ordenados de más largo a más corto para el regex.
     *
     * @return list<string>
     */
    public static function filenameFieldTokens(): array
    {
        $tokens = array_values(array_unique(array_merge(
            self::recoveryDocumentSlots(),
            array_keys(self::filenameFieldAliases()),
        )));

        usort($tokens, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $tokens;
    }

    public static function resolveFilenameFieldToSlot(string $token): string
    {
        $token = mb_strtolower(trim($token));
        $aliases = self::filenameFieldAliases();

        return $aliases[$token] ?? $token;
    }

    /**
     * Etiquetas cortas para Excel / CLI.
     *
     * @return array<string, string>
     */
    public static function documentFieldLabels(): array
    {
        return [
            'precontractual' => 'Precontractual',
            'foto_sorteo' => 'Foto sorteo',
            'dni_anverso' => 'DNI anverso',
            'dni_reverso' => 'DNI reverso',
            'documento_titularidad' => 'Titularidad',
            'nomina' => 'Nómina',
            'pension' => 'Pensión',
            'otros_documentos' => 'Otros docs',
            'contrato_firmado' => 'Contrato firmado',
        ];
    }

    /**
     * Etiquetas cortas para badges de atajos / listado.
     *
     * @return array<string, string>
     */
    public static function documentFieldShortLabels(): array
    {
        return [
            'precontractual' => 'Pre',
            'foto_sorteo' => 'Foto',
            'dni_anverso' => 'DNI-A',
            'dni_reverso' => 'DNI-R',
            'documento_titularidad' => 'Tit',
            'nomina' => 'Nóm',
            'pension' => 'Pen',
            'otros_documentos' => 'Otros',
            'contrato_firmado' => 'Firm',
        ];
    }

    /**
     * Badges de atajo a cada slot: color si hay archivo (abre preview); gris sin click si vacío.
     *
     * @param  bool  $stack  true = columna vertical (tabla); false = fila horizontal (formulario / listado).
     * @param  bool  $compact  true = badges más pequeños (2ª fila del listado Docs).
     */
    public static function atajosBadgesHtml(?\App\Models\Venta $record, bool $stack = false, bool $compact = false): \Illuminate\Support\HtmlString
    {
        $short = self::documentFieldShortLabels();
        $labels = self::documentFieldLabels();

        $filledStyles = [
            'precontractual' => 'background:#16a34a;color:#ffffff;border:1px solid #15803d;',
            'foto_sorteo' => 'background:#d97706;color:#ffffff;border:1px solid #b45309;',
            'dni_anverso' => 'background:#0891b2;color:#ffffff;border:1px solid #0e7490;',
            'dni_reverso' => 'background:#0891b2;color:#ffffff;border:1px solid #0e7490;',
            'documento_titularidad' => 'background:#7c3aed;color:#ffffff;border:1px solid #6d28d9;',
            'nomina' => 'background:#65a30d;color:#ffffff;border:1px solid #4d7c0f;',
            'pension' => 'background:#e11d48;color:#ffffff;border:1px solid #be123c;',
            'otros_documentos' => 'background:#ea580c;color:#ffffff;border:1px solid #c2410c;',
            'contrato_firmado' => 'background:#4f46e5;color:#ffffff;border:1px solid #4338ca;',
        ];

        $emptyStyle = 'background:#e5e7eb;color:#6b7280;border:1px solid #9ca3af;';
        $baseBox = $compact
            ? 'display:inline-flex;align-items:center;justify-content:center;'
                .'border-radius:0.25rem;padding:0.05rem 0.3rem;font-size:0.625rem;font-weight:600;'
                .'line-height:1.15;text-decoration:none;white-space:nowrap;'
            : 'display:inline-flex;align-items:center;justify-content:center;'
                .'border-radius:0.375rem;padding:0.25rem 0.625rem;font-size:0.75rem;font-weight:600;'
                .'line-height:1.25;text-decoration:none;white-space:nowrap;';

        $badges = [];

        foreach (self::recoveryDocumentSlots() as $field) {
            $label = $short[$field] ?? ($labels[$field] ?? $field);
            $fullLabel = $labels[$field] ?? $field;
            $path = ($record && filled($record->{$field})) ? (string) $record->{$field} : null;
            $has = filled($path);
            $url = $has ? self::browserPreviewUrl($path) : null;

            if ($has && filled($url)) {
                $style = ($filledStyles[$field] ?? $filledStyles['precontractual']).$baseBox;
                $badges[] = sprintf(
                    '<a href="%s" target="_blank" rel="noopener noreferrer" title="%s" style="%s">%s</a>',
                    e($url),
                    e($fullLabel),
                    e($style),
                    e($label),
                );
            } else {
                $badges[] = sprintf(
                    '<span style="%s pointer-events:none;user-select:none;" aria-disabled="true">%s</span>',
                    e($emptyStyle.$baseBox),
                    e($label),
                );
            }
        }

        $wrapperClass = $stack
            ? 'flex flex-col items-start gap-1'
            : ($compact
                ? 'flex w-full flex-nowrap items-center justify-start gap-1 whitespace-nowrap'
                : 'flex flex-nowrap items-center justify-start gap-1.5 whitespace-nowrap');

        return new \Illuminate\Support\HtmlString(
            '<div class="'.$wrapperClass.'" style="justify-content:flex-start;text-align:left;">'.implode('', $badges).'</div>'
        );
    }

    /**
     * Tope de subida (KB) alineado con Livewire / recomendación nginx-php (≥25 MB).
     * iPhone HEIC suele ir bien; ProRAW/DNG (~20 MB) se rechaza por extensión, no por tope.
     */
    public const MAX_UPLOAD_KILOBYTES = 25600;

    /**
     * Extensiones de imagen aceptadas (validación por nombre: iOS a veces manda mime vacío/octet-stream).
     *
     * @return list<string>
     */
    public static function acceptedImageExtensions(): array
    {
        return ['png', 'jpg', 'jpeg', 'webp', 'heic', 'heif'];
    }

    /**
     * RAW / negativos digitales: iOS los ofrece si ProRAW está activo, incluso desde “Hacer foto”.
     *
     * @return list<string>
     */
    public static function rejectedRawExtensions(): array
    {
        return ['dng', 'raw', 'cr2', 'nef', 'arw', 'orf', 'rw2'];
    }

    /**
     * Formatos de imagen permitidos en documentos de venta (incl. PNG y HEIC iPhone).
     *
     * @return array<int, string>
     */
    public static function acceptedImageMimeTypes(): array
    {
        return [
            'image/png',
            'image/x-png',
            'image/jpeg',
            'image/jpg',
            'image/pjpeg',
            'image/webp',
            'image/heic',
            'image/heif',
            // iOS/Safari a veces no reporta mime real en la subida Livewire.
            'application/octet-stream',
        ];
    }

    public static function extensionOf(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: '');
    }

    /**
     * Mensaje de rechazo para FilePond / validación (español comercial).
     */
    public static function uploadRejectionMessage(string $filename, ?int $sizeBytes = null, bool $allowPdf = false): ?string
    {
        $ext = self::extensionOf($filename);

        if ($ext !== '' && in_array($ext, self::rejectedRawExtensions(), true)) {
            return 'No se puede subir foto RAW/ProRAW (.'.$ext.'). En el iPhone: Ajustes → Cámara → Formatos → desactiva ProRAW, o elige JPEG/HEIC.';
        }

        $allowed = self::acceptedImageExtensions();
        if ($allowPdf) {
            $allowed[] = 'pdf';
        }

        if ($ext !== '' && ! in_array($ext, $allowed, true)) {
            return 'Formato no permitido (.'.$ext.'). Usa JPEG, HEIC, PNG'.($allowPdf ? ' o PDF' : '').'.';
        }

        if ($sizeBytes !== null && $sizeBytes > self::MAX_UPLOAD_KILOBYTES * 1024) {
            $mb = round($sizeBytes / 1048576, 1);

            return "Archivo demasiado grande ({$mb} MB). Máximo ".(int) (self::MAX_UPLOAD_KILOBYTES / 1024).' MB. Prueba otra foto o baja la calidad.';
        }

        return null;
    }

    public static function rawFormatRejectionMessage(): string
    {
        return 'No uses fotos RAW / ProRAW (.dng). En el iPhone elige una foto normal (JPEG o HEIC) '
            .'desde la fototeca, o cambia Ajustes → Cámara → Formatos → Compatible.';
    }

    public static function looksLikeRawPhoto(mixed $file): bool
    {
        if ($file instanceof TemporaryUploadedFile) {
            $name = strtolower($file->getClientOriginalName() ?: '');
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
            $mime = strtolower((string) $file->getMimeType());

            return in_array($ext, self::rejectedRawExtensions(), true)
                || str_ends_with($name, '.dng')
                || str_contains($mime, 'image/x-adobe-dng')
                || str_contains($mime, 'image/dng');
        }

        if (is_string($file) && $file !== '') {
            return in_array(self::extensionOf($file), self::rejectedRawExtensions(), true);
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public static function acceptedPdfMimeTypes(): array
    {
        return [
            'application/pdf',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function acceptedDocumentMimeTypes(bool $allowPdf = false): array
    {
        $types = self::acceptedImageMimeTypes();

        if ($allowPdf) {
            $types = array_merge($types, self::acceptedPdfMimeTypes());
        }

        return $types;
    }

    public static function fieldsAllowingPdf(): array
    {
        return [
            'precontractual',
            'foto_sorteo',
            'contrato_firmado',
            'documento_titularidad',
            'nomina',
            'pension',
            'otros_documentos',
            'albaran',
        ];
    }

    public static function fieldAllowsPdf(string $field, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (ContractsCommercialUser::matches($user)) {
            return true;
        }

        // Admin / Gerente / SuperAdmin adjuntan PDF desde escritorio (nóminas, contratos, etc.).
        if (self::isOfficeContractsPanel()) {
            return true;
        }

        return in_array($field, self::fieldsAllowingPdf(), true);
    }

    /**
     * Paneles de oficina: no forzar cámara (rompe el selector de archivos en PC).
     */
    public static function isOfficeContractsPanel(): bool
    {
        $panelId = filament()->getCurrentPanel()?->getId();

        return in_array($panelId, ['admin', 'gerente', 'superAdmin'], true);
    }

    /**
     * Título del slot + FileUpload FilePond nativo (cuadrado, ver / descargar / borrar).
     */
    public static function card(string $field, string $label, FileUpload $upload): Group
    {
        return Group::make([
            View::make('filament.forms.venta-document-large-preview')
                ->viewData(['label' => $label]),
            $upload->hiddenLabel(),
        ])->columns(1);
    }

    public static function resolveDocumentPath(mixed $state, mixed $record = null, string $field = ''): ?string
    {
        if (is_array($state)) {
            $state = $state[0] ?? null;
        }

        if ($state instanceof TemporaryUploadedFile) {
            return null;
        }

        $path = is_string($state) && $state !== ''
            ? $state
            : (is_object($record) ? ($record->{$field} ?? null) : null);

        if (! is_string($path) || $path === '') {
            return null;
        }

        return DocumentStorage::normalize($path) ?? $path;
    }

    public static function imagePreviewUrl(mixed $state, mixed $record = null, string $field = ''): ?string
    {
        if (is_array($state)) {
            $state = $state[0] ?? null;
        }

        if ($state instanceof TemporaryUploadedFile) {
            $mime = (string) $state->getMimeType();
            if (! str_starts_with($mime, 'image/')) {
                return null;
            }

            try {
                return $state->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        $path = self::resolveDocumentPath($state, $record, $field);
        if ($path === null) {
            return null;
        }

        $mime = self::mimeFromPath($path);
        if ($mime === null || ! str_starts_with($mime, 'image/')) {
            return null;
        }

        return self::browserPreviewUrl($path);
    }

    public static function stateLooksLikeImage(mixed $state): bool
    {
        if (is_array($state)) {
            $state = $state[0] ?? null;
        }

        if ($state instanceof TemporaryUploadedFile) {
            return str_starts_with((string) $state->getMimeType(), 'image/');
        }

        if (! is_string($state) || $state === '') {
            return false;
        }

        $mime = self::mimeFromPath(DocumentStorage::normalize($state) ?? $state);

        return is_string($mime) && str_starts_with($mime, 'image/');
    }

    /**
     * @return array<int, string>
     */
    public static function previewableImageExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif'];
    }

    public static function mimeFromPath(string $path): ?string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'heic' => 'image/heic',
            'heif' => 'image/heif',
            'dng' => 'image/x-adobe-dng',
            'pdf' => 'application/pdf',
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function documentValidationMessages(string $label): array
    {
        return [
            'required' => "El documento {$label} es obligatorio.",
            'mimetypes' => "Formato no válido en {$label}. Usa JPG, PNG, WEBP o HEIC (no RAW .dng).",
            'mimes' => "Formato no válido en {$label}. Usa JPG, PNG, WEBP o HEIC (no RAW .dng).",
            'max' => "El archivo de {$label} es demasiado grande (máximo ".(int) (self::MAX_UPLOAD_KILOBYTES / 1024).' MB).',
        ];
    }

    public static function browserPreviewUrl(string $path): ?string
    {
        $path = DocumentStorage::normalize($path);

        if ($path === null) {
            return null;
        }

        try {
            // Nombre con extensión en la ruta: FilePond restaura mejor la miniatura.
            return url('/ventas/documentos/preview/'.rawurlencode(basename($path))).'?path='.rawurlencode($path);
        } catch (\Throwable) {
            return DocumentStorage::url($path);
        }
    }

    public static function acceptAttribute(bool $allowPdf = false): string
    {
        // image/* primero: en iOS Safari abre el selector con Cámara + Fototeca.
        // Listar solo mime concretos + capture suele dejar solo cámara.
        // Sin image/* suelto: en iPhone abre también ProRAW (.dng) y luego falla al guardar.
        $parts = [
            'image/png',
            'image/jpeg',
            'image/jpg',
            'image/webp',
            'image/heic',
            'image/heif',
            '.png',
            '.jpg',
            '.jpeg',
            '.webp',
            '.heic',
            '.heif',
        ];

        if ($allowPdf) {
            $parts[] = 'application/pdf';
            $parts[] = '.pdf';
        }

        return implode(',', $parts);
    }

    public static function configure(
        FileUpload $upload,
        string $field,
        bool $required = false,
        ?bool $allowPdf = null,
        bool $soloCamara = false,
    ): FileUpload {
        $allowPdf ??= self::fieldAllowsPdf($field, auth()->user());

        // Nunca usar el atributo HTML capture:
        // - En iPhone/Safari fuerza la cámara y a menudo bloquea la fototeca.
        // - En escritorio impide elegir ficheros del disco.
        // Sin capture, iOS muestra: Hacer foto / Fototeca / Explorar.
        // ($soloCamara se conserva en la firma por compatibilidad; se ignora a propósito.)
        unset($soloCamara);
        $extraInputAttributes = [
            'accept' => self::acceptAttribute($allowPdf),
        ];

        return $upload
            ->label('')
            // Disco y visibilidad salen de config('filesystems.documents'): con el
            // default 'public' el comportamiento es idéntico al de siempre, y al
            // apuntar a Spaces los documentos se escriben privados y con URL firmada.
            ->disk(DocumentStorage::diskName())
            ->directory('ventas')
            ->visibility(DocumentStorage::uploadVisibility())
            ->acceptedFileTypes(self::acceptedDocumentMimeTypes($allowPdf))
            ->maxSize(self::MAX_UPLOAD_KILOBYTES)
            ->rules([
                function () use ($allowPdf) {
                    return function (string $attribute, mixed $value, \Closure $fail) use ($allowPdf): void {
                        if (! $value instanceof TemporaryUploadedFile) {
                            return;
                        }

                        $message = self::uploadRejectionMessage(
                            $value->getClientOriginalName() ?: (string) $value->getFilename(),
                            $value->getSize() ?: null,
                            $allowPdf,
                        );

                        if ($message !== null) {
                            $fail($message);
                        }
                    };
                },
            ])
            // Evita que Filament “pierda” ficheros existentes al fallar el probe de mime/size
            // (varios uploads grandes en el mismo form → dropzone vacío salvo el último).
            ->fetchFileInformation(false)
            ->imagePreviewHeight('180')
            // Fotos: panel integrado con miniatura. PDF: fila compacta con ver/descargar/borrar.
            ->panelLayout(function (FileUpload $component): string {
                $state = $component->getState();

                if (blank($state) || (is_array($state) && $state === [])) {
                    return 'integrated';
                }

                return self::stateLooksLikeImage($state) ? 'integrated' : 'compact';
            })
            ->loadingIndicatorPosition('left')
            ->removeUploadedFileButtonPosition('left')
            ->previewable()
            ->openable()
            ->downloadable()
            ->required($required)
            // Solo desvincula del registro; el fichero permanece en storage.
            ->deleteUploadedFileUsing(static function (): void {})
            // Sin ->live(): en móvil provoca re-render y corta la subida Livewire.
            // Sin ->image(): la regla image de Laravel rechaza HEIC en iPhone.
            ->extraInputAttributes($extraInputAttributes)
            ->extraAttributes([
                'class' => 'venta-document-upload',
                'data-venta-document-upload' => '1',
            ])
            ->getUploadedFileUsing(function (string $file, string|array|null $storedFileNames = null): ?array {
                $path = DocumentStorage::normalize($file) ?? $file;
                $url = self::browserPreviewUrl($path);

                if ($url === null) {
                    return null;
                }

                $name = is_string($storedFileNames) && $storedFileNames !== ''
                    ? $storedFileNames
                    : (is_array($storedFileNames) ? ($storedFileNames[$file] ?? basename($path)) : basename($path));

                return [
                    'name' => $name,
                    // Safari/FilePond: size 0 suele romper la miniatura; 1 como mínimo seguro.
                    'size' => DocumentStorage::size($path) ?: 1,
                    'type' => self::mimeFromPath($path) ?? 'application/octet-stream',
                    'url' => $url,
                ];
            })
            ->afterStateHydrated(function (FileUpload $component, mixed $state) use ($field): void {
                // Filament itera el state con foreach: debe ser array, nunca string suelto.
                if (is_string($state) && $state !== '') {
                    $component->state([$state]);

                    return;
                }

                if (is_array($state) && $state !== []) {
                    return;
                }

                $record = $component->getRecord();
                $path = is_object($record) ? ($record->{$field} ?? null) : null;
                if (filled($path) && is_string($path)) {
                    $component->state([$path]);
                }
            })
            ->dehydrateStateUsing(function (mixed $state): ?string {
                if (is_array($state)) {
                    $first = array_values(array_filter($state, fn ($v) => filled($v)))[0] ?? null;

                    return is_string($first) ? $first : null;
                }

                return filled($state) && is_string($state) ? $state : null;
            })
            ->getUploadedFileNameForStorageUsing(
                function (TemporaryUploadedFile $file) use ($field): string {
                    $user = auth()->user();

                    $timestamp = now()->format('Ymd_His');
                    $empleadoId = $user?->empleado_id ?? 'sin-id';
                    $fullName = $user
                        ? Str::slug(trim(($user->name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'usuario', '_')
                        : 'sin-usuario';

                    $fieldSlug = Str::slug($field, '_');
                    $extension = strtolower($file->getClientOriginalExtension()
                        ?: $file->extension()
                        ?: 'jpg');

                    if ($extension === 'jpeg') {
                        $extension = 'jpg';
                    }

                    return "{$timestamp}_{$empleadoId}_{$fullName}_{$fieldSlug}.{$extension}";
                }
            );
    }
}
