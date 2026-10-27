<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\Song;
use App\Support\ChunkedUpload;
use App\Support\ImageStore;
use App\Support\MediaLibrary;
use App\Support\SongLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SongController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $artist = $request->query('artist');
        $liked = $request->query('view') === 'liked' && $request->user();
        $sort = in_array($request->query('sort'), ['popular', 'title', 'artist'], true) ? $request->query('sort') : 'recent';

        $songs = Song::query()
            ->search($term)
            ->when($artist, fn ($q) => $q->where('artist', $artist))
            ->when($liked, fn ($q) => $q->whereHas('likers', fn ($l) => $l->whereKey($request->user()->id)))
            ->when($sort === 'popular', fn ($q) => $q->orderByDesc('plays'))
            ->when($sort === 'title', fn ($q) => $q->orderBy('title'))
            ->when($sort === 'artist', fn ($q) => $q->orderBy('artist')->orderBy('title'))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $isHome = ! $term && ! $artist && ! $liked && $songs->currentPage() === 1;

        return view('songs.index', [
            'songs' => $songs,
            'term' => $term,
            'artist' => $artist,
            'liked' => (bool) $liked,
            'sort' => $sort,
            'isHome' => $isHome,
            'featured' => $isHome
                ? Playlist::with(['user', 'songs'])->withCount('songs')->having('songs_count', '>', 0)
                    ->orderByDesc('plays')->latest()->limit(6)->get()
                : collect(),
            'topArtists' => $isHome ? $this->artistRows(8) : collect(),
            'likedIds' => $this->likedIds($request),
            'totalSongs' => Song::count(),
        ]);
    }

    protected function artistRows(?int $limit = null): \Illuminate\Support\Collection
    {
        $rows = Song::query()
            ->whereNotNull('artist')
            ->select('artist', DB::raw('count(*) as tracks'), DB::raw('sum(plays) as plays'), DB::raw('max(cover_path) as cover_path'))
            ->groupBy('artist')
            ->orderByDesc('plays')
            ->orderByDesc('tracks')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        return $rows->map(fn ($r) => (object) [
            'name' => $r->artist,
            'tracks' => (int) $r->tracks,
            'cover' => $r->cover_path ? asset('storage/'.$r->cover_path) : null,
            'hue' => crc32($r->artist) % 360,
        ]);
    }

    public function artistIndex(Request $request): View
    {
        return view('songs.artists', [
            'artists' => $this->artistRows(),
            'totalSongs' => Song::count(),
        ]);
    }

    public function create(): View
    {
        return view('songs.create', ['totalSongs' => Song::count()]);
    }

    /** One track per request; the audio itself arrived in chunks under upload_id. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'upload_id' => ['required', 'string', 'regex:/^[a-f0-9-]{36}$/'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:40'],
            'filename' => ['required', 'string', 'max:255'],
            'duration' => ['nullable', 'numeric', 'min:0', 'max:36000'],
        ]);

        $user = $request->user();
        $path = MediaLibrary::songPath($data['filename']);

        try {
            ChunkedUpload::assemble($user->id, $data['upload_id'], (int) $data['total_chunks'], Song::DISK, $path);
        } catch (\Throwable $e) {
            ChunkedUpload::discard($user->id, $data['upload_id']);
            Storage::disk(Song::DISK)->delete($path);
            throw $e;
        }

        $song = MediaLibrary::createSong(
            $user, $path, $data['filename'], null,
            isset($data['duration']) ? (int) round($data['duration']) : null,
            lookup: true,
        );

        return response()->json([
            'id' => $song->id,
            'title' => $song->title,
            'artist' => $song->artistName(),
            'artist_raw' => $song->artist,
            'album' => $song->album,
            'genre' => $song->genre,
            'found' => $song->detailsFound ?? false,
            'duration' => $song->durationLabel(),
            'cover' => $song->coverUrl(),
            'edit_url' => route('songs.edit', $song),
            'update_url' => route('songs.update', $song),
        ]);
    }

    /** "Find song details" on the edit page: matching songs to choose from. */
    public function lookup(Request $request, Song $song): JsonResponse
    {
        $this->authorizeOwner($request, $song);

        $q = trim((string) $request->query('q')) ?: trim($song->title.' '.$song->artist);

        return response()->json(collect(SongLookup::search($q, 8))->map(fn ($m) => [
            'id' => $m['key'],
            'title' => $m['title'],
            'artist' => $m['artist'],
            'album' => $m['album'],
            'thumb' => $m['thumb'],
            'duration' => $m['duration'] ? sprintf('%d:%02d', intdiv($m['duration'], 60), $m['duration'] % 60) : null,
        ])->values());
    }

    /** Use the details of the result the owner picked. */
    public function applyLookup(Request $request, Song $song): RedirectResponse
    {
        $this->authorizeOwner($request, $song);

        $data = $request->validate(['id' => ['required', 'string', 'regex:/^(dz|it):\d{1,15}$/']]);
        $match = SongLookup::track($data['id']);

        if (! $match) {
            return back()->withErrors(['lookup' => 'Those details could not be loaded. Try again in a moment.']);
        }

        SongLookup::apply($song, $match);

        return redirect()->route('songs.edit', $song)->with('status', 'Song details updated. You can still change anything below.');
    }

    public function edit(Request $request, Song $song): View
    {
        $this->authorizeOwner($request, $song);

        return view('songs.edit', ['song' => $song, 'totalSongs' => Song::count()]);
    }

    public function update(Request $request, Song $song): RedirectResponse|JsonResponse
    {
        $this->authorizeOwner($request, $song);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:250'],
            'artist' => ['nullable', 'string', 'max:250'],
            'album' => ['nullable', 'string', 'max:250'],
            'genre' => ['nullable', 'string', 'max:60'],
            'cover' => ['nullable', 'image', 'max:4096'],
            'remove_cover' => ['nullable', 'boolean'],
        ]);

        $oldCover = $song->cover_path;
        $newCover = $oldCover;

        if ($request->hasFile('cover')) {
            $newCover = ImageStore::put($request->file('cover'), 'music/covers', 800, 800);
        } elseif ($request->boolean('remove_cover')) {
            $newCover = null;
        }

        $song->update([
            'title' => $data['title'],
            'artist' => ($data['artist'] ?? null) ?: null,
            'album' => ($data['album'] ?? null) ?: null,
            'genre' => ($data['genre'] ?? null) ?: null,
            'cover_path' => $newCover,
        ]);

        // Covers can be shared by an album's tracks; only drop files nothing uses.
        if ($oldCover && $oldCover !== $newCover && ! Song::where('cover_path', $oldCover)->exists()) {
            Storage::disk(Song::DISK)->delete($oldCover);
        }

        // The upload page saves corrections in place.
        if ($request->expectsJson()) {
            return response()->json(['title' => $song->title, 'artist' => $song->artistName(), 'album' => $song->album]);
        }

        return redirect()->route('songs.index')->with('status', '"'.$song->title.'" saved.');
    }

    public function destroy(Request $request, Song $song): RedirectResponse|JsonResponse
    {
        $this->authorizeOwner($request, $song);

        $song->deleteWithFiles();

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : redirect()->route('songs.index')->with('status', 'Song deleted.');
    }

    public function like(Request $request, Song $song): JsonResponse
    {
        $result = $song->likers()->toggle($request->user()->id);

        return response()->json(['liked' => ! empty($result['attached'])]);
    }

    /** Called by the player once a track has played for a while. Also fills a missing duration. */
    public function play(Request $request, Song $song): JsonResponse
    {
        $data = $request->validate(['duration' => ['nullable', 'numeric', 'min:0', 'max:36000']]);

        $song->increment('plays');

        if (! $song->duration && ! empty($data['duration'])) {
            $song->update(['duration' => (int) round($data['duration'])]);
        }

        return response()->json(['plays' => $song->plays]);
    }

    protected function likedIds(Request $request): array
    {
        return $request->user()
            ? DB::table('song_likes')->where('user_id', $request->user()->id)->pluck('song_id')->all()
            : [];
    }

    protected function authorizeOwner(Request $request, Song $song): void
    {
        abort_unless($request->user()?->id === $song->user_id, 403);
    }
}
