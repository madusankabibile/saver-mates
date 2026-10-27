<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriveFile extends Model
{
    protected $fillable = [
        'user_id', 'folder_id', 'name', 'path',
        'mime', 'size', 'is_public',
    ];

    protected $casts = ['is_public' => 'boolean'];

    /** Private disk - never web accessible. */
    public const DISK = 'local';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DriveFolder::class, 'folder_id');
    }

    /**
     * Drive files live on the private disk, so every read goes through the
     * controller that checks the privacy flag. There is no direct storage URL.
     */
    public function url(): string
    {
        return route('drive.files.preview', $this);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }

    /**
     * Files we can safely render inline in the preview pane.
     */
    public function isPreviewable(): bool
    {
        return $this->isImage()
            || $this->isPdf()
            || str_starts_with((string) $this->mime, 'video/')
            || str_starts_with((string) $this->mime, 'audio/')
            || str_starts_with((string) $this->mime, 'text/');
    }

    public function extension(): string
    {
        return strtoupper(pathinfo($this->name, PATHINFO_EXTENSION) ?: 'FILE');
    }

    public function readableSize(): string
    {
        return self::humanSize((int) $this->size);
    }

    public static function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $i = 0;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, $value >= 10 ? 0 : 1).' '.$units[$i];
    }

    /**
     * A colour + glyph pair so each file type reads at a glance.
     */
    public function badge(): array
    {
        $ext = strtolower(pathinfo($this->name, PATHINFO_EXTENSION));

        return match (true) {
            $this->isImage() => ['color' => '#8b5cf6', 'glyph' => 'IMG'],
            $this->isPdf() => ['color' => '#ef4444', 'glyph' => 'PDF'],
            in_array($ext, ['doc', 'docx', 'odt', 'rtf']) => ['color' => '#2563eb', 'glyph' => 'DOC'],
            in_array($ext, ['xls', 'xlsx', 'csv', 'ods']) => ['color' => '#059669', 'glyph' => 'XLS'],
            in_array($ext, ['ppt', 'pptx', 'odp']) => ['color' => '#f97316', 'glyph' => 'PPT'],
            in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz']) => ['color' => '#f59e0b', 'glyph' => 'ZIP'],
            str_starts_with((string) $this->mime, 'video/') => ['color' => '#ec4899', 'glyph' => 'VID'],
            str_starts_with((string) $this->mime, 'audio/') => ['color' => '#06b6d4', 'glyph' => 'AUD'],
            in_array($ext, ['php', 'js', 'py', 'java', 'sql', 'json', 'html', 'css']) => ['color' => '#0891b2', 'glyph' => 'COD'],
            default => ['color' => '#64748b', 'glyph' => $this->extension() ?: 'FILE'],
        };
    }
}
