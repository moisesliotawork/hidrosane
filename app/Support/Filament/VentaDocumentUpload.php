<?php

namespace App\Support\Filament;

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

    public static function acceptAttribute(): string
    {
        return implode(',', [
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
        ]);
    }

    public static function configure(
        FileUpload $upload,
        string $field,
        bool $required = false,
    ): FileUpload {
        return $upload
            ->label('')
            ->disk('public')
            ->directory('ventas')
            ->visibility('public')
            ->acceptedFileTypes(self::acceptedImageMimeTypes())
            ->imagePreviewHeight('200')
            ->openable()
            ->downloadable()
            ->required($required)
            // Sin ->live(): en móvil provoca re-render y corta la subida Livewire.
            // Sin ->image(): la regla image de Laravel rechaza HEIC en iPhone.
            ->extraInputAttributes([
                // Sin capture=: en muchos móviles bloquea la galería y el input no abre.
                'accept' => self::acceptAttribute(),
            ])
            ->extraAttributes([
                'class' => 'border-2 border-dashed py-16',
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
