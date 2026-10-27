<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The right column on every page lists the most recently active members.
        View::composer('partials.members', function ($view) {
            $view->with('recentMembers', User::query()
                ->when(auth()->check(), fn ($q) => $q->where('id', '!=', auth()->id()))
                ->orderByRaw('last_seen_at IS NULL')
                ->latest('last_seen_at')
                ->latest()
                ->limit(8)
                ->get());
        });

        // The music player's "Add to playlist" menu lists the member's own playlists.
        View::composer('songs.partials.player', function ($view) {
            $view->with('myPlaylists', auth()->check()
                ? auth()->user()->playlists()->get(['id', 'name'])
                : []);
        });
    }
}
