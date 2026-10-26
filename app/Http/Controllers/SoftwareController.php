<?php

namespace App\Http\Controllers;

use App\Models\Software;
use App\Support\ChunkedUpload;
use App\Support\SoftwareLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SoftwareController extends Controller
{
    public function index(Request $request): View
    {
        $term   = trim((string) $request->query('q'));
        $osType = $request->query('os_type');
        $sort   = in_array($request->query('sort'), ['popular', 'liked'], true) ? $request->query('sort') : 'latest';

        $software = Software::with('user')
            ->withCount('likers')
            ->when($term, fn ($q) => $q->search($term))
            ->when($osType, fn ($q) => $q->where('os_type', $osType))
            ->when($sort === 'popular', fn ($q) => $q->orderByDesc('downloads'))
            ->when($sort === 'liked',   fn ($q) => $q->orderByDesc('likers_count'))
            ->latest()
            ->paginate(24)
            ->withQueryString();

        $totalSize = Software::sum('file_size');

        return view('software.index', [
            'software'      => $software,
            'term'          => $term,
            'osType'        => $osType,
            'sort'          => $sort,
            'osTypes'       => Software::OS_TYPES,
            'trending'      => Software::with('user')->orderByDesc('downloads')->latest()->limit(5)->get(),
            'totalSoftware' => Software::count(),
            'totalSize'     => $totalSize,
            'myCount'       => $request->user()?->software()->count() ?? 0,
        ]);
    }

    public function create(): View
    {
        return view('software.create', [
            'osTypes'    => Software::OS_TYPES,
            'categories' => Software::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'upload_id'    => ['required', 'string', 'regex:/^[a-f0-9-]{36}$/'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:' . ChunkedUpload::MAX_CHUNKS],
            'file_name'    => ['required', 'string', 'max:260'],
            'title'        => ['required', 'string', 'max:200'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'version'      => ['nullable', 'string', 'max:40'],
            'os_type'      => ['nullable', Rule::in(Software::OS_TYPES)],
            'category'     => ['nullable', Rule::in(Software::CATEGORIES)],
            'thumbnail'    => ['nullable', 'image', 'max:4096'],
            'image_url'    => ['nullable', 'string', 'max:500'],
        ]);

        $user      = $request->user();
        $origName  = $data['file_name'];
        $ext       = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $safeName  = Str::random(20) . ($ext ? '.' . $ext : '');
        $path      = 'software/' . $user->id . '/' . $safeName;

        try {
            $size = ChunkedUpload::assemble(
                $user->id,
                $data['upload_id'],
                (int) $data['total_chunks'],
                Software::DISK,
                $path
            );
        } catch (\Throwable $e) {
            ChunkedUpload::discard($user->id, $data['upload_id']);
            Storage::disk(Software::DISK)->delete($path);
            throw $e;
        }

        // Detect mime type from the assembled file.
        $fullPath = Storage::disk(Software::DISK)->path($path);
        $mime     = null;
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($fullPath);
            $mime     = $detected ?: null;
        }
        if (! $mime) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($fullPath) ?: 'application/octet-stream';
        }

        // Reject images, videos and audio - those have their own sections.
        if (preg_match('#^(image|video|audio)/#i', (string) $mime)) {
            Storage::disk(Software::DISK)->delete($path);
            return back()->withErrors(['file_name' => 'Please upload a software file, not an image, video or audio file.']);
        }

        $thumbPath = null;
        if (! $request->hasFile('thumbnail') && ! empty($data['image_url'])) {
            $bytes = SoftwareLookup::thumbnail(['image' => $data['image_url']]);
            if ($bytes) {
                $thumbPath = 'software/thumbs/' . Str::random(20) . '.jpg';
                Storage::disk(Software::DISK)->put($thumbPath, $bytes);
            }
        }
        if ($request->hasFile('thumbnail')) {
            $thumb     = $request->file('thumbnail');
            $thumbName = Str::random(20) . '.jpg';
            $thumbPath = 'software/thumbs/' . $thumbName;

            // Convert or copy thumbnail.
            $thumbBytes = $thumb->get();
            Storage::disk(Software::DISK)->put($thumbPath, $thumbBytes);
        }

        $item = Software::create([
            'user_id'        => $user->id,
            'title'          => $data['title'],
            'description'    => $data['description'] ?? null,
            'version'        => $data['version'] ?? null,
            'os_type'        => $data['os_type'] ?? null,
            'category'       => $data['category'] ?? null,
            'file_path'      => $path,
            'file_name'      => $origName,
            'file_size'      => $size,
            'file_mime'      => $mime,
            'thumbnail_path' => $thumbPath,
        ]);

        return redirect()->route('software.show', $item)
            ->with('status', 'Software uploaded successfully.');
    }

    public function lookup(Request $request): JsonResponse
    {
        $data  = $request->validate(['name' => ['required', 'string', 'max:260']]);
        $match = SoftwareLookup::find($data['name']);

        if (! $match) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found'       => true,
            'title'       => $match['title'],
            'version'     => $match['version'],
            'description' => $match['description'],
            'os_type'     => $match['os_type'],
            'category'    => $match['category'],
            'image_url'   => $match['image'] ?? null,
            'images'      => $match['images'] ?? [],
        ]);
    }

    public function show(Request $request, Software $software): View
    {
        $software->load('user')->loadCount('likers');

        // Increment views (once per session per item).
        $seen = $request->session()->get('software_views', []);
        if (! in_array($software->id, $seen, true)) {
            Software::whereKey($software->id)->increment('views');
            $request->session()->put('software_views', [...$seen, $software->id]);
            $software->views++;
        }

        $similar = Software::with('user')
            ->where('id', '!=', $software->id)
            ->when($software->category, fn ($q) => $q->where('category', $software->category))
            ->orderByDesc('downloads')
            ->limit(6)
            ->get();

        return view('software.show', [
            'software'       => $software,
            'isOwner'        => auth()->id() === $software->user_id,
            'liked'          => $request->user() ? $software->likers()->whereKey($request->user()->id)->exists() : false,
            'similar'        => $similar,
            'uploaderCount'  => Software::where('user_id', $software->user_id)->count(),
        ]);
    }

    public function edit(Request $request, Software $software): View
    {
        abort_unless($request->user()?->id === $software->user_id, 403);

        return view('software.edit', [
            'software'   => $software,
            'osTypes'    => Software::OS_TYPES,
            'categories' => Software::CATEGORIES,
        ]);
    }

    public function update(Request $request, Software $software): RedirectResponse
    {
        abort_unless($request->user()?->id === $software->user_id, 403);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'version'     => ['nullable', 'string', 'max:40'],
            'os_type'     => ['nullable', Rule::in(Software::OS_TYPES)],
            'category'    => ['nullable', Rule::in(Software::CATEGORIES)],
            'thumbnail'   => ['nullable', 'image', 'max:4096'],
        ]);

        $software->update([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'version'     => $data['version'] ?? null,
            'os_type'     => $data['os_type'] ?? null,
            'category'    => $data['category'] ?? null,
        ]);

        if ($request->hasFile('thumbnail')) {
            // Remove old thumbnail.
            if ($software->thumbnail_path) {
                Storage::disk(Software::DISK)->delete($software->thumbnail_path);
            }

            $thumbName = Str::random(20) . '.jpg';
            $thumbPath = 'software/thumbs/' . $thumbName;
            Storage::disk(Software::DISK)->put($thumbPath, $request->file('thumbnail')->get());
            $software->update(['thumbnail_path' => $thumbPath]);
        }

        return redirect()->route('software.show', $software)
            ->with('status', 'Software details saved.');
    }

    public function destroy(Request $request, Software $software): RedirectResponse
    {
        abort_unless($request->user()?->id === $software->user_id, 403);

        $software->deleteWithFiles();

        return redirect()->route('software.index')
            ->with('status', 'Software deleted.');
    }

    public function download(Request $request, Software $software): mixed
    {
        Software::whereKey($software->id)->increment('downloads');

        return Storage::disk(Software::DISK)->download($software->file_path, $software->file_name);
    }

    public function like(Request $request, Software $software): JsonResponse
    {
        $result = $software->likers()->toggle($request->user()->id);

        return response()->json([
            'liked' => ! empty($result['attached']),
            'count' => $software->likers()->count(),
        ]);
    }
}
