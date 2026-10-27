<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DirectoryController extends Controller
{
    /**
     * Browse every member on the network, searchable by name or job role.
     */
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $filter = $request->query('filter', 'all');

        $query = User::query()->where('id', '!=', $request->user()->id);

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%")
                    ->orWhere('designation', 'like', "%{$term}%")
                    ->orWhere('skills', 'like', "%{$term}%");
            });
        }

        if ($filter === 'online') {
            $query->online();
        }

        $members = $query
            ->orderByRaw('CASE WHEN last_seen_at > ? THEN 0 ELSE 1 END', [now()->subMinutes(User::ONLINE_WINDOW_MINUTES)])
            ->orderBy('name')
            ->paginate(18)
            ->withQueryString();

        return view('directory.index', [
            'members' => $members,
            'term' => $term,
            'filter' => $filter,
            'onlineCount' => User::online()->where('id', '!=', $request->user()->id)->count(),
            'totalCount' => User::where('id', '!=', $request->user()->id)->count(),
        ]);
    }

    /**
     * Live status poll for the directory and the feed sidebar.
     */
    public function presence(Request $request): JsonResponse
    {
        $online = User::online()
            ->where('id', '!=', $request->user()->id)
            ->latest('last_seen_at')
            ->get(['id', 'name', 'username', 'designation', 'avatar', 'last_seen_at']);

        return response()->json([
            'count' => $online->count(),
            'ids' => $online->pluck('id'),
            'members' => $online->take(12)->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'designation' => $u->designation,
                'avatar' => $u->avatarUrl(),
                'chat_url' => route('chat.with', $u),
            ])->values(),
        ]);
    }

    /**
     * Keeps a member flagged as online while a tab stays open.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $request->user()->forceFill(['last_seen_at' => now()])->saveQuietly();

        return response()->json(['ok' => true, 'at' => now()->toIso8601String()]);
    }
}
