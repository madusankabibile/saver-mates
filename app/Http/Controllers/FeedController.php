<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\View\View;

class FeedController extends Controller
{
    /**
     * The community newsfeed - readable by guests, postable by members.
     */
    public function index(): View
    {
        $posts = Post::with(['user', 'images', 'reactions', 'rootComments.user', 'rootComments.replies.user'])
            ->withCount('comments')
            ->latest()
            ->paginate(12);

        return view('feed.index', [
            'posts' => $posts,
            // Exclude yourself so the server-rendered tally matches the list and
            // the number does not jump when the presence poll first replies.
            'onlineMembers' => User::online()
                ->when(auth()->check(), fn ($q) => $q->where('id', '!=', auth()->id()))
                ->latest('last_seen_at')
                ->limit(12)
                ->get(),
            'memberCount' => User::count(),
            'postCount' => Post::count(),
        ]);
    }
}
