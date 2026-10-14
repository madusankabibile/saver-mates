<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Comment;
use App\Models\DriveFile;
use App\Models\Movie;
use App\Models\MovieComment;
use App\Models\Post;
use App\Models\Song;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * One moderation screen per kind of content. Every type lists, searches,
 * filters by member, and deletes (singly or in bulk) the same way.
 */
class ContentController extends Controller
{
    public const TYPES = ['posts', 'comments', 'movie-comments', 'movies', 'songs', 'files'];

    protected const LABELS = [
        'posts' => ['Posts', 'post'],
        'comments' => ['Post comments', 'comment'],
        'movie-comments' => ['Movie comments', 'movie comment'],
        'movies' => ['Movies', 'movie'],
        'songs' => ['Songs', 'song'],
        'files' => ['Drive files', 'file'],
    ];

    public function index(Request $request, string $type): View
    {
        $query = $this->query($type)->with('user');
        $term = trim((string) $request->query('q'));

        if ($term !== '') {
            $this->search($query, $type, $term);
        }

        if ($userId = $request->integer('user')) {
            $query->where('user_id', $userId);
        }

        if ($type === 'files' && in_array($request->query('visibility'), ['public', 'private'], true)) {
            $query->where('is_public', $request->query('visibility') === 'public');
        }

        match ($type) {
            'posts' => $query->withCount(['comments', 'reactions']),
            'comments' => $query->with('post:id,title,body,type'),
            'movie-comments' => $query->with('movie:id,title'),
            default => null,
        };

        $sort = $request->query('sort');
        match (true) {
            $sort === 'oldest' => $query->oldest(),
            $sort === 'largest' && in_array($type, ['movies', 'songs', 'files']) => $query->orderByDesc(['movies' => 'video_size', 'songs' => 'size', 'files' => 'size'][$type]),
            $sort === 'popular' && $type === 'movies' => $query->orderByDesc('views'),
            $sort === 'popular' && $type === 'songs' => $query->orderByDesc('plays'),
            $sort === 'popular' && $type === 'posts' => $query->orderByDesc('reactions_count'),
            default => $query->latest(),
        };

        return view('admin.content', [
            'type' => $type,
            'label' => self::LABELS[$type][0],
            'items' => $query->paginate(25)->withQueryString(),
            'filterUser' => $userId ? \App\Models\User::find($userId) : null,
            'totals' => collect(self::TYPES)->mapWithKeys(fn ($t) => [$t => $this->query($t)->count()]),
        ]);
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        $item = $this->query($type)->findOrFail($id);
        $summary = $this->describe($type, $item);

        $this->remove($type, $item);
        AdminLog::record("content.delete-{$type}", "Deleted {$summary}");

        return back()->with('status', ucfirst(self::LABELS[$type][1]).' deleted.');
    }

    public function bulkDestroy(Request $request, string $type): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        $items = $this->query($type)->whereIn('id', $data['ids'])->get();

        foreach ($items as $item) {
            $this->remove($type, $item);
        }

        $noun = $items->count().' '.Str::plural(self::LABELS[$type][1], $items->count());
        AdminLog::record("content.bulk-delete-{$type}", "Deleted {$noun} in bulk");

        return back()->with('status', ucfirst($noun).' deleted.');
    }

    public function toggleFile(DriveFile $file): RedirectResponse
    {
        $file->update(['is_public' => ! $file->is_public]);

        AdminLog::record('content.file-visibility', 'Made "'.$file->name.'" '.($file->is_public ? 'public' : 'private'));

        return back()->with('status', '"'.$file->name.'" is now '.($file->is_public ? 'on the network drive.' : 'private to its owner.'));
    }

    /* ------------------------------------------------------------------ */

    protected function query(string $type): Builder
    {
        return match ($type) {
            'posts' => Post::query(),
            'comments' => Comment::query(),
            'movie-comments' => MovieComment::query(),
            'movies' => Movie::query(),
            'songs' => Song::query(),
            'files' => DriveFile::query(),
        };
    }

    protected function search(Builder $query, string $type, string $term): void
    {
        $columns = match ($type) {
            'posts' => ['title', 'body'],
            'comments', 'movie-comments' => ['body'],
            'movies' => ['title', 'description', 'category'],
            'songs' => ['title', 'artist', 'album', 'genre'],
            'files' => ['name', 'mime'],
        };

        $query->where(function ($q) use ($columns, $term) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', "%{$term}%");
            }

            $q->orWhereHas('user', fn ($u) => $u->where('username', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"));
        });
    }

    /** Delete the row and any files it owns on disk. */
    protected function remove(string $type, Model $item): void
    {
        match ($type) {
            'posts' => (function () use ($item) {
                Storage::disk('public')->delete($item->images->pluck('path')->all());
                $item->delete();
            })(),
            'movies', 'songs' => $item->deleteWithFiles(),
            'files' => (function () use ($item) {
                Storage::disk(DriveFile::DISK)->delete($item->path);
                $item->delete();
            })(),
            default => $item->delete(),
        };
    }

    protected function describe(string $type, Model $item): string
    {
        $owner = $item->user ? ' by @'.$item->user->username : '';

        return match ($type) {
            'posts' => 'post "'.Str::limit($item->displayTitle(), 60).'"'.$owner,
            'comments', 'movie-comments' => self::LABELS[$type][1].' "'.Str::limit($item->body, 60).'"'.$owner,
            'movies', 'songs' => self::LABELS[$type][1].' "'.$item->title.'"'.$owner,
            'files' => 'file "'.$item->name.'"'.$owner,
        };
    }
}
