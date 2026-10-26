<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Software extends Model
{
    public const DISK = 'public';

    public const OS_TYPES = ['Windows', 'macOS', 'Linux', 'Android', 'iOS', 'Cross-platform', 'Web'];

    public const CATEGORIES = [
        'Productivity', 'Developer Tools', 'Graphics & Design', 'Media & Entertainment',
        'Utilities', 'Security', 'Education', 'Networking', 'Games', 'Other',
    ];

    protected $fillable = [
        'user_id', 'title', 'description', 'version', 'os_type', 'category',
        'file_path', 'file_name', 'file_size', 'file_mime', 'thumbnail_path',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'software_likes')->withTimestamps();
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%")
              ->orWhere('category', 'like', "%{$term}%")
              ->orWhere('os_type', 'like', "%{$term}%");
        });
    }

    public function fileUrl(): string
    {
        return Storage::disk(self::DISK)->url($this->file_path);
    }

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail_path ? Storage::disk(self::DISK)->url($this->thumbnail_path) : null;
    }

    public function sizeLabel(): string
    {
        $b = $this->file_size;
        if ($b >= 1073741824) return round($b / 1073741824, 1) . ' GB';
        if ($b >= 1048576)    return round($b / 1048576, 1) . ' MB';
        if ($b >= 1024)       return round($b / 1024, 1) . ' KB';
        return $b . ' B';
    }

    public function downloadsLabel(): string
    {
        $n = $this->downloads;
        if ($n >= 1000000) return round($n / 1000000, 1) . 'M downloads';
        if ($n >= 1000)    return round($n / 1000, 1) . 'K downloads';
        return $n . ' ' . ($n === 1 ? 'download' : 'downloads');
    }

    public function viewsLabel(): string
    {
        $n = $this->views;
        if ($n >= 1000000) return round($n / 1000000, 1) . 'M views';
        if ($n >= 1000)    return round($n / 1000, 1) . 'K views';
        return $n . ' ' . ($n === 1 ? 'view' : 'views');
    }

    public function posterHue(): int
    {
        return abs(crc32($this->title ?? '')) % 360;
    }

    public function deleteWithFiles(): void
    {
        if ($this->file_path)      Storage::disk(self::DISK)->delete($this->file_path);
        if ($this->thumbnail_path) Storage::disk(self::DISK)->delete($this->thumbnail_path);
        $this->delete();
    }
}
