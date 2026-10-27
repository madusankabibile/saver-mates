<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    protected $fillable = [
        'message_id', 'source', 'drive_file_id',
        'name', 'path', 'mime', 'size',
    ];

    /** Chat files are private to the two participants. */
    public const DISK = 'local';

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function driveFile(): BelongsTo
    {
        return $this->belongsTo(DriveFile::class);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    /**
     * Rendered inline (image thumbnails, previews) - still behind the
     * participants-only check in ChatController.
     */
    public function url(): string
    {
        return route('chat.attachment.inline', $this);
    }

    public function downloadUrl(): string
    {
        return route('chat.attachment', $this);
    }

    public function readableSize(): string
    {
        return DriveFile::humanSize((int) $this->size);
    }
}
