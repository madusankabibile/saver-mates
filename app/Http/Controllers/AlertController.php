<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $request): View
    {
        $alerts = Alert::where('user_id', $request->user()->id)
            ->with(['actor', 'post'])
            ->latest()
            ->paginate(25);

        $unread = $request->user()->unreadAlertsCount();

        return view('alerts.index', [
            'alerts' => $alerts,
            'unread' => $unread,
        ]);
    }

    /**
     * Polled by the bell in the top bar for the unread badge and dropdown.
     */
    public function feed(Request $request): JsonResponse
    {
        $alerts = Alert::where('user_id', $request->user()->id)
            ->with('actor')
            ->latest()
            ->limit(8)
            ->get();

        return response()->json([
            'count' => $request->user()->unreadAlertsCount(),
            'items' => $alerts->map(fn (Alert $a) => [
                'id' => $a->id,
                'icon' => $a->icon(),
                'actor' => $a->actor->name,
                'avatar' => $a->actor->avatarUrl(),
                'sentence' => $a->sentence(),
                'preview' => $a->preview,
                'url' => $a->url(),
                'unread' => is_null($a->read_at),
                'ago' => $a->created_at->diffForHumans(null, true).' ago',
            ])->values(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        Alert::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'count' => 0]);
        }

        return back()->with('status', 'All notifications marked as read.');
    }
}
