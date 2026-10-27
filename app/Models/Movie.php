<?php

namespace App\Models;

use App\Support\MediaMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Movie extends Model
{
    /** Movies are public, so they live on the public disk and Apache streams them (with Range support). */
    public const DISK = 'public';

    public const CATEGORIES = [
        'Action', 'Animation', 'Comedy', 'Drama', 'Family', 'Horror',
        'Romance', 'Sci-Fi', 'Thriller', 'Documentary', 'Music', 'Other',
    ];

    /** Containers a browser can usually play; anything else gets a warning. */
    public const BROWSER_FRIENDLY = ['video/mp4', 'video/webm', 'video/ogg'];

    protected $fillable = [
        'user_id', 'title', 'description', 'category', 'video_path', 'video_mime',
        'video_size', 'thumbnail_path', 'duration', 'views',
    ];

    protected $casts = [
        'duration' => 'integer',
        'views' => 'integer',
        'video_size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subtitles(): HasMany
    {
        return $this->hasMany(MovieSubtitle::class)->orderBy('label');
    }

    /** Root comments, newest first; replies hang off each one. */
    public function comments(): HasMany
    {
        return $this->hasMany(MovieComment::class)->whereNull('parent_id')->latest();
    }

    public function allComments(): HasMany
    {
        return $this->hasMany(MovieComment::class);
    }

    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'movie_likes')->withTimestamps();
    }

    public function watchProgress(): HasMany
    {
        return $this->hasMany(MovieWatchProgress::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('category', 'like', "%{$term}%");
        });
    }

    /* ---------------------------------------------------------------- */
    /* Presentation                                                      */
    /* ---------------------------------------------------------------- */

    public function videoUrl(): string
    {
        return asset('storage/'.$this->video_path);
    }

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail_path ? asset('storage/'.$this->thumbnail_path) : null;
    }

    public function durationLabel(): ?string
    {
        return $this->duration ? MediaMeta::formatDuration($this->duration) : null;
    }

    public function viewsLabel(): string
    {
        return MediaMeta::compactNumber($this->views).' '.Str::plural('view', $this->views);
    }

    public function isBrowserFriendly(): bool
    {
        return in_array($this->video_mime, self::BROWSER_FRIENDLY, true);
    }

    /** A stable hue for the gradient shown while a movie has no thumbnail. */
    public function posterHue(): int
    {
        return (crc32($this->title) + $this->id * 47) % 360;
    }

    /**
     * Movies to show beside the player: same category, same uploader and shared
     * title words score highest, popular movies fill the rest of the list.
     */
    public function related(int $limit = 12): Collection
    {
        $words = self::titleWords($this->title);

        return self::with('user')
            ->where('id', '!=', $this->id)
            ->latest()
            ->limit(300)
            ->get()
            ->map(function (Movie $movie) use ($words) {
                $score = 0;
                $score += $this->category && $movie->category === $this->category ? 4 : 0;
                $score += $movie->user_id === $this->user_id ? 2 : 0;
                $score += count(array_intersect($words, self::titleWords($movie->title))) * 3;
                $score += log($movie->views + 1) * .6;
                $movie->setAttribute('relevance', $score);

                return $movie;
            })
            ->sortByDesc('relevance')
            ->take($limit)
            ->values();
    }

    protected static function titleWords(string $title): array
    {
        $stop = ['the', 'a', 'an', 'of', 'and', 'in', 'on', 'to', 'part', 'movie'];

        return array_values(array_diff(
            array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($title)), fn ($w) => mb_strlen($w) > 2 && ! is_numeric($w)),
            $stop
        ));
    }

    /** Remove the video, thumbnail and subtitle files along with the row. */
    public function deleteWithFiles(): void
    {
        $disk = Storage::disk(self::DISK);
        $disk->delete(array_filter([
            $this->video_path,
            $this->thumbnail_path,
            ...$this->subtitles->pluck('path')->all(),
        ]));

        $this->delete();
    }
}
