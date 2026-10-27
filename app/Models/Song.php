<?php

namespace App\Models;

use App\Support\MediaMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Song extends Model
{
    public const DISK = 'public';

    /** Set on upload when the song's details were found automatically (not stored). */
    public ?string $detailsFound = null; // "full", "partial" (title and artist only) or null

    protected $fillable = [
        'user_id', 'title', 'artist', 'album', 'genre', 'path', 'mime',
        'size', 'duration', 'cover_path', 'plays',
    ];

    protected $casts = [
        'duration' => 'integer',
        'plays' => 'integer',
        'size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'song_likes')->withTimestamps();
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class)->withPivot('position')->withTimestamps();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('artist', 'like', "%{$term}%")
                ->orWhere('album', 'like', "%{$term}%")
                ->orWhere('genre', 'like', "%{$term}%");
        });
    }

    public function url(): string
    {
        return asset('storage/'.$this->path);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? asset('storage/'.$this->cover_path) : null;
    }

    public function artistName(): string
    {
        return $this->artist ?: 'Unknown artist';
    }

    public function durationLabel(): string
    {
        return $this->duration ? MediaMeta::formatDuration($this->duration) : '--:--';
    }

    /** A stable hue for the cover gradient when a track has no artwork. */
    public function hue(): int
    {
        return crc32($this->artistName().$this->album) % 360;
    }

    /** The shape the music player works with, embedded in each track row. */
    public function toPlayerArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'artist' => $this->artistName(),
            'album' => $this->album,
            'src' => $this->url(),
            'cover' => $this->coverUrl(),
            'hue' => $this->hue(),
            'duration' => $this->duration,
        ];
    }

    public function deleteWithFiles(): void
    {
        $disk = Storage::disk(self::DISK);
        $disk->delete($this->path);

        // Covers are stored once per image and shared between tracks of an album.
        if ($this->cover_path && ! self::where('cover_path', $this->cover_path)->whereKeyNot($this->id)->exists()) {
            $disk->delete($this->cover_path);
        }

        $this->delete();
    }
}
