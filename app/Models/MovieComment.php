<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Comments under a movie: root comments plus one level of replies,
 * the same shape as newsfeed comments.
 */
class MovieComment extends Model
{
    protected $fillable = ['movie_id', 'user_id', 'parent_id', 'body'];

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    /** The comment's author and the movie's uploader may remove it. */
    public function removableBy(?User $user): bool
    {
        return $user && ($user->id === $this->user_id || $user->id === $this->movie->user_id);
    }
}
