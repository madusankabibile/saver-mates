<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Signing in happens in a popup on every page. The old /login address
     * (and the auth middleware's redirect) opens that popup over the feed;
     * the intended URL stays in the session so sign-in still returns there.
     */
    public function create(): RedirectResponse
    {
        return redirect()->route('feed.index')->with('auth_modal', 'login');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'username' => 'Those details do not match any member account.',
            ]);
        }

        if (Auth::user()->isSuspended()) {
            $reason = Auth::user()->suspension_reason;
            Auth::logout();

            throw ValidationException::withMessages([
                'username' => 'This account is suspended'.($reason ? ': '.$reason : '.'),
            ]);
        }

        $request->session()->regenerate();

        Auth::user()->forceFill(['last_seen_at' => now()])->saveQuietly();
        $request->session()->flash('status', 'Welcome back, '.Auth::user()->name.'!');

        return self::finish($request, redirect()->intended(self::returnTo($request)));
    }

    public function destroy(Request $request): RedirectResponse
    {
        // Drop them just past the online window so an explicit sign-out shows
        // as offline immediately, without faking a stale "active a year ago".
        Auth::user()?->forceFill([
            'last_seen_at' => now()->subMinutes(User::ONLINE_WINDOW_MINUTES + 1),
        ])->saveQuietly();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('feed.index');
    }

    /**
     * Where the popup was opened from, so signing in keeps you on that page.
     * Only same-site addresses are honoured.
     */
    public static function returnTo(Request $request): string
    {
        $target = (string) $request->input('return_to');
        $root = rtrim(url('/'), '/');

        return $target !== '' && ($target === $root || str_starts_with($target, $root.'/'))
            ? $target
            : route('feed.index');
    }

    /** The popup posts with fetch and navigates itself; plain forms get a redirect. */
    public static function finish(Request $request, RedirectResponse $redirect): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['redirect' => $redirect->getTargetUrl()])
            : $redirect;
    }
}
