<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Support\ImageStore;
use App\Support\RandomCover;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    /** Opens the create-account popup over the feed. */
    public function create(): RedirectResponse
    {
        return redirect()->route('feed.index')->with('auth_modal', 'register');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        if (! Setting::flag('registration_open')) {
            throw ValidationException::withMessages([
                'name' => 'New registrations are closed. Ask a network administrator for an account.',
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'designation' => ['nullable', 'string', 'max:120'],
            'qualifications' => ['nullable', 'string', 'max:1000'],
            'skills' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'max:4096'],
        ], [
            'username.regex' => 'Usernames may only contain letters, numbers, dots, dashes and underscores.',
        ]);

        $user = new User($data);

        if ($request->hasFile('avatar')) {
            $user->avatar = ImageStore::put($request->file('avatar'), 'avatars', 600, 600);
        }

        $user->last_seen_at = now();
        $user->save();

        RandomCover::assign($user);

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->flash('status', 'Your account is ready. Add a bio and cover banner to finish your profile.');

        return LoginController::finish($request, redirect()->route('profile.edit'));
    }
}
