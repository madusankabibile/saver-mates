{{-- Section tabs for the music pages. Links carry data-music-link so music.js
     swaps only the middle panel and the song keeps playing. --}}
@php
    $route = request()->route()->getName();
    $tab = match (true) {
        $route === 'songs.artists' => 'artists',
        in_array($route, ['playlists.index', 'playlists.show']) => 'playlists',
        $route === 'songs.index' && request('view') === 'liked' => 'liked',
        default => 'songs',
    };
@endphp

<div class="mu-nav">
    <nav class="tabs mu-tabs" aria-label="Music">
        <a class="tab {{ $tab === 'songs' ? 'is-active' : '' }}" href="{{ route('songs.index') }}" data-music-link>
            <x-icon name="music" :size="16" /> Songs
        </a>
        <a class="tab {{ $tab === 'artists' ? 'is-active' : '' }}" href="{{ route('songs.artists') }}" data-music-link>
            <x-icon name="people" :size="16" /> Artists
        </a>
        <a class="tab {{ $tab === 'playlists' ? 'is-active' : '' }}" href="{{ route('playlists.index') }}" data-music-link>
            <x-icon name="playlist" :size="16" /> Playlists
        </a>
        @auth
            <a class="tab {{ $tab === 'liked' ? 'is-active' : '' }}" href="{{ route('songs.index', ['view' => 'liked']) }}" data-music-link>
                <x-icon name="heart" :size="16" /> Liked
            </a>
        @endauth
    </nav>

    <form class="search-form mu-search" method="GET" action="{{ route('songs.index') }}" role="search" data-music-search>
        <span class="si"><x-icon name="search" :size="17" /></span>
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search songs, artists, albums" aria-label="Search music">
    </form>
</div>
