<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\Song;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Playlists are public: anyone can browse and play them; only the owner edits.
 */
class PlaylistController extends Controller
{
    public function index(Request $request): View
    {
        $mine = $request->query('view') === 'mine' && $request->user();

        return view('songs.playlists', [
            'playlists' => Playlist::with(['user', 'songs'])
                ->withCount('songs')
                ->when($mine, fn ($q) => $q->where('user_id', $request->user()->id))
                ->orderByDesc('plays')
                ->latest()
                ->paginate(24)
                ->withQueryString(),
            'mine' => (bool) $mine,
            'totalSongs' => Song::count(),
        ]);
    }

    public function show(Request $request, Playlist $playlist): View
    {
        $playlist->load(['user', 'songs']);

        return view('songs.playlist', [
            'playlist' => $playlist,
            'isOwner' => $request->user()?->id === $playlist->user_id,
            'likedIds' => $request->user()
                ? DB::table('song_likes')->where('user_id', $request->user()->id)->pluck('song_id')->all()
                : [],
            'totalSongs' => Song::count(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'song_id' => ['nullable', 'integer', 'exists:songs,id'],
        ]);

        $playlist = $request->user()->playlists()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        if (! empty($data['song_id'])) {
            $playlist->songs()->attach($data['song_id'], ['position' => 1]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $playlist->id,
                'name' => $playlist->name,
                'url' => route('playlists.show', $playlist),
            ]);
        }

        return redirect()->route('playlists.show', $playlist)->with('status', 'Playlist created.');
    }

    public function update(Request $request, Playlist $playlist): RedirectResponse
    {
        $this->authorizeOwner($request, $playlist);

        $playlist->update($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]));

        return redirect()->route('playlists.show', $playlist)->with('status', 'Playlist saved.');
    }

    public function destroy(Request $request, Playlist $playlist): RedirectResponse
    {
        $this->authorizeOwner($request, $playlist);

        $playlist->delete();

        return redirect()->route('playlists.index')->with('status', 'Playlist deleted.');
    }

    public function addSong(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizeOwner($request, $playlist);

        $songId = (int) $request->validate(['song_id' => ['required', 'integer', 'exists:songs,id']])['song_id'];

        $exists = $playlist->songs()->whereKey($songId)->exists();

        if (! $exists) {
            $next = (int) DB::table('playlist_song')->where('playlist_id', $playlist->id)->max('position') + 1;
            $playlist->songs()->attach($songId, ['position' => $next]);
            $playlist->touch();
        }

        return response()->json([
            'added' => ! $exists,
            'count' => $playlist->songs()->count(),
            'name' => $playlist->name,
        ]);
    }

    public function removeSong(Request $request, Playlist $playlist, Song $song): JsonResponse
    {
        $this->authorizeOwner($request, $playlist);

        $playlist->songs()->detach($song->id);

        return response()->json(['count' => $playlist->songs()->count()]);
    }

    /** Persist a drag-and-drop order. */
    public function reorder(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizeOwner($request, $playlist);

        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        DB::transaction(function () use ($playlist, $ids) {
            foreach (array_values($ids) as $position => $id) {
                DB::table('playlist_song')
                    ->where('playlist_id', $playlist->id)
                    ->where('song_id', $id)
                    ->update(['position' => $position + 1]);
            }
        });

        return response()->json(['ok' => true]);
    }

    /** Counts a "Play all" on the playlist page. */
    public function play(Playlist $playlist): JsonResponse
    {
        $playlist->increment('plays');

        return response()->json(['plays' => $playlist->plays]);
    }

    protected function authorizeOwner(Request $request, Playlist $playlist): void
    {
        abort_unless($request->user()?->id === $playlist->user_id, 403);
    }
}
