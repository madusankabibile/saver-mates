<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creating and running group chats. Any member can add people, mute and
 * leave; renaming, removing people, promoting admins and deleting the group
 * are for the group's admins.
 */
class GroupChatController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'members' => ['required', 'array', 'min:1', 'max:'.(Conversation::GROUP_MAX_MEMBERS - 1)],
            'members.*' => ['integer', 'exists:users,id'],
        ], [
            'members.required' => 'Pick at least one colleague for the group.',
        ]);

        $me = $request->user();
        $memberIds = array_values(array_diff(array_unique($data['members']), [$me->id]));

        if (! $memberIds) {
            throw ValidationException::withMessages(['members' => 'Pick at least one colleague for the group.']);
        }

        $group = Conversation::createGroup($me, trim($data['name']), $memberIds);
        $this->alertAdded($group, $me, $group->members()->where('user_id', '!=', $me->id)->pluck('user_id')->all());

        return redirect()->route('chat.show', $group)->with('status', 'Group "'.$group->name.'" created.');
    }

    /** Rename the group. */
    public function update(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorizeAdmin($request, $conversation);

        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $name = trim($data['name']);

        if ($name !== $conversation->name) {
            $conversation->update(['name' => $name]);
            $conversation->addSystemMessage($request->user(), 'renamed', 'renamed the group to "'.$name.'"');
        }

        return back()->with('status', 'Group renamed.');
    }

    public function addMembers(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorizeMember($request, $conversation);

        $data = $request->validate([
            'members' => ['required', 'array', 'min:1', 'max:'.Conversation::GROUP_MAX_MEMBERS],
            'members.*' => ['integer', 'exists:users,id'],
        ]);

        $added = $conversation->addMembers($request->user(), $data['members']);
        $this->alertAdded($conversation, $request->user(), $added->pluck('id')->all());

        return back()->with('status', $added->isEmpty()
            ? 'Everyone you picked is already in the group.'
            : 'Added '.$added->count().' '.Str::plural('member', $added->count()).'.');
    }

    public function removeMember(Request $request, Conversation $conversation, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request, $conversation);
        abort_if($user->is($request->user()), 422, 'Use "Leave group" to leave.');
        abort_unless($conversation->includes($user), 404);

        $conversation->removeMember($request->user(), $user);

        return back()->with('status', $user->name.' was removed from the group.');
    }

    public function role(Request $request, Conversation $conversation, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request, $conversation);

        $data = $request->validate(['role' => ['required', Rule::in(['admin', 'member'])]]);
        $member = $conversation->membershipFor($user);
        abort_unless($member, 404);

        if ($data['role'] === 'member' && $conversation->members()->where('role', 'admin')->count() <= 1 && $member->isAdmin()) {
            return back()->withErrors(['role' => 'A group needs at least one admin. Make someone else an admin first.']);
        }

        $member->update(['role' => $data['role']]);
        $conversation->addSystemMessage($request->user(), 'role', $data['role'] === 'admin'
            ? 'made '.$user->name.' an admin'
            : 'removed '.$user->name.' as admin');

        return back()->with('status', $user->name.($data['role'] === 'admin' ? ' is now an admin.' : ' is no longer an admin.'));
    }

    /** Mute for a while, until turned back on, or unmute ("off"). */
    public function mute(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        $member = $this->authorizeMember($request, $conversation);

        $data = $request->validate([
            'for' => ['required', Rule::in([...array_keys(ConversationMember::MUTE_OPTIONS), 'off'])],
        ]);

        $member->update(['muted_until' => $data['for'] === 'off' ? null : ConversationMember::muteUntil($data['for'])]);

        // Muting also silences anything already waiting in the bell.
        if ($member->isMuted()) {
            Alert::where('user_id', $member->user_id)->where('conversation_id', $conversation->id)
                ->whereNull('read_at')->update(['read_at' => now()]);
        }

        $status = $member->isMuted() ? 'Notifications for this group are muted.' : 'Notifications for this group are on.';

        return $request->wantsJson()
            ? response()->json(['muted' => $member->isMuted(), 'label' => $member->muteLabel(), 'status' => $status])
            : back()->with('status', $status);
    }

    public function leave(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorizeMember($request, $conversation);

        $conversation->removeMember($request->user(), $request->user());

        return redirect()->route('chat.index')->with('status', 'You left "'.$conversation->name.'".');
    }

    public function destroy(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorizeAdmin($request, $conversation);

        $name = $conversation->name;
        $conversation->deleteWithFiles();

        return redirect()->route('chat.index')->with('status', 'Group "'.$name.'" deleted for everyone.');
    }

    /** Colleague search for the group member picker. */
    public function people(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));
        $exclude = [];

        if ($groupId = $request->integer('group')) {
            $group = Conversation::where('is_group', true)->find($groupId);
            if ($group && $group->includes($request->user())) {
                $exclude = $group->members()->pluck('user_id')->all();
            }
        }

        $people = User::whereKeyNot($request->user()->id)
            ->whereNotIn('id', $exclude)
            ->whereNull('suspended_at')
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('username', 'like', "%{$term}%")
                ->orWhere('designation', 'like', "%{$term}%")))
            ->orderByRaw('last_seen_at IS NULL, last_seen_at DESC')
            ->limit(40)
            ->get();

        return response()->json($people->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'meta' => $u->designation ?: '@'.$u->username,
            'avatar' => $u->avatarUrl(),
            'online' => $u->isOnline(),
        ])->values());
    }

    /* ------------------------------------------------------------------ */

    protected function authorizeMember(Request $request, Conversation $conversation): ConversationMember
    {
        abort_unless($conversation->is_group, 404);
        $member = $conversation->membershipFor($request->user());
        abort_unless($member, 403);

        return $member;
    }

    protected function authorizeAdmin(Request $request, Conversation $conversation): void
    {
        abort_unless($this->authorizeMember($request, $conversation)->isAdmin(), 403, 'Only group admins can do that.');
    }

    /** Tell people they were added (one alert each, in their bell). */
    protected function alertAdded(Conversation $group, User $actor, array $userIds): void
    {
        $now = now();

        Alert::insert(collect($userIds)->reject(fn ($id) => $id === $actor->id)->map(fn ($id) => [
            'user_id' => $id,
            'actor_id' => $actor->id,
            'type' => 'group_added',
            'conversation_id' => $group->id,
            'preview' => Str::limit($group->name, 70),
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all());
    }
}
