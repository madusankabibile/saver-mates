<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use App\Models\MovieSubtitle;
use App\Support\ChunkedUpload;
use App\Support\MediaLibrary;
use App\Support\Subtitles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MovieController extends Controller
{
    /** The browse page: search, category chips and sort, like a video home page. */
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $category = $request->query('category');
        $sort = in_array($request->query('sort'), ['popular', 'liked'], true) ? $request->query('sort') : 'latest';

        $movies = Movie::with('user')
            ->withCount('likers')
            ->search($term)
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($sort === 'popular', fn ($q) => $q->orderByDesc('views'))
            ->when($sort === 'liked', fn ($q) => $q->orderByDesc('likers_count'))
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return view('movies.index', [
            'movies' => $movies,
            'term' => $term,
            'category' => $category,
            'sort' => $sort,
            'categories' => Movie::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'trending' => Movie::with('user')->orderByDesc('views')->latest()->limit(5)->get(),
            'totalMovies' => Movie::count(),
            'totalHours' => (int) round(Movie::sum('duration') / 3600),
            'myCount' => $request->user()?->movies()->count() ?? 0,
        ]);
    }

    public function show(Request $request, Movie $movie): View
    {
        $movie->load(['user', 'subtitles', 'comments.user', 'comments.replies.user'])
            ->loadCount(['likers', 'allComments']);

        return view('movies.show', [
            'movie' => $movie,
            'related' => $movie->related(),
            'liked' => $request->user() ? $movie->likers()->whereKey($request->user()->id)->exists() : false,
            'uploaderCount' => Movie::where('user_id', $movie->user_id)->count(),
            // The uploader's browser captures a frame for imported movies that have no thumbnail.
            'needsThumbnail' => ! $movie->thumbnail_path && $request->user()?->id === $movie->user_id,
        ]);
    }

    public function create(): View
    {
        return view('movies.create', [
            'categories' => Movie::CATEGORIES,
            'languages' => Subtitles::LANGUAGES,
        ]);
    }

    /**
     * Finishes an upload: the video already arrived in chunks; this request
     * carries the details, thumbnail and subtitle files.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'upload_id' => ['required', 'string', 'regex:/^[a-f0-9-]{36}$/'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:'.ChunkedUpload::MAX_CHUNKS],
            'filename' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', Rule::in(Movie::CATEGORIES)],
            'duration' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'thumbnail' => ['nullable', 'image', 'max:6144'],
            'subtitles' => ['nullable', 'array', 'max:6'],
            'subtitles.*' => ['file', 'max:2048'],
            'subtitle_langs' => ['nullable', 'array'],
            'subtitle_langs.*' => ['nullable', Rule::in(array_keys(Subtitles::LANGUAGES))],
        ]);

        $this->assertSubtitleFiles($request);

        $user = $request->user();
        $path = MediaLibrary::moviePath($data['filename']);

        try {
            ChunkedUpload::assemble($user->id, $data['upload_id'], (int) $data['total_chunks'], Movie::DISK, $path);
        } catch (\Throwable $e) {
            ChunkedUpload::discard($user->id, $data['upload_id']);
            Storage::disk(Movie::DISK)->delete($path);
            throw $e;
        }

        $movie = MediaLibrary::createMovie($user, $path, $data, isset($data['duration']) ? (int) round($data['duration']) : null);

        if ($request->hasFile('thumbnail')) {
            MediaLibrary::storeThumbnail($movie, $request->file('thumbnail'));
        }

        foreach ($request->file('subtitles', []) as $i => $file) {
            MediaLibrary::addSubtitle($movie, $file->get(), $file->getClientOriginalName(), $data['subtitle_langs'][$i] ?? null);
        }

        return response()->json(['url' => route('movies.show', $movie)]);
    }

    public function edit(Request $request, Movie $movie): View
    {
        $this->authorizeOwner($request, $movie);

        return view('movies.edit', [
            'movie' => $movie->load('subtitles'),
            'categories' => Movie::CATEGORIES,
            'languages' => Subtitles::LANGUAGES,
        ]);
    }

    public function update(Request $request, Movie $movie): RedirectResponse
    {
        $this->authorizeOwner($request, $movie);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', Rule::in(Movie::CATEGORIES)],
            'thumbnail' => ['nullable', 'image', 'max:6144'],
            'subtitles' => ['nullable', 'array', 'max:6'],
            'subtitles.*' => ['file', 'max:2048'],
            'subtitle_langs' => ['nullable', 'array'],
            'subtitle_langs.*' => ['nullable', Rule::in(array_keys(Subtitles::LANGUAGES))],
            'remove_subtitles' => ['nullable', 'array'],
            'remove_subtitles.*' => ['integer'],
        ]);

        $this->assertSubtitleFiles($request);

        $movie->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
        ]);

        if ($request->hasFile('thumbnail')) {
            MediaLibrary::storeThumbnail($movie, $request->file('thumbnail'));
        }

        $movie->subtitles()->whereIn('id', $data['remove_subtitles'] ?? [])->get()
            ->each(function (MovieSubtitle $sub) {
                Storage::disk(Movie::DISK)->delete($sub->path);
                $sub->delete();
            });

        foreach ($request->file('subtitles', []) as $i => $file) {
            MediaLibrary::addSubtitle($movie, $file->get(), $file->getClientOriginalName(), $data['subtitle_langs'][$i] ?? null);
        }

        return redirect()->route('movies.show', $movie)->with('status', 'Movie details saved.');
    }

    public function destroy(Request $request, Movie $movie): RedirectResponse
    {
        $this->authorizeOwner($request, $movie);

        $movie->deleteWithFiles();

        return redirect()->route('movies.index')->with('status', 'Movie deleted.');
    }

    /** A frame captured in the uploader's browser becomes the thumbnail (and fills a missing duration). */
    public function thumbnail(Request $request, Movie $movie): JsonResponse
    {
        $this->authorizeOwner($request, $movie);

        $data = $request->validate([
            'thumbnail' => ['required', 'image', 'max:6144'],
            'duration' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        MediaLibrary::storeThumbnail($movie, $request->file('thumbnail'));

        if (! $movie->duration && ! empty($data['duration'])) {
            $movie->update(['duration' => (int) round($data['duration'])]);
        }

        return response()->json(['url' => $movie->thumbnailUrl()]);
    }

    public function like(Request $request, Movie $movie): JsonResponse
    {
        $result = $movie->likers()->toggle($request->user()->id);

        return response()->json([
            'liked' => ! empty($result['attached']),
            'count' => $movie->likers()->count(),
        ]);
    }

    /** Counted once per browser session per movie, after the viewer has actually watched a little. */
    public function view(Request $request, Movie $movie): JsonResponse
    {
        $seen = $request->session()->get('movie_views', []);

        if (! in_array($movie->id, $seen, true)) {
            $movie->increment('views');
            $request->session()->put('movie_views', [...$seen, $movie->id]);
        }

        return response()->json(['views' => $movie->views]);
    }

    protected function authorizeOwner(Request $request, Movie $movie): void
    {
        abort_unless($request->user()?->id === $movie->user_id, 403);
    }

    /** Subtitle uploads are plain text, so the extension is what tells SRT and VTT apart. */
    protected function assertSubtitleFiles(Request $request): void
    {
        foreach ($request->file('subtitles', []) as $file) {
            if (! in_array(strtolower($file->getClientOriginalExtension()), ['srt', 'vtt'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'subtitles' => 'Subtitles must be .srt or .vtt files.',
                ]);
            }
        }
    }
}
