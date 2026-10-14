<?php

namespace App\Http\Controllers;

use App\Models\DriveFile;
use App\Models\DriveFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class DriveController extends Controller
{
    /**
     * Personal drive listing, either at the root or inside a folder.
     */
    public function index(Request $request, ?DriveFolder $folder = null): View
    {
        $user = $request->user();

        if ($folder) {
            abort_unless($folder->user_id === $user->id, 403);
        }

        $folders = DriveFolder::where('user_id', $user->id)
            ->where('parent_id', $folder?->id)
            ->orderBy('name')
            ->withCount(['children', 'files'])
            ->get();

        $files = DriveFile::where('user_id', $user->id)
            ->where('folder_id', $folder?->id)
            ->orderBy('name')
            ->get();

        return view('drive.index', [
            'folder' => $folder,
            'folders' => $folders,
            'files' => $files,
            'breadcrumbs' => $folder ? $folder->breadcrumbs() : [],
            'usage' => DriveFile::where('user_id', $user->id)->sum('size'),
            'totals' => [
                'files' => DriveFile::where('user_id', $user->id)->count(),
                'folders' => DriveFolder::where('user_id', $user->id)->count(),
                'public' => DriveFile::where('user_id', $user->id)->where('is_public', true)->count(),
            ],
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Folders                                                             */
    /* ------------------------------------------------------------------ */

    public function storeFolder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:drive_folders,id'],
        ]);

        $parent = $this->ownedFolder($request, $data['parent_id'] ?? null);

        $request->user()->driveFolders()->create([
            'parent_id' => $parent?->id,
            'name' => $this->cleanName($data['name']),
            // New folders inherit the privacy of the folder they live in.
            'is_public' => (bool) ($parent?->is_public),
        ]);

        return back()->with('status', 'Folder created.');
    }

    public function renameFolder(Request $request, DriveFolder $folder): RedirectResponse
    {
        abort_unless($folder->user_id === $request->user()->id, 403);

        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $folder->update(['name' => $this->cleanName($data['name'])]);

        return back()->with('status', 'Folder renamed.');
    }

    /**
     * Flip a folder between Private and Public, cascading to everything inside
     * so the public network drive never exposes a half-shared tree.
     */
    public function toggleFolder(Request $request, DriveFolder $folder): RedirectResponse
    {
        abort_unless($folder->user_id === $request->user()->id, 403);

        $folder->load('children.children');
        $ids = $folder->descendantIds();
        $makePublic = ! $folder->is_public;

        DriveFolder::whereIn('id', $ids)->update(['is_public' => $makePublic]);
        DriveFile::whereIn('folder_id', $ids)->update(['is_public' => $makePublic]);

        return back()->with('status', sprintf(
            '"%s" and its contents are now %s.',
            $folder->name,
            $makePublic ? 'public on the network drive' : 'private'
        ));
    }

    public function destroyFolder(Request $request, DriveFolder $folder): RedirectResponse
    {
        abort_unless($folder->user_id === $request->user()->id, 403);

        $folder->load('children.children');
        $ids = $folder->descendantIds();

        foreach (DriveFile::whereIn('folder_id', $ids)->get() as $file) {
            Storage::disk(DriveFile::DISK)->delete($file->path);
        }

        $parent = $folder->parent_id;
        $folder->delete();

        return redirect()
            ->route($parent ? 'drive.folder' : 'drive.index', $parent ? ['folder' => $parent] : [])
            ->with('status', 'Folder deleted.');
    }

    /* ------------------------------------------------------------------ */
    /* Files                                                               */
    /* ------------------------------------------------------------------ */

    public function storeFile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'max:51200'],
            'folder_id' => ['nullable', 'integer', 'exists:drive_folders,id'],
        ]);

        $user = $request->user();
        $folder = $this->ownedFolder($request, $data['folder_id'] ?? null);
        $stored = 0;

        foreach ($request->file('files') as $upload) {
            $original = $upload->getClientOriginalName();
            $path = $upload->store('drive/'.$user->id, DriveFile::DISK);

            $user->driveFiles()->create([
                'folder_id' => $folder?->id,
                'name' => $this->cleanName($original),
                'path' => $path,
                'mime' => $upload->getClientMimeType(),
                'size' => $upload->getSize(),
                // Private by default, unless dropped into a public folder.
                'is_public' => (bool) ($folder?->is_public),
            ]);

            $stored++;
        }

        return back()->with('status', $stored.' '.Str::plural('file', $stored).' uploaded.');
    }

    public function renameFile(Request $request, DriveFile $file): RedirectResponse
    {
        abort_unless($file->user_id === $request->user()->id, 403);

        $data = $request->validate(['name' => ['required', 'string', 'max:160']]);

        $file->update(['name' => $this->cleanName($data['name'])]);

        return back()->with('status', 'File renamed.');
    }

    public function toggleFile(Request $request, DriveFile $file): RedirectResponse
    {
        abort_unless($file->user_id === $request->user()->id, 403);

        $file->update(['is_public' => ! $file->is_public]);

        return back()->with('status', sprintf(
            '"%s" is now %s.',
            $file->name,
            $file->is_public ? 'public' : 'private'
        ));
    }

    public function destroyFile(Request $request, DriveFile $file): RedirectResponse
    {
        abort_unless($file->user_id === $request->user()->id, 403);

        Storage::disk(DriveFile::DISK)->delete($file->path);
        $file->delete();

        return back()->with('status', 'File deleted.');
    }

    /* ------------------------------------------------------------------ */
    /* Serving                                                             */
    /* ------------------------------------------------------------------ */

    public function preview(Request $request, DriveFile $file): Response|BinaryFileResponse
    {
        $this->authorizeRead($request, $file);

        $absolute = Storage::disk(DriveFile::DISK)->path($file->path);
        abort_unless(is_file($absolute), 404);

        return response()->file($absolute, [
            'Content-Type' => $file->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($file->name).'"',
        ]);
    }

    public function download(Request $request, DriveFile $file): Response
    {
        $this->authorizeRead($request, $file);

        abort_unless(Storage::disk(DriveFile::DISK)->exists($file->path), 404);

        return Storage::disk(DriveFile::DISK)->download($file->path, $file->name);
    }

    /**
     * Public files are open to the whole network; private ones only to the owner.
     */
    protected function authorizeRead(Request $request, DriveFile $file): void
    {
        if ($file->is_public) {
            return;
        }

        abort_unless($request->user() && $request->user()->id === $file->user_id, 403, 'This file is private.');
    }

    /* ------------------------------------------------------------------ */

    protected function ownedFolder(Request $request, $id): ?DriveFolder
    {
        if (! $id) {
            return null;
        }

        $folder = DriveFolder::find($id);

        abort_unless($folder && $folder->user_id === $request->user()->id, 403);

        return $folder;
    }

    /**
     * Keep names safe for display and for Content-Disposition headers.
     */
    protected function cleanName(string $name): string
    {
        $name = str_replace(['/', '\\', "\r", "\n", '"'], '-', trim($name));

        return $name === '' ? 'Untitled' : Str::limit($name, 150, '');
    }
}
