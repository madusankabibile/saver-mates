<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A private chat between two members (user_one/user_two, lower id first) or
 * a group chat (is_group, with its people in conversation_members).
 */
class Conversation extends Model
{
    public const GROUP_MAX_MEMBERS = 100;

    protected $fillable = ['user_one_id', 'user_two_id', 'last_message_at', 'is_group', 'name', 'created_by'];

    protected $casts = [
        'last_message_at' => 'datetime',
        'is_group' => 'boolean',
    ];

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_members')
            ->withPivot(['role', 'muted_until'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->oldest();
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Find (or open) the single conversation shared by two members.
     */
    public static function between(User $a, User $b): self
    {
        [$low, $high] = $a->id < $b->id ? [$a->id, $b->id] : [$b->id, $a->id];

        return self::firstOrCreate(
            ['user_one_id' => $low, 'user_two_id' => $high, 'is_group' => false],
            ['last_message_at' => now()]
        );
    }

    /**
     * Start a group with its creator as the first admin.
     *
     * @param  array<int>  $memberIds
     */
    public static function createGroup(User $creator, string $name, array $memberIds): self
    {
        $group = self::create([
            'is_group' => true,
            'name' => $name,
            'created_by' => $creator->id,
            'last_message_at' => now(),
        ]);

        $group->members()->create(['user_id' => $creator->id, 'role' => 'admin']);
        $group->addSystemMessage($creator, 'created', 'created the group');
        $group->addMembers($creator, $memberIds);

        return $group;
    }

    /** Conversations a member takes part in, private and group. */
    public function scopeFor(Builder $query, User $user): Builder
    {
        return $query->where(function ($q) use ($user) {
            $q->where('user_one_id', $user->id)
                ->orWhere('user_two_id', $user->id)
                ->orWhereHas('members', fn ($m) => $m->where('user_id', $user->id));
        });
    }

    public function includes(User $user): bool
    {
        if ($this->is_group) {
            return $this->membershipFor($user) !== null;
        }

        return in_array($user->id, [$this->user_one_id, $this->user_two_id], true);
    }

    public function membershipFor(User $user): ?ConversationMember
    {
        if (! $this->is_group) {
            return null;
        }

        return $this->relationLoaded('members')
            ? $this->members->firstWhere('user_id', $user->id)
            : $this->members()->where('user_id', $user->id)->first();
    }

    public function isAdmin(User $user): bool
    {
        return (bool) $this->membershipFor($user)?->isAdmin();
    }

    public function partnerFor(User $user): ?User
    {
        if ($this->is_group) {
            return null;
        }

        return $this->user_one_id === $user->id ? $this->userTwo : $this->userOne;
    }

    public function displayNameFor(User $user): string
    {
        return $this->is_group ? (string) $this->name : (string) $this->partnerFor($user)?->name;
    }

    /** Group avatar: up to two initials on a colour picked from the id. */
    public function groupInitials(): string
    {
        $words = array_values(array_filter(preg_split('/\s+/', trim((string) $this->name)) ?: []));

        return mb_strtoupper(mb_substr($words[0] ?? 'G', 0, 1).mb_substr($words[1] ?? '', 0, 1));
    }

    public function groupHue(): int
    {
        return ($this->id * 67) % 360;
    }

    public function unreadCountFor(User $user): int
    {
        if ($this->is_group) {
            $member = $this->membershipFor($user);

            return $member ? $this->messages()
                ->where('id', '>', (int) $member->last_read_message_id)
                ->where('sender_id', '!=', $user->id)
                ->whereNull('event')->whereNull('removed_at')
                ->count() : 0;
        }

        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')->whereNull('removed_at')
            ->count();
    }

    public function markReadFor(User $user): void
    {
        if ($this->is_group) {
            $latest = $this->messages()->max('id');

            if ($latest) {
                $this->members()->where('user_id', $user->id)
                    ->where(fn ($q) => $q->whereNull('last_read_message_id')->orWhere('last_read_message_id', '<', $latest))
                    ->update(['last_read_message_id' => $latest]);
            }
        } else {
            $this->messages()
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        Alert::where('user_id', $user->id)
            ->where('conversation_id', $this->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /* ---------------------------------------------------------------- */
    /* Group membership                                                  */
    /* ---------------------------------------------------------------- */

    /**
     * Add members who are not in the group yet. Returns the users added.
     *
     * @param  array<int>  $userIds
     */
    public function addMembers(User $actor, array $userIds)
    {
        $existing = $this->members()->pluck('user_id')->all();
        $room = self::GROUP_MAX_MEMBERS - count($existing);

        $users = User::whereIn('id', $userIds)
            ->whereNotIn('id', $existing)
            ->whereNull('suspended_at')
            ->orderBy('name')
            ->limit(max(0, $room))
            ->get();

        if ($users->isEmpty()) {
            return $users;
        }

        // New members start "caught up" so they are not greeted with old unread messages.
        $latest = $this->messages()->max('id');

        foreach ($users as $user) {
            $this->members()->create(['user_id' => $user->id, 'role' => 'member', 'joined_after_message_id' => $latest, 'last_read_message_id' => $latest]);
        }

        $this->addSystemMessage($actor, 'added', 'added '.self::nameList($users->pluck('name')->all()));

        return $users;
    }

    public function removeMember(User $actor, User $user): void
    {
        $this->members()->where('user_id', $user->id)->delete();

        $this->addSystemMessage($actor, $actor->is($user) ? 'left' : 'removed', $actor->is($user) ? 'left the group' : 'removed '.$user->name);

        $this->ensureAnAdmin();
    }

    /**
     * A group always keeps an admin: if the last one leaves, the longest
     * standing member takes over. An empty group is deleted.
     */
    public function ensureAnAdmin(): void
    {
        $members = $this->members()->orderBy('id')->get();

        if ($members->isEmpty()) {
            $this->deleteWithFiles();

            return;
        }

        if (! $members->contains(fn ($m) => $m->isAdmin())) {
            $members->first()->update(['role' => 'admin']);
        }
    }

    public function addSystemMessage(User $actor, string $event, string $text): Message
    {
        $this->forceFill(['last_message_at' => now()])->save();

        return $this->messages()->create([
            'sender_id' => $actor->id,
            'body' => $text,
            'event' => $event,
        ]);
    }

    /** Remove the conversation and any files uploaded into it. */
    public function deleteWithFiles(): void
    {
        $paths = MessageAttachment::where('source', 'upload')
            ->whereIn('message_id', $this->messages()->select('id'))
            ->whereNotNull('path')
            ->pluck('path')
            ->all();

        \Illuminate\Support\Facades\Storage::disk(MessageAttachment::DISK)->delete($paths);

        $this->delete();
    }

    protected static function nameList(array $names): string
    {
        if (count($names) <= 3) {
            return count($names) > 1
                ? implode(', ', array_slice($names, 0, -1)).' and '.end($names)
                : (string) ($names[0] ?? '');
        }

        return implode(', ', array_slice($names, 0, 2)).' and '.(count($names) - 2).' others';
    }
}
