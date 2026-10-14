<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovieSubtitle extends Model
{
    protected $fillable = ['movie_id', 'label', 'lang', 'path'];

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    /** WebVTT file, converted from SRT on upload when needed. */
    public function url(): string
    {
        return asset('storage/'.$this->path);
    }
}
