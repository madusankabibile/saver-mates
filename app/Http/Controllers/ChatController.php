<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Conversation;
use App\Models\DriveFile;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(Request $request): View
    {
        return view('chat.index', [
            'threads' => $this->threadsFor($request->user()),
            'conversation' => null,
            'partner' => null,
            'messages' => collect(),
        ]);
    }

    /**
     * Jump straight into a private chat with a colleague from the directory.
     */
    public function with(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return redirect()->route('chat.index')
                ->with('status', 'You cannot start a chat with yourself.');
        }

        return redirect()->route('chat.show', Conversation::between($request->user(), $user));
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $me = $request->user();
        abort_unless($conversation->includes($me), 403);

        $conversation->markReadFor($me);

        if ($conversation->is_group) {
            $conversation->load(['members' => fn ($q) => $q->with('user')->orderByRaw("role = 'admin' desc")->orderBy('id')]);
        }

        return view('chat.index', [
            'threads' => $this->threadsFor($me),
            'conversation' => $conversation,
            'partner' => $conversation->partnerFor($me),
            'membership' => $conversation->membershipFor($me),
            'readers' => $this->readPointers($conversation, $me),
            'messages' => $conversation->messages()->with(['sender', 'attachments'])->get(),
            'driveFiles' => DriveFile::where('user_id', $me->id)->orderBy('name')->limit(200)->get(),
        ]);
    }

    public function send(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        $me = $request->user();
        abort_unless($conversation->includes($me), 403);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:4000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:51200'],
            'drive_files' => ['nullable', 'array', 'max:10'],
            'drive_files.*' => ['integer', 'exists:drive_files,id'],
        ]);

        $uploads = $request->file('attachments') ?: [];
        $drivePicks = $data['drive_files'] ?? [];

        if (blank($data['body'] ?? null) && empty($uploads) && empty($drivePicks)) {
            throw ValidationException::withMessages(['body' => 'Type a message or attach a file.']);
        }

        $message = $conversation->messages()->create([
            'sender_id' => $me->id,
            'body' => $data['body'] ?? null,
        ]);

        /* Files pushed straight from the device ------------------------- */
        foreach ($uploads as $upload) {
            $path = $upload->store('chat/'.$conversation->id, MessageAttachment::DISK);

            $message->attachments()->create([
                'source' => 'upload',
                'name' => $this->cleanName($upload->getClientOriginalName()),
                'path' => $path,
                'mime' => $upload->getClientMimeType(),
                'size' => $upload->getSize(),
            ]);
        }

        /* Files linked from the sender's SaverMate Drive - no re-upload -- */
        foreach ($drivePicks as $id) {
            // Your own files, or anything published to the network drive.
            $file = DriveFile::where(fn ($q) => $q->where('user_id', $me->id)->orWhere('is_public', true))->find($id);

            if (! $file) {
                continue;
            }

            $message->attachments()->create([
                'source' => 'drive',
                'drive_file_id' => $file->id,
                'name' => $file->name,
                'path' => $file->path,
                'mime' => $file->mime,
                'size' => $file->size,
            ]);
        }

        $conversation->update(['last_message_at' => now()]);
        $preview = Str::limit($data['body'] ?? '[attachment]', 70);

        if ($conversation->is_group) {
            // Your own message means you have read everything before it.
            $conversation->members()->where('user_id', $me->id)->update(['last_read_message_id' => $message->id]);
            $this->notifyGroup($conversation, $me, $preview);
        } else {
            Alert::record([
                'user_id' => $conversation->partnerFor($me)->id,
                'actor_id' => $me->id,
                'type' => 'message',
                'conversation_id' => $conversation->id,
                'preview' => $preview,
            ]);
        }

        if ($request->wantsJson()) {
            $message->load(['sender', 'attachments']);

            return response()->json([
                'html' => view('chat.partials.bubble', [
                    'message' => $message,
                    'me' => $me,
                    'group' => $conversation->is_group,
                    'readers' => $this->readPointers($conversation, $me),
                ])->render(),
                'id' => $message->id,
            ]);
        }

        return back();
    }

    /**
     * Polled by the open chat window for messages newer than what it has.
     */
    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        $me = $request->user();
        abort_unless($conversation->includes($me), 403);

        $after = (int) $request->query('after', 0);

        $fresh = $conversation->messages()
            ->with(['sender', 'attachments'])
            ->where('id', '>', $after)
            ->get();

        if ($fresh->isNotEmpty()) {
            $conversation->markReadFor($me);
        }

        if ($conversation->is_group) {
            [$online, $status] = $this->groupStatus($conversation, $me);
        } else {
            $partner = $conversation->partnerFor($me);
            [$online, $status] = [(bool) $partner?->isOnline(), $partner?->lastSeenLabel()];
        }

        $readers = $this->readPointers($conversation, $me);

        // Messages already on screen that were edited or deleted since the
        // window last asked. A second of overlap covers clock rounding.
        $since = $this->sinceParam($request);
        $changed = $since ? $conversation->messages()
            ->with(['sender', 'attachments'])
            ->where('id', '<=', $after)
            ->where(fn ($q) => $q->where('edited_at', '>=', $since)->orWhere('removed_at', '>=', $since))
            ->get() : collect();

        return response()->json([
            'now' => now()->subSecond()->toIso8601String(),
            'changed' => $changed->map(fn (Message $m) => [
                'id' => $m->id,
                'html' => view('chat.partials.bubble', [
                    'message' => $m,
                    'me' => $me,
                    'group' => $conversation->is_group,
                    'readers' => $readers,
                ])->render(),
            ])->values(),
            'last_id' => $fresh->max('id') ?: $after,
            'html' => $fresh->map(fn (Message $m) => view('chat.partials.bubble', [
                'message' => $m,
                'me' => $me,
                'group' => $conversation->is_group,
                'readers' => $readers,
            ])->render())->values(),
            'partner_online' => $online,
            'partner_status' => $status,
            // Read receipts for messages already on screen. Private chat: your
            // messages up to this id have been read. Group: each other member's
            // read position; a message is read by everyone whose position >= its id.
            'read_upto' => $conversation->is_group ? null : (int) $conversation->messages()
                ->where('sender_id', $me->id)->whereNotNull('read_at')->max('id'),
            'readers' => $readers,
        ]);
    }

    /**
     * Message info for the sender: when it was sent, edited and read. In a
     * group, who has read it and who has not yet.
     */
    public function info(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $me = $this->authorizeOwnMessage($request, $conversation, $message);

        $info = [
            'sent' => $message->created_at->format('D j M Y, g:i a'),
            'edited' => $message->edited_at?->format('D j M Y, g:i a'),
            'group' => $conversation->is_group,
        ];

        if (! $conversation->is_group) {
            return response()->json($info + [
                'read' => $message->read_at?->format('D j M Y, g:i a'),
                'partner' => $conversation->partnerFor($me)?->name,
            ]);
        }

        // Only people who were in the group when it was sent count.
        $members = $conversation->members()->where('user_id', '!=', $me->id)->with('user')->get()
            ->filter(fn ($m) => $m->user && (int) $m->joined_after_message_id < $message->id);

        $row = fn ($m) => [
            'name' => $m->user->name,
            'meta' => $m->user->designation ?: '@'.$m->user->username,
            'avatar' => $m->user->avatarUrl(),
        ];

        [$read, $unread] = $members->partition(fn ($m) => (int) $m->last_read_message_id >= $message->id);

        return response()->json($info + [
            'readers' => $read->map($row)->values(),
            'waiting' => $unread->map($row)->values(),
        ]);
    }

    /** Edit the text of your own message. */
    public function update(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $me = $this->authorizeOwnMessage($request, $conversation, $message);

        $data = $request->validate(['body' => ['nullable', 'string', 'max:4000']]);
        $body = trim((string) ($data['body'] ?? ''));

        if ($body === '' && ! $message->attachments()->exists()) {
            throw ValidationException::withMessages(['body' => 'A message cannot be empty. Delete it instead.']);
        }

        if ($body !== (string) $message->body) {
            $old = (string) $message->body;
            $message->forceFill(['body' => $body === '' ? null : $body, 'edited_at' => now()])->save();
            $this->refreshAlertPreview($conversation, $me, $old, Str::limit($body ?: '[attachment]', 70));
        }

        return $this->bubbleJson($conversation, $message, $me);
    }

    /** Delete your own message for everyone. */
    public function destroyMessage(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $me = $this->authorizeOwnMessage($request, $conversation, $message);

        $old = (string) $message->body;
        $message->removeForEveryone();
        $this->refreshAlertPreview($conversation, $me, $old, 'Message deleted');

        return $this->bubbleJson($conversation, $message, $me);
    }

    /**
     * Unread message badge for the top bar.
     */
    public function unread(Request $request): JsonResponse
    {
        $me = $request->user();

        $private = Message::whereIn('conversation_id', Conversation::where('is_group', false)
            ->where(fn ($q) => $q->where('user_one_id', $me->id)->orWhere('user_two_id', $me->id))
            ->select('id'))
            ->where('sender_id', '!=', $me->id)
            ->whereNull('read_at')->whereNull('removed_at')
            ->count();

        // Muted groups stay out of the badge.
        $groups = Message::join('conversation_members as cm', function ($join) use ($me) {
            $join->on('cm.conversation_id', '=', 'messages.conversation_id')->where('cm.user_id', $me->id);
        })
            ->where('messages.sender_id', '!=', $me->id)
            ->whereNull('messages.event')->whereNull('messages.removed_at')
            ->whereRaw('messages.id > COALESCE(cm.last_read_message_id, 0)')
            ->where(fn ($q) => $q->whereNull('cm.muted_until')->orWhere('cm.muted_until', '<=', now()))
            ->count();

        return response()->json(['count' => $private + $groups]);
    }

    /**
     * Feeds the "Attach from Cloud Drive" picker.
     */
    /**
     * Files that can be attached from SaverMate Drive: everything in your own
     * drive (private or public) plus every file colleagues have published to
     * the network drive. ?scope=mine|network narrows it; ?q searches names.
     */
    public function drivePicker(Request $request): JsonResponse
    {
        $me = $request->user();
        $term = trim((string) $request->query('q'));
        $scope = in_array($request->query('scope'), ['mine', 'network'], true) ? $request->query('scope') : 'all';

        $search = fn ($q) => $q->when($term !== '', fn ($q) => $q->where('name', 'like', "%{$term}%"));

        $mine = $scope === 'network' ? collect() : DriveFile::where('user_id', $me->id)
            ->tap($search)
            ->with('folder')
            ->orderBy('name')
            ->limit(60)
            ->get();

        $network = $scope === 'mine' ? collect() : DriveFile::where('is_public', true)
            ->where('user_id', '!=', $me->id)
            ->tap($search)
            ->with(['folder', 'user'])
            ->orderBy('name')
            ->limit(60)
            ->get();

        $row = fn (DriveFile $f) => [
            'id' => $f->id,
            'name' => $f->name,
            'size' => $f->readableSize(),
            'folder' => $f->folder?->name ?? ($f->user_id === $me->id ? 'My Drive' : 'Network Drive'),
            'owner' => $f->user_id === $me->id ? null : $f->user?->name,
            'glyph' => $f->badge()['glyph'],
            'color' => $f->badge()['color'],
            'is_public' => $f->is_public,
        ];

        return response()->json([
            'mine' => $mine->map($row)->values(),
            'network' => $network->map($row)->values(),
        ]);
    }

    public function attachment(Request $request, MessageAttachment $attachment)
    {
        $this->authorizeAttachment($request, $attachment);

        return Storage::disk(MessageAttachment::DISK)->download($attachment->path, $attachment->name);
    }

    /**
     * Same file served inline so image bubbles and previews can render it.
     */
    public function attachmentInline(Request $request, MessageAttachment $attachment)
    {
        $this->authorizeAttachment($request, $attachment);

        return response()->file(
            Storage::disk(MessageAttachment::DISK)->path($attachment->path),
            [
                'Content-Type' => $attachment->mime ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.addslashes($attachment->name).'"',
            ]
        );
    }

    /**
     * Chat files are readable only by the two people in the conversation,
     * whatever the privacy flag on the originating drive file says.
     */
    protected function authorizeAttachment(Request $request, MessageAttachment $attachment): void
    {
        abort_unless($attachment->message->conversation->includes($request->user()), 403);
        abort_unless(
            $attachment->path && Storage::disk(MessageAttachment::DISK)->exists($attachment->path),
            404
        );
    }

    /* ------------------------------------------------------------------ */

    /**
     * Conversation list ordered by most recent activity. For groups only the
     * viewer's own membership row is loaded (role, read position, mute).
     */
    protected function threadsFor(User $user)
    {
        return Conversation::for($user)
            ->with(['userOne', 'userTwo', 'latestMessage.sender', 'members' => fn ($q) => $q->where('user_id', $user->id)])
            ->withCount('members')
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function (Conversation $c) use ($user) {
                $c->setAttribute('partner', $c->partnerFor($user));
                $c->setAttribute('unread', $c->unreadCountFor($user));
                $c->setAttribute('muted', (bool) $c->membershipFor($user)?->isMuted());

                return $c;
            });
    }

    protected function sinceParam(Request $request): ?\Illuminate\Support\Carbon
    {
        try {
            return $request->filled('since') ? \Illuminate\Support\Carbon::parse($request->query('since'))->setTimezone(config('app.timezone')) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function authorizeOwnMessage(Request $request, Conversation $conversation, Message $message): User
    {
        $me = $request->user();
        abort_unless($conversation->includes($me), 403);
        abort_unless($message->conversation_id === $conversation->id && $message->isManageableBy($me), 404);

        return $me;
    }

    protected function bubbleJson(Conversation $conversation, Message $message, User $me): JsonResponse
    {
        $message->load(['sender', 'attachments']);

        return response()->json([
            'id' => $message->id,
            'html' => view('chat.partials.bubble', [
                'message' => $message,
                'me' => $me,
                'group' => $conversation->is_group,
                'readers' => $this->readPointers($conversation, $me),
            ])->render(),
        ]);
    }

    /**
     * A waiting bell notification still quoting the old text of an edited or
     * deleted message is brought up to date.
     */
    protected function refreshAlertPreview(Conversation $conversation, User $sender, string $oldBody, string $preview): void
    {
        if ($oldBody === '') {
            return;
        }

        Alert::where('conversation_id', $conversation->id)
            ->where('actor_id', $sender->id)
            ->whereNull('read_at')
            ->where('preview', Str::limit($oldBody, 70))
            ->update(['preview' => $preview]);
    }

    /**
     * Group read receipts: one [joined after, read up to] pair of message ids
     * per other member. A message counts a member once they joined before it
     * was sent, and as read once their read position reaches it. Null for a
     * private chat, which uses each message's read_at instead.
     *
     * @return array<int, array{0: int, 1: int}>|null
     */
    protected function readPointers(Conversation $conversation, User $me): ?array
    {
        if (! $conversation->is_group) {
            return null;
        }

        return $conversation->members()->where('user_id', '!=', $me->id)
            ->get(['joined_after_message_id', 'last_read_message_id'])
            ->map(fn ($m) => [(int) $m->joined_after_message_id, (int) $m->last_read_message_id])
            ->all();
    }

    /** "8 members, 3 online" for the group header. */
    protected function groupStatus(Conversation $group, User $me): array
    {
        $users = $group->users()->get(['users.id', 'users.last_seen_at']);
        $online = $users->filter(fn (User $u) => $u->id !== $me->id && $u->isOnline())->count();

        return [
            $online > 0,
            $users->count().' '.Str::plural('member', $users->count()).($online ? ', '.$online.' online' : ''),
        ];
    }

    /**
     * One unread alert per group per member: a busy group refreshes the
     * waiting alert instead of filling the bell. Muted members get nothing.
     */
    protected function notifyGroup(Conversation $group, User $sender, string $preview): void
    {
        $recipients = $group->members()->audible()->where('user_id', '!=', $sender->id)->pluck('user_id');

        if ($recipients->isEmpty()) {
            return;
        }

        $now = now();
        $waiting = Alert::where('conversation_id', $group->id)
            ->where('type', 'group_message')
            ->whereNull('read_at')
            ->whereIn('user_id', $recipients);

        $refresh = (clone $waiting)->pluck('user_id');
        (clone $waiting)->update(['actor_id' => $sender->id, 'preview' => $preview, 'created_at' => $now, 'updated_at' => $now]);

        Alert::insert($recipients->diff($refresh)->map(fn ($id) => [
            'user_id' => $id,
            'actor_id' => $sender->id,
            'type' => 'group_message',
            'conversation_id' => $group->id,
            'preview' => $preview,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all());
    }

    protected function cleanName(string $name): string
    {
        $name = str_replace(['/', '\\', "\r", "\n", '"'], '-', trim($name));

        return $name === '' ? 'attachment' : Str::limit($name, 150, '');
    }
}
