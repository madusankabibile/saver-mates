<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovieWatchProgress extends Model
{
    use HasFactory;

    protected $table = 'movie_watch_progress';

    protected $fillable = [
        'user_id',
        'movie_id',
        'current_time',
        'duration',
        'progress_percent',
        'last_watched_at',
    ];

    protected $casts = [
        'current_time' => 'integer',
        'duration' => 'integer',
        'progress_percent' => 'decimal:2',
        'last_watched_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    /**
     * Check if the movie has been mostly watched (>90%)
     */
    public function isCompleted(): bool
    {
        return $this->progress_percent >= 90;
    }

    /**
     * Check if this is in progress (between 5% and 90%)
     */
    public function isInProgress(): bool
    {
        return $this->progress_percent >= 5 && $this->progress_percent < 90;
    }
}
