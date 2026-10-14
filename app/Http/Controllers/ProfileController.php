<?php

namespace App\Http\Controllers;

use App\Models\DriveFile;
use App\Models\DriveFolder;
use App\Models\Post;
use App\Models\User;
use App\Support\ImageStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * A member's public profile page with their posts and activity stats.
     */
    public function show(Request $request, User $user): View
    {
        $posts = $user->posts()
            ->with(['images', 'reactions', 'rootComments.user', 'rootComments.replies.user', 'user'])
            ->withCount('comments')
            ->paginate(10);

        $shared = $this->sharedTree($user);

        return view('profile.show', [
            'profile' => $user,
            'posts' => $posts,
            'sharedRoots' => $shared['roots'],
            'looseFiles' => $shared['loose'],
            'sharedFileCount' => $shared['fileCount'],
            'sharedFolderCount' => $shared['folderCount'],
            'sharedSize' => $shared['size'],
            'stats' => [
                'posts' => $user->posts()->count(),
                'comments' => $user->comments()->count(),
                'reactions_given' => $user->reactions()->count(),
                'reactions_received' => \App\Models\Reaction::whereIn(
                    'post_id', Post::where('user_id', $user->id)->select('id')
                )->count(),
                'files' => $user->driveFiles()->count(),
                'public_files' => $shared['fileCount'],
            ],
        ]);
    }

    /**
     * Everything this member has published, assembled into a folder tree.
     *
     * Two queries then grouped in PHP, so a deep tree costs no more than a flat
     * one. Each folder carries its public children and files; files that are
     * public but sit outside any public folder are returned separately.
     *
     * @return array{roots: \Illuminate\Support\Collection, loose: \Illuminate\Support\Collection, fileCount: int, folderCount: int, size: int}
     */
    protected function sharedTree(User $user): array
    {
        $folders = DriveFolder::where('user_id', $user->id)
            ->where('is_public', true)
            ->orderBy('name')
            ->get();

        $files = DriveFile::where('user_id', $user->id)
            ->where('is_public', true)
            ->orderBy('name')
            ->get();

        $publicFolderIds = $folders->pluck('id')->flip();
        $childrenOf = $folders->groupBy('parent_id');
        $filesOf = $files->groupBy('folder_id');

        $attach = function (DriveFolder $folder) use (&$attach, $childrenOf, $filesOf) {
            $folder->setRelation(
                'children',
                collect($childrenOf[$folder->id] ?? [])->each(fn ($child) => $attach($child))
            );
            $folder->setRelation('files', collect($filesOf[$folder->id] ?? []));

            return $folder;
        };

        // A folder is a root here when it is public but its parent is not, so a
        // shared branch inside a private tree still shows up once at its top.
        $roots = $folders
            ->filter(fn ($f) => $f->parent_id === null || ! $publicFolderIds->has($f->parent_id))
            ->each(fn ($f) => $attach($f))
            ->values();

        $loose = $files
            ->filter(fn ($f) => $f->folder_id === null || ! $publicFolderIds->has($f->folder_id))
            ->values();

        return [
            'roots' => $roots,
            'loose' => $loose,
            'fileCount' => $files->count(),
            'folderCount' => $folders->count(),
            'size' => (int) $files->sum('size'),
        ];
    }

    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:160', Rule::unique('users')->ignore($user->id)],
            'designation' => ['nullable', 'string', 'max:120'],
            'qualifications' => ['nullable', 'string', 'max:1000'],
            'skills' => ['nullable', 'string', 'max:1000'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'max:4096'],
            'cover' => ['nullable', 'image', 'max:8192'],
        ], [
            'username.regex' => 'Usernames may only contain letters, numbers, dots, dashes and underscores.',
        ]);

        if ($request->hasFile('avatar')) {
            $old = $user->avatar;
            $data['avatar'] = ImageStore::put($request->file('avatar'), 'avatars', 600, 600);

            if ($old) {
                Storage::disk('public')->delete($old);
            }
        }

        if ($request->hasFile('cover')) {
            $old = $user->cover;
            $data['cover'] = ImageStore::put($request->file('cover'), 'covers', 2000, 700);

            if ($old) {
                Storage::disk('public')->delete($old);
            }
        }

        $user->update($data);

        return back()->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That is not your current password.',
            ]);
        }

        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Password changed.');
    }
}
