<?php

namespace App\Support\Filament;

use App\Models\User;
use App\Support\ContractsCommercialUser;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class VentaDocumentUpload
{
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
        ];
    }

    public static function fieldAllowsPdf(string $field, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (ContractsCommercialUser::matches($user)) {
            return true;
        }

        return in_array($field, self::fieldsAllowingPdf(), true);
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

        if ($soloCamara) {
            $extraInputAttributes['capture'] = 'environment';
        }

        return $upload
            ->label('')
            ->disk('public')
            ->directory('ventas')
            ->visibility('public')
            ->acceptedFileTypes(self::acceptedDocumentMimeTypes($allowPdf))
            ->imagePreviewHeight('200')
            ->openable()
            ->downloadable()
            ->required($required)
            // Solo desvincula del registro; el fichero permanece en storage.
            ->deleteUploadedFileUsing(static function (): void {})
            // Sin ->live(): en móvil provoca re-render y corta la subida Livewire.
            // Sin ->image(): la regla image de Laravel rechaza HEIC en iPhone.
            ->extraInputAttributes($extraInputAttributes)
            ->extraAttributes([
                'class' => 'border-2 border-dashed py-16 venta-document-upload',
                'data-venta-document-upload' => '1',
            ])
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
