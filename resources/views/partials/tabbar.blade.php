@php
    $current = request()->route()->getName();
    $tabs = [
        ['route' => 'feed.index',      'label' => 'Feed',    'icon' => 'feed',   'active' => ['feed.index', 'posts.show']],
        ['route' => 'directory.index', 'label' => 'People',  'icon' => 'people', 'active' => ['directory.index']],
        ['route' => 'chat.index',      'label' => 'Chat',    'icon' => 'chat',   'active' => ['chat.index', 'chat.show'], 'badge' => 'chat'],
        ['route' => 'movies.index',    'label' => 'Movies',  'icon' => 'film',   'active' => ['movies.index', 'movies.show', 'movies.create', 'movies.edit']],
        ['route' => 'songs.index',     'label' => 'Songs',   'icon' => 'music',  'active' => ['songs.index', 'songs.artists', 'songs.create', 'songs.edit', 'playlists.index', 'playlists.show']],
        ['route' => 'drive.index',     'label' => 'Drive',   'icon' => 'cloud',  'active' => ['drive.index', 'drive.folder']],
    ];
@endphp

<nav class="tabbar" aria-label="Sections">
    @foreach ($tabs as $tab)
        <a href="{{ route($tab['route']) }}" class="{{ in_array($current, $tab['active']) ? 'is-active' : '' }}">
            <span class="ti"><x-icon :name="$tab['icon']" :size="21" /></span>
            {{ $tab['label'] }}
            @if (($tab['badge'] ?? null) === 'chat')
                <span class="tabbar-dot" data-chat-count hidden>0</span>
            @endif
        </a>
    @endforeach
</nav>
