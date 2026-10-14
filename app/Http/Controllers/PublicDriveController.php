<?php

namespace App\Http\Controllers;

use App\Models\DriveFile;
use App\Models\DriveFolder;
use Illuminate\Http\Request;
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
}
