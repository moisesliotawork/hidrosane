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
        ];
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
     * Título + subtítulo, y miniatura FilePond (abrir, descargar, borrar) como en móvil.
     */
    public static function card(string $field, string $label, FileUpload $upload): Group
    {
        return Group::make([
            View::make('filament.forms.venta-document-large-preview')
                ->viewData(['label' => $label]),
            $upload->hiddenLabel(),
        ])->columns(1);
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

        $path = is_string($state) && $state !== ''
            ? $state
            : (is_object($record) ? ($record->{$field} ?? null) : null);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $mime = self::mimeFromPath($path);
        if ($mime === null || ! str_starts_with($mime, 'image/')) {
            return null;
        }

        return self::browserPreviewUrl($path);
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
            'pdf' => 'application/pdf',
            default => null,
        };
    }

    public static function browserPreviewUrl(string $path): ?string
    {
        $path = DocumentStorage::normalize($path);

        if ($path === null) {
            return null;
        }

        try {
            return route('venta-documents.preview', ['path' => $path]);
        } catch (\Throwable) {
            return DocumentStorage::url($path);
        }
    }

    public static function acceptAttribute(bool $allowPdf = false): string
    {
        $parts = [
            'image/png',
            'image/jpeg',
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

        $extraInputAttributes = [
            // Sin capture por defecto: en muchos móviles bloquea la galería.
            'accept' => self::acceptAttribute($allowPdf),
        ];

        // Nunca capture en paneles de oficina: en escritorio impide adjuntar desde el disco.
        if ($soloCamara && ! self::isOfficeContractsPanel()) {
            $extraInputAttributes['capture'] = 'environment';
        }

        return $upload
            ->label('')
            // Disco y visibilidad salen de config('filesystems.documents'): con el
            // default 'public' el comportamiento es idéntico al de siempre, y al
            // apuntar a Spaces los documentos se escriben privados y con URL firmada.
            ->disk(DocumentStorage::diskName())
            ->directory('ventas')
            ->visibility(DocumentStorage::uploadVisibility())
            ->acceptedFileTypes(self::acceptedDocumentMimeTypes($allowPdf))
            // Evita que Filament “pierda” ficheros existentes al fallar el probe de mime/size
            // (varios uploads grandes en el mismo form → dropzone vacío salvo el último).
            ->fetchFileInformation(false)
            ->imagePreviewHeight('280')
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
                    'size' => 0,
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
