<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Comment;
use App\Models\DriveFile;
use App\Models\Message;
use App\Models\Movie;
use App\Models\MovieComment;
use App\Models\Playlist;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Song;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $weekAgo = now()->subDays(7);

        $stats = [
            'users' => User::count(),
            'users_new' => User::where('created_at', '>=', $weekAgo)->count(),
            'online' => User::online()->count(),
            'active_today' => User::where('last_seen_at', '>=', now()->subDay())->count(),
            'admins' => User::where('is_admin', true)->count(),
            'suspended' => User::whereNotNull('suspended_at')->count(),
            'posts' => Post::count(),
            'posts_new' => Post::where('created_at', '>=', $weekAgo)->count(),
            'comments' => Comment::count() + MovieComment::count(),
            'reactions' => Reaction::count(),
            'messages' => Message::count(),
            'messages_new' => Message::where('created_at', '>=', $weekAgo)->count(),
            'movies' => Movie::count(),
            'movie_views' => (int) Movie::sum('views'),
            'songs' => Song::count(),
            'song_plays' => (int) Song::sum('plays'),
            'playlists' => Playlist::count(),
            'files' => DriveFile::count(),
            'files_public' => DriveFile::where('is_public', true)->count(),
        ];

        $storage = [
            ['label' => 'Movies', 'bytes' => (int) Movie::sum('video_size'), 'tone' => 'coral'],
            ['label' => 'Songs', 'bytes' => (int) Song::sum('size'), 'tone' => 'amber'],
            ['label' => 'Drive files', 'bytes' => (int) DriveFile::sum('size'), 'tone' => 'jade'],
        ];
        $storageTotal = array_sum(array_column($storage, 'bytes'));

        return view('admin.dashboard', [
            'stats' => $stats,
            'storage' => $storage,
            'storageTotal' => $storageTotal,
            'signups' => $this->daily(User::query(), 30),
            'activity' => $this->activity(14),
            'recentUsers' => User::latest()->limit(6)->get(),
            'recentPosts' => Post::with('user')->withCount(['comments', 'reactions'])->latest()->limit(6)->get(),
            'topMovies' => Movie::orderByDesc('views')->limit(5)->get(),
            'topSongs' => Song::orderByDesc('plays')->limit(5)->get(),
            'topPosters' => User::withCount('posts')->orderByDesc('posts_count')->limit(5)->get(),
            'log' => AdminLog::with('user')->latest()->limit(8)->get(),
        ]);
    }

    /**
     * Rows created per day for the last $days days, zero-filled so the chart
     * shows quiet days too.
     *
     * @return array<int, array{date: Carbon, count: int}>
     */
    protected function daily($query, int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $counts = $query->where('created_at', '>=', $from)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i);
            $series[] = ['date' => $date, 'count' => (int) ($counts[$date->toDateString()] ?? 0)];
        }

        return $series;
    }

    /** Posts, comments and chat messages per day, stacked. */
    protected function activity(int $days): array
    {
        $posts = $this->daily(Post::query(), $days);
        $comments = $this->daily(Comment::query(), $days);
        $messages = $this->daily(Message::query(), $days);

        return array_map(fn ($i) => [
            'date' => $posts[$i]['date'],
            'posts' => $posts[$i]['count'],
            'comments' => $comments[$i]['count'],
            'messages' => $messages[$i]['count'],
        ], array_keys($posts));
    }
}
