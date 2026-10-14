<a class="pl-card" href="{{ route('playlists.show', $playlist) }}" data-music-link>
    @include('songs.partials.mosaic', ['playlist' => $playlist])
    <span class="pl-name">{{ $playlist->name }}</span>
    <span class="tiny muted">
        {{ $playlist->user->name }} &middot; {{ $playlist->songs_count ?? $playlist->songs->count() }} {{ \Illuminate\Support\Str::plural('song', $playlist->songs_count ?? $playlist->songs->count()) }}
    </span>
</a>
