@extends('songs.layout')

@section('title', $liked ? 'Liked songs' : ($artist ?: 'Songs'))

@section('music')
    @php
        $heading = $liked ? 'Liked songs' : ($artist ?: ($term ? 'Results for “'.$term.'”' : 'All songs'));
    @endphp

    @if ($isHome)
        @if ($featured->isNotEmpty())
            <div class="mu-section-head">
                <h2>Popular playlists</h2>
                <a class="small" href="{{ route('playlists.index') }}" data-music-link>See all</a>
            </div>
            <div class="pl-grid">
                @foreach ($featured as $playlist)
                    @include('songs.partials.playlist-card', ['playlist' => $playlist])
                @endforeach
            </div>
        @endif

        @if ($topArtists->isNotEmpty())
            <div class="mu-section-head">
                <h2>Artists</h2>
                <a class="small" href="{{ route('songs.artists') }}" data-music-link>See all</a>
            </div>
            <div class="ar-row">
                @foreach ($topArtists as $a)
                    <a class="ar-card" href="{{ route('songs.index', ['artist' => $a->name]) }}" data-music-link>
                        <span class="ar-pic" style="--hue: {{ $a->hue }}">
                            @if ($a->cover)<img src="{{ $a->cover }}" alt="" loading="lazy">@else{{ mb_substr($a->name, 0, 1) }}@endif
                        </span>
                        <span class="ar-name">{{ $a->name }}</span>
                        <span class="tiny muted">{{ $a->tracks }} {{ \Illuminate\Support\Str::plural('song', $a->tracks) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    @endif

    <div class="mu-section-head mu-list-head">
        <div>
            <h2>{{ $heading }}</h2>
            <p class="small muted">{{ number_format($songs->total()) }} {{ \Illuminate\Support\Str::plural('song', $songs->total()) }}</p>
        </div>

        <div class="mu-list-actions">
            @if ($songs->isNotEmpty())
                <button class="btn btn-sm" type="button" data-play-all><x-icon name="play" :size="14" /> Play</button>
                <button class="btn btn-ghost btn-sm" type="button" data-play-all="shuffle"><x-icon name="shuffle" :size="15" /> Shuffle</button>
            @endif

            <div class="seg seg-sm">
                @foreach (['recent' => 'Recent', 'popular' => 'Popular', 'title' => 'A–Z', 'artist' => 'Artist'] as $key => $label)
                    <a class="{{ $sort === $key ? 'is-active' : '' }}" data-music-link
                       href="{{ route('songs.index', array_filter(['q' => $term, 'artist' => $artist, 'view' => $liked ? 'liked' : null, 'sort' => $key === 'recent' ? null : $key])) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </div>

    @if ($songs->isEmpty())
        <div class="empty" style="padding:2.5rem 1rem">
            <div class="em-icon"><x-icon name="{{ $liked ? 'heart' : 'music' }}" :size="32" /></div>
            <h3>{{ $liked ? 'No liked songs yet' : ($term ? 'Nothing matches that search' : 'No songs yet') }}</h3>
            <p>
                @if ($liked)
                    Tap the heart on any song to keep it here.
                @elseif ($term)
                    Try an artist name or part of a title.
                @else
                    Upload some music and it will be here for everyone.
                @endif
            </p>
            @auth
                @unless ($liked)
                    <a class="btn btn-sm" href="{{ route('songs.create') }}">Upload songs</a>
                @endunless
            @endauth
        </div>
    @else
        <div class="mu-tracks" data-tracklist>
            @foreach ($songs as $song)
                @include('songs.partials.row', ['song' => $song, 'index' => $songs->firstItem() + $loop->index])
            @endforeach
        </div>

        <div data-music-pager>
            @include('partials.pager', ['paginator' => $songs])
        </div>
    @endif
@endsection
