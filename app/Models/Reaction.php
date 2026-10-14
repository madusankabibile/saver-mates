<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reaction extends Model
{
    protected $fillable = ['post_id', 'user_id', 'type'];

    /**
     * The reaction palette offered on every post.
     */
    public const TYPES = [
        'like' => ['emoji' => '&#128077;', 'label' => 'Like', 'color' => '#2563eb'],
        'love' => ['emoji' => '&#10084;&#65039;', 'label' => 'Love', 'color' => '#f43f5e'],
        'haha' => ['emoji' => '&#128514;', 'label' => 'Haha', 'color' => '#fbbf24'],
        'sad' => ['emoji' => '&#128546;', 'label' => 'Sad', 'color' => '#0ea5e9'],
        'fire' => ['emoji' => '&#128293;', 'label' => 'Fire', 'color' => '#f97316'],
    ];

    /**
     * Reaction types that have been retired, mapped to what replaced them.
     * Rows stored before a type was dropped still render through this.
     */
    public const RETIRED = [
        'wow' => 'haha',
        'clap' => 'fire',
        'idea' => 'like',
    ];

    /**
     * The stored type, folded onto a type that still exists.
     */
    public static function normalise(?string $type): string
    {
        $type = (string) $type;

        if (isset(self::TYPES[$type])) {
            return $type;
        }

        return self::RETIRED[$type] ?? 'like';
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function emoji(string $type): string
    {
        return self::TYPES[self::normalise($type)]['emoji'];
    }

    public static function label(string $type): string
    {
        return self::TYPES[self::normalise($type)]['label'];
    }
}
