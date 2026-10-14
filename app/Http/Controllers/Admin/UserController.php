<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Message;
use App\Models\User;
use App\Support\RandomCover;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    protected const SORTS = [
        'newest' => ['created_at', 'desc'],
        'oldest' => ['created_at', 'asc'],
        'name' => ['name', 'asc'],
        'active' => ['last_seen_at', 'desc'],
        'posts' => ['posts_count', 'desc'],
    ];

    public function index(Request $request): View
    {
        $users = $this->filtered($request)
            ->withCount(['posts', 'movies', 'songs', 'driveFiles'])
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'counts' => [
                'all' => User::count(),
                'admins' => User::where('is_admin', true)->count(),
                'suspended' => User::whereNotNull('suspended_at')->count(),
                'online' => User::online()->count(),
            ],
        ]);
    }

    /** The current filter as a CSV download. */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->withCount(['posts', 'movies', 'songs', 'driveFiles']);

        AdminLog::record('users.export', 'Exported the member list as CSV');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Name', 'Username', 'Designation', 'Role', 'Status', 'Posts', 'Movies', 'Songs', 'Drive files', 'Joined', 'Last seen']);

            $query->chunk(200, function ($users) use ($out) {
                foreach ($users as $u) {
                    fputcsv($out, [
                        $u->id, $u->name, $u->username, $u->designation,
                        $u->is_admin ? 'Admin' : 'Member',
                        $u->isSuspended() ? 'Suspended' : 'Active',
                        $u->posts_count, $u->movies_count, $u->songs_count, $u->drive_files_count,
                        $u->created_at?->toDateTimeString(), $u->last_seen_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($out);
        }, 'savermate-members-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Admins can create accounts directly, which matters once registration is closed. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users')],
            'password' => ['required', 'string', 'min:8'],
            'designation' => ['nullable', 'string', 'max:120'],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        $user = new User($data);
        $user->is_admin = $request->boolean('is_admin');
        $user->save();

        RandomCover::assign($user);

        AdminLog::record('users.create', "Created account @{$user->username}".($user->is_admin ? ' (admin)' : ''));

        return redirect()->route('admin.users.show', $user)->with('status', "Account @{$user->username} created.");
    }

    public function show(User $user): View
    {
        $user->loadCount(['posts', 'comments', 'reactions', 'movies', 'songs', 'playlists', 'driveFiles']);

        return view('admin.users.show', [
            'user' => $user,
            'messagesSent' => Message::where('sender_id', $user->id)->count(),
            'storage' => (int) $user->driveFiles()->sum('size') + (int) $user->movies()->sum('video_size') + (int) $user->songs()->sum('size'),
            'posts' => $user->posts()->withCount(['comments', 'reactions'])->limit(8)->get(),
            'movies' => $user->movies()->limit(6)->get(),
            'songs' => $user->songs()->limit(6)->get(),
            'files' => $user->driveFiles()->latest()->limit(8)->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users')->ignore($user->id)],
            'designation' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        $makeAdmin = $request->boolean('is_admin');

        if ($user->is_admin && ! $makeAdmin) {
            $this->guardLastAdmin($user, 'remove admin rights from');
        }

        $roleChanged = $user->is_admin !== $makeAdmin;
        $user->fill($data);
        $user->is_admin = $makeAdmin;
        $changes = array_keys($user->getDirty());
        $user->save();

        if ($changes) {
            AdminLog::record(
                $roleChanged ? 'users.role' : 'users.update',
                "Updated @{$user->username}: ".implode(', ', $changes).($roleChanged ? ($makeAdmin ? ' (granted admin)' : ' (revoked admin)') : '')
            );
        }

        return back()->with('status', 'Member details saved.');
    }

    public function password(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'confirmed']]);

        $user->password = $data['password'];
        $user->setRememberToken(Str::random(60)); // signs out "remember me" sessions
        $user->save();

        AdminLog::record('users.password', "Reset the password of @{$user->username}");

        return back()->with('status', "Password for @{$user->username} has been reset.");
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $this->refuseIf($user->is($request->user()), 'You cannot suspend yourself.');
        $this->guardLastAdmin($user, 'suspend');

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        $user->forceFill([
            'suspended_at' => now(),
            'suspension_reason' => $data['reason'] ?? null,
        ])->save();

        AdminLog::record('users.suspend', "Suspended @{$user->username}".(! empty($data['reason']) ? ": {$data['reason']}" : ''));

        return back()->with('status', "@{$user->username} is suspended and has been signed out.");
    }

    public function unsuspend(User $user): RedirectResponse
    {
        $user->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();

        AdminLog::record('users.unsuspend', "Lifted the suspension on @{$user->username}");

        return back()->with('status', "@{$user->username} can sign in again.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->refuseIf($user->is($request->user()), 'You cannot delete your own account from here.');
        $this->guardLastAdmin($user, 'delete');

        $label = "{$user->name} (@{$user->username})";
        $user->deleteWithFiles();

        AdminLog::record('users.delete', "Deleted the account of {$label} and all their content");

        return redirect()->route('admin.users.index')->with('status', "{$label} and everything they shared has been deleted.");
    }

    /** Suspend, reinstate or delete several members at once. */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['suspend', 'unsuspend', 'delete'])],
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        // Never act on yourself, or on admins in bulk: those are deliberate one-by-one decisions.
        $users = User::whereIn('id', $data['ids'])
            ->whereKeyNot($request->user()->id)
            ->where('is_admin', false)
            ->get();

        foreach ($users as $user) {
            match ($data['action']) {
                'suspend' => $user->forceFill(['suspended_at' => $user->suspended_at ?? now()])->save(),
                'unsuspend' => $user->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save(),
                'delete' => $user->deleteWithFiles(),
            };
        }

        $verb = ['suspend' => 'Suspended', 'unsuspend' => 'Reinstated', 'delete' => 'Deleted'][$data['action']];
        $names = $users->map(fn ($u) => '@'.$u->username)->implode(', ');

        if ($users->isNotEmpty()) {
            AdminLog::record('users.bulk-'.$data['action'], "{$verb} {$users->count()} members: {$names}");
        }

        $skipped = count($data['ids']) - $users->count();

        return back()->with('status', "{$verb} ".$users->count().' '.Str::plural('member', $users->count()).'.'
            .($skipped ? " Skipped {$skipped} (admins and your own account are never changed in bulk)." : ''));
    }

    /* ------------------------------------------------------------------ */

    protected function filtered(Request $request)
    {
        $query = User::query();

        if ($term = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%")
                    ->orWhere('designation', 'like', "%{$term}%")
                    ->orWhere('skills', 'like', "%{$term}%");
            });
        }

        match ($request->query('filter')) {
            'admins' => $query->where('is_admin', true),
            'suspended' => $query->whereNotNull('suspended_at'),
            'online' => $query->online(),
            'inactive' => $query->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subDays(30))),
            default => null,
        };

        [$column, $direction] = self::SORTS[$request->query('sort')] ?? self::SORTS['newest'];

        return $query->orderBy($column, $direction)->orderBy('id', 'desc');
    }

    /** There must always be at least one active admin left to run the network. */
    protected function guardLastAdmin(User $user, string $verb): void
    {
        $this->refuseIf(
            $user->is_admin && User::where('is_admin', true)->whereNull('suspended_at')->whereKeyNot($user->id)->doesntExist(),
            "You cannot {$verb} the last active administrator. Make someone else an admin first."
        );
    }

    /** Sends the admin back to the form with the reason shown as an error. */
    protected function refuseIf(bool $condition, string $message): void
    {
        if ($condition) {
            throw ValidationException::withMessages(['user' => $message]);
        }
    }
}
