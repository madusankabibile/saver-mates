@php
    // Messages and Notifications deliberately live only in the top bar, so the
    // rail stays a short list of places rather than duplicating those badges.
    $nav = [
        ['route' => 'feed.index',      'label' => 'Newsfeed',      'icon' => 'feed',   'active' => ['feed.index', 'posts.show']],
        ['route' => 'directory.index', 'label' => 'Colleagues',    'icon' => 'people', 'active' => ['directory.index'], 'auth' => true],
        ['route' => 'movies.index',    'label' => 'Movies',        'icon' => 'film',   'active' => ['movies.index', 'movies.show', 'movies.create', 'movies.edit']],
        ['route' => 'software.index', 'label' => 'Software',      'icon' => 'package','active' => ['software.index', 'software.show', 'software.create', 'software.edit']],
        ['route' => 'songs.index',     'label' => 'Songs',         'icon' => 'music',  'active' => ['songs.index', 'songs.artists', 'songs.create', 'songs.edit', 'playlists.index', 'playlists.show']],
        ['route' => 'drive.index',     'label' => 'My Drive',      'icon' => 'cloud',  'active' => ['drive.index', 'drive.folder'], 'auth' => true],
        ['route' => 'drive.public',    'label' => 'Network Drive', 'icon' => 'globe',  'active' => ['drive.public']],
    ];
@endphp

<nav class="rail" aria-label="Main">
    <div class="rail-panel">
        <div class="rail-nav">
            @foreach ($nav as $item)
                @continue(($item['auth'] ?? false) && ! auth()->check())

                <a class="rail-link {{ in_array(request()->route()->getName(), $item['active']) ? 'is-active' : '' }}"
                   href="{{ route($item['route']) }}" title="{{ $item['label'] }}">
                    <span class="ri"><x-icon :name="$item['icon']" :size="19" /></span>
                    <span class="rl-label">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>

        <div class="rail-divider"></div>

        @auth
            <div class="rail-user">
                <a class="person-row" href="{{ route('profile.show', auth()->user()) }}" style="padding:.4rem .5rem">
                    <x-avatar :user="auth()->user()" :size="38" :presence="true" />
                    <span class="pr-body">
                        <span class="pr-name">{{ auth()->user()->name }}</span>
                        <span class="pr-meta">{{ auth()->user()->designation ?: '@'.auth()->user()->username }}</span>
                    </span>
                </a>
                <a class="btn btn-ghost btn-sm btn-block" href="{{ route('profile.edit') }}" style="margin-top:.65rem">
                    <x-icon name="settings" :size="15" /> Edit profile
                </a>
            </div>
        @else
            <div class="rail-guest">
                <h3 style="font-size:.92rem;font-weight:650">Join your colleagues</h3>
                <p class="small muted" style="margin:.3rem 0 .75rem;line-height:1.45">
                    Create an account to post, react, chat and share files on the network.
                </p>
                <a class="btn btn-sm btn-block btn-primary" href="{{ route('register') }}" data-auth-open="register">Create account</a>
            </div>
        @endauth
    </div>
</nav>
