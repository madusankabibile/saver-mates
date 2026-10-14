<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** A member of a group chat: their role, read position and mute setting. */
class ConversationMember extends Model
{
    /** Stored for "until I turn it back on". */
    public const FOREVER = '2100-01-01 00:00:00';

    /** Choices offered in the mute menu, in hours (null = until turned back on). */
    public const MUTE_OPTIONS = ['1' => '1 hour', '8' => '8 hours', '168' => '1 week', 'forever' => 'Until I turn it back on'];

    protected $fillable = ['conversation_id', 'user_id', 'role', 'joined_after_message_id', 'last_read_message_id', 'muted_until'];

    protected $casts = ['muted_until' => 'datetime'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isMuted(): bool
    {
        return $this->muted_until !== null && $this->muted_until->isFuture();
    }

    public function muteLabel(): ?string
    {
        if (! $this->isMuted()) {
            return null;
        }

        return $this->muted_until->year >= 2100
            ? 'Muted'
            : 'Muted until '.($this->muted_until->isToday() ? $this->muted_until->format('g:i a') : $this->muted_until->format('D j M, g:i a'));
    }

    public static function muteUntil(string $choice): Carbon
    {
        return $choice === 'forever' ? Carbon::parse(self::FOREVER) : now()->addHours((int) $choice);
    }

    /** Scope: memberships whose notifications are on. */
    public function scopeAudible($query)
    {
        return $query->where(fn ($q) => $q->whereNull('muted_until')->orWhere('muted_until', '<=', now()));
    }
}
