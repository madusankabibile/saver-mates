<?php

namespace App\Models;

use App\Support\MediaMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Playlists are always public: anyone can open and play one, only the owner edits it.
 */
class Playlist extends Model
{
    protected $fillable = ['user_id', 'name', 'description', 'plays'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class)
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('playlist_song.position');
    }

    /** Up to four distinct covers for the mosaic artwork. */
    public function mosaic(): array
    {
        return $this->songs
            ->map(fn (Song $s) => ['cover' => $s->coverUrl(), 'hue' => $s->hue()])
            ->unique(fn ($c) => $c['cover'] ?? 'hue'.$c['hue'])
            ->take(4)
            ->values()
            ->all();
    }

    public function totalDurationLabel(): string
    {
        $seconds = (int) $this->songs->sum('duration');

        if ($seconds >= 3600) {
            return floor($seconds / 3600).' hr '.floor(($seconds % 3600) / 60).' min';
        }

        return max(1, (int) round($seconds / 60)).' min';
    }

    public function playsLabel(): string
    {
        return MediaMeta::compactNumber($this->plays);
    }
}
