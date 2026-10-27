<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * How long after their last request a member still counts as "online".
     */
    public const ONLINE_WINDOW_MINUTES = 2;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'designation',
        'qualifications',
        'skills',
        'bio',
        'avatar',
        'cover',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'suspended_at' => 'datetime',
        'is_admin' => 'boolean',
        'password' => 'hashed',
    ];

    /* ---------------------------------------------------------------- */
    /* Relationships                                                     */
    /* ---------------------------------------------------------------- */

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class)->latest();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function driveFiles(): HasMany
    {
        return $this->hasMany(DriveFile::class);
    }

    public function driveFolders(): HasMany
    {
        return $this->hasMany(DriveFolder::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class)->latest();
    }

    public function movies(): HasMany
    {
        return $this->hasMany(Movie::class)->latest();
    }

    public function songs(): HasMany
    {
        return $this->hasMany(Song::class)->latest();
    }

    public function chatMemberships(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class)->latest();
    }

    /* ---------------------------------------------------------------- */
    /* Roles and moderation                                              */
    /* ---------------------------------------------------------------- */

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Remove the member and every file they put on disk. Rows go with the
     * cascading foreign keys; files on disk have to be deleted by hand.
     */
    public function deleteWithFiles(): void
    {
        foreach ($this->movies()->with('subtitles')->get() as $movie) {
            $movie->deleteWithFiles();
        }

        foreach ($this->songs()->get() as $song) {
            $song->deleteWithFiles();
        }

        foreach ($this->posts()->with('images')->get() as $post) {
            Storage::disk('public')->delete($post->images->pluck('path')->all());
        }

        Storage::disk(DriveFile::DISK)->delete($this->driveFiles()->pluck('path')->all());

        // Their private conversations go too, with files either side uploaded into them.
        foreach (Conversation::where('user_one_id', $this->id)->orWhere('user_two_id', $this->id)->get() as $conversation) {
            $conversation->deleteWithFiles();
        }

        // In groups their messages disappear (cascade), so remove their uploads
        // now, and hand over admin rights in groups they were running.
        Storage::disk(MessageAttachment::DISK)->delete(
            MessageAttachment::where('source', 'upload')
                ->whereIn('message_id', Message::where('sender_id', $this->id)->select('id'))
                ->whereNotNull('path')
                ->pluck('path')
                ->all()
        );

        $groups = Conversation::whereHas('members', fn ($m) => $m->where('user_id', $this->id))->get();
        ConversationMember::where('user_id', $this->id)->delete();
        $groups->each->ensureAnAdmin();

        Storage::disk('public')->delete(array_filter([$this->avatar, $this->cover]));

        $this->delete();
    }

    /* ---------------------------------------------------------------- */
    /* Presence                                                          */
    /* ---------------------------------------------------------------- */

    public function isOnline(): bool
    {
        return $this->last_seen_at
            && $this->last_seen_at->gt(now()->subMinutes(self::ONLINE_WINDOW_MINUTES));
    }

    public function scopeOnline($query)
    {
        return $query->where('last_seen_at', '>', now()->subMinutes(self::ONLINE_WINDOW_MINUTES));
    }

    public function lastSeenLabel(): string
    {
        if ($this->isOnline()) {
            return 'Online now';
        }

        return $this->last_seen_at
            ? 'Active '.$this->last_seen_at->diffForHumans()
            : 'Not active yet';
    }

    /* ---------------------------------------------------------------- */
    /* Presentation helpers                                              */
    /* ---------------------------------------------------------------- */

    public function avatarUrl(): string
    {
        if ($this->avatar && Storage::disk('public')->exists($this->avatar)) {
            return Storage::url($this->avatar);
        }

        return route('avatar.initials', ['user' => $this->id]);
    }

    public function coverUrl(): ?string
    {
        if ($this->cover && Storage::disk('public')->exists($this->cover)) {
            return Storage::url($this->cover);
        }

        return null;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $parts = array_values(array_filter($parts));

        if (empty($parts)) {
            return strtoupper(substr((string) $this->username, 0, 2));
        }

        $first = mb_substr($parts[0], 0, 1);
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    /**
     * A stable per-user colour so avatars stay recognisable between pages.
     */
    public function colorPair(): array
    {
        $palette = [
            ['#6366f1', '#8b5cf6'],
            ['#0ea5e9', '#2563eb'],
            ['#f43f5e', '#ec4899'],
            ['#f59e0b', '#f97316'],
            ['#10b981', '#059669'],
            ['#8b5cf6', '#d946ef'],
            ['#06b6d4', '#0891b2'],
            ['#ef4444', '#f97316'],
        ];

        return $palette[$this->id % count($palette)];
    }

    public function skillList(): array
    {
        if (! $this->skills) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $this->skills))));
    }

    public function unreadAlertsCount(): int
    {
        return $this->alerts()->whereNull('read_at')->count();
    }
}
