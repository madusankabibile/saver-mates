<?php

namespace App\Http\Controllers;

use App\Models\DriveFile;
use App\Models\DriveFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicDriveController extends Controller
{
    /**
     * The shared network drive: every file or folder a colleague has marked
     * public, browsable and searchable by the whole network (guests included).
     */
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $folderId = $request->query('folder');
        $folder = null;

        if ($folderId) {
            $folder = DriveFolder::where('is_public', true)->with('user')->find($folderId);
            abort_unless($folder, 404);
        }

        if ($term !== '') {
            // Search spans the whole public drive rather than one folder.
            $folders = DriveFolder::where('is_public', true)
                ->where('name', 'like', "%{$term}%")
                ->with('user')
                ->withCount(['children', 'files'])
                ->orderBy('name')
                ->limit(40)
                ->get();

            $files = DriveFile::where('is_public', true)
                ->where('name', 'like', "%{$term}%")
                ->with('user')
                ->orderBy('name')
                ->paginate(24)
                ->withQueryString();
        } else {
            $folders = DriveFolder::where('is_public', true)
                ->where(function ($q) use ($folder) {
                    if ($folder) {
                        $q->where('parent_id', $folder->id);
                    } else {
                        // Roots of the public drive: either a top level folder, or
                        // the first public folder in an otherwise private branch.
                        $q->whereNull('parent_id')
                            ->orWhereHas('parent', fn ($p) => $p->where('is_public', false));
                    }
                })
                ->with('user')
                ->withCount(['children', 'files'])
                ->orderBy('name')
                ->get();

            $files = DriveFile::where('is_public', true)
                ->where('folder_id', $folder?->id)
                ->with('user')
                ->orderBy('name')
                ->paginate(24)
                ->withQueryString();
        }

        return view('drive.public', [
            'term' => $term,
            'folder' => $folder,
            'folders' => $folders,
            'files' => $files,
            'breadcrumbs' => $folder ? array_filter($folder->breadcrumbs(), fn ($f) => $f->is_public) : [],
            'totalPublicFiles' => DriveFile::where('is_public', true)->count(),
            'totalPublicSize' => DriveFile::where('is_public', true)->sum('size'),
            'contributors' => DriveFile::where('is_public', true)->distinct('user_id')->count('user_id'),
        ]);
    }

    /**
     * Create a new folder on the network drive (automatically public).
     */
    public function storeFolder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $request->user()->driveFolders()->create([
            'parent_id' => null,
            'name' => $this->cleanName($data['name']),
            'is_public' => true, // Always public on network drive
        ]);

        return back()->with('status', 'Public folder created.');
    }

    /**
     * Upload files to the network drive (automatically public).
     */
    public function storeFile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'max:51200'],
        ]);

        $user = $request->user();
        $stored = 0;

        foreach ($request->file('files') as $upload) {
            $original = $upload->getClientOriginalName();
            $path = $upload->store('drive/'.$user->id, DriveFile::DISK);

            $user->driveFiles()->create([
                'folder_id' => null, // Root level on network drive
                'name' => $this->cleanName($original),
                'path' => $path,
                'mime' => $upload->getClientMimeType(),
                'size' => $upload->getSize(),
                'is_public' => true, // Always public on network drive
            ]);

            $stored++;
        }

        return back()->with('status', $stored.' '.Str::plural('file', $stored).' uploaded and published.');
    }

    /**
     * Upload an entire folder with its structure to the network drive (automatically public).
     */
    public function uploadFolder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'folder_files' => ['required', 'array'],
            'folder_files.*' => ['file', 'max:51200'],
            'folder_paths' => ['required', 'array'],
            'folder_paths.*' => ['string'],
        ]);

        $user = $request->user();
        $files = $request->file('folder_files');
        $paths = $request->input('folder_paths');

        // Track created folders to avoid duplicates
        $folderCache = [];
        $filesUploaded = 0;
        $foldersCreated = 0;

        foreach ($files as $index => $upload) {
            if (!$upload) {
                continue;
            }

            $relativePath = $paths[$index] ?? '';

            // Skip if no path
            if (empty($relativePath)) {
                continue;
            }

            // Extract directory path and filename
            $pathParts = explode('/', str_replace('\\', '/', $relativePath));
            $filename = array_pop($pathParts);

            // Filter out empty parts
            $pathParts = array_filter($pathParts, fn($part) => !empty(trim($part)));

            // Create nested folder structure
            $parentId = null;
            $currentPath = '';

            foreach ($pathParts as $folderName) {
                $cleanFolderName = $this->cleanName($folderName);
                if (empty($cleanFolderName)) {
                    continue;
                }

                $currentPath .= '/' . $cleanFolderName;

                // Check if we already created this folder
                if (!isset($folderCache[$currentPath])) {
                    $folder = $user->driveFolders()->create([
                        'parent_id' => $parentId,
                        'name' => $cleanFolderName,
                        'is_public' => true, // Always public on network drive
                    ]);

                    $folderCache[$currentPath] = $folder->id;
                    $foldersCreated++;
                }

                $parentId = $folderCache[$currentPath];
            }

            // Upload the file
            $storagePath = $upload->store('drive/'.$user->id, DriveFile::DISK);

            $user->driveFiles()->create([
                'folder_id' => $parentId,
                'name' => $this->cleanName($filename),
                'path' => $storagePath,
                'mime' => $upload->getClientMimeType(),
                'size' => $upload->getSize(),
                'is_public' => true, // Always public on network drive
            ]);

            $filesUploaded++;
        }

        $message = $filesUploaded.' '.Str::plural('file', $filesUploaded);
        if ($foldersCreated > 0) {
            $message .= ' and '.$foldersCreated.' '.Str::plural('folder', $foldersCreated);
        }
        $message .= ' uploaded and published.';

        return back()->with('status', $message);
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
