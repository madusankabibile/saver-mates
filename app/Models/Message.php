<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    protected $fillable = ['conversation_id', 'sender_id', 'body', 'event', 'read_at', 'edited_at', 'removed_at'];

    protected $casts = [
        'read_at' => 'datetime',
        'edited_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    /** "Nimal added Sanduni" style lines in a group, not a real message. */
    public function isEvent(): bool
    {
        return $this->event !== null;
    }

    public function isRemoved(): bool
    {
        return $this->removed_at !== null;
    }

    /** The sender can edit, delete and inspect their own real messages. */
    public function isManageableBy(User $user): bool
    {
        return $this->sender_id === $user->id && ! $this->isEvent() && ! $this->isRemoved();
    }

    /**
     * Delete for everyone: the text and any files uploaded with it go, the
     * row stays as "This message was deleted". Drive attachments were only
     * links to the sender's drive, so those files are left alone.
     */
    public function removeForEveryone(): void
    {
        $uploads = $this->attachments()->where('source', 'upload')->whereNotNull('path')->pluck('path')->all();
        Storage::disk(MessageAttachment::DISK)->delete($uploads);
        $this->attachments()->delete();

        $this->forceFill(['body' => null, 'edited_at' => null, 'removed_at' => now()])->save();
    }
}
