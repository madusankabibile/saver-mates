<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A suspension takes effect on the member's next request, not just their
 * next sign-in: the session is ended and they land back on the feed.
 */
class BlockSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->isSuspended()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'Your account has been suspended. Contact a network administrator.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 403)
                : redirect()->route('feed.index')->withErrors(['account' => $message]);
        }

        return $next($request);
    }
}
