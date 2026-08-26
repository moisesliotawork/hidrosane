<?php

namespace App\Models;

use App\Support\Storage\DocumentStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Documento genérico de SuperAdmin (imagen o PDF) con descripción.
 *
 * @property int $id
 * @property string|null $original_name
 * @property string $file_path
 * @property string|null $mime_type
 * @property string|null $description
 * @property int|null $uploaded_by_user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Documento extends Model
{
    use SoftDeletes;

    protected $table = 'documentos';

    protected $fillable = [
        'original_name',
        'file_path',
        'mime_type',
        'description',
        'uploaded_by_user_id',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function displayName(): string
    {
        $name = trim((string) $this->original_name);
        if ($name !== '') {
            return $name;
        }

        return basename((string) $this->file_path) ?: 'Documento #'.$this->id;
    }

    public function infoPreview(int $max = 20): string
    {
        $raw = trim((string) ($this->description ?? ''));
        if ($raw === '') {
            return '—';
        }

        return mb_strlen($raw) > $max
            ? mb_substr($raw, 0, $max).'...'
            : $raw;
    }

    public function publicUrl(): ?string
    {
        return DocumentStorage::url($this->file_path);
    }

    public function isPdf(): bool
    {
        $mime = strtolower((string) $this->mime_type);
        $ext = strtolower(pathinfo((string) ($this->original_name ?: $this->file_path), PATHINFO_EXTENSION));

        return str_contains($mime, 'pdf') || $ext === 'pdf';
    }

    public function isImage(): bool
    {
        $mime = strtolower((string) $this->mime_type);
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        $ext = strtolower(pathinfo((string) ($this->original_name ?: $this->file_path), PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
    }
}
