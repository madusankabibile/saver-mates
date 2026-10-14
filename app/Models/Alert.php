<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = [
        'user_id', 'actor_id', 'type', 'post_id', 'movie_id',
        'comment_id', 'conversation_id', 'preview', 'read_at',
    ];

    protected $casts = ['read_at' => 'datetime'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Record an alert, skipping self-notifications.
     */
    public static function record(array $attributes): ?self
    {
        if (($attributes['user_id'] ?? null) === ($attributes['actor_id'] ?? null)) {
            return null;
        }

        return self::create($attributes);
    }

    public function icon(): string
    {
        return match ($this->type) {
            'reaction' => '&#10024;',
            'comment' => '&#128172;',
            'reply', 'movie_reply' => '&#8617;&#65039;',
            'movie_comment' => '&#127916;',
            'message' => '&#9993;&#65039;',
            'announcement' => '&#128227;',
            'group_message' => '&#128101;',
            'group_added' => '&#10133;',
            default => '&#128276;',
        };
    }

    public function sentence(): string
    {
        return match ($this->type) {
            'reaction' => 'reacted to your post',
            'comment' => 'commented on your post',
            'reply', 'movie_reply' => 'replied to your comment',
            'movie_comment' => 'commented on your movie',
            'message' => 'sent you a message',
            'announcement' => 'posted an announcement',
            'group_message' => 'posted in "'.($this->conversation?->name ?? 'a group').'"',
            'group_added' => 'added you to a group',
            default => 'sent you an update',
        };
    }

    public function url(): string
    {
        if ($this->conversation_id) {
            return route('chat.show', $this->conversation_id);
        }

        if ($this->movie_id) {
            return route('movies.show', $this->movie_id).'#comments';
        }

        if ($this->post_id) {
            return route('posts.show', $this->post_id);
        }

        return route('alerts.index');
    }
}
