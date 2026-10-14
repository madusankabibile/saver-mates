@extends('songs.layout')

@section('title', $playlist->name)

@section('music')
    <header class="pl-hero">
        @include('songs.partials.mosaic', ['playlist' => $playlist, 'big' => true])

        <div class="pl-hero-body">
            <span class="tiny pl-kicker"><x-icon name="globe" :size="13" /> Public playlist</span>
            <h2 class="pl-hero-title" data-playlist-name>{{ $playlist->name }}</h2>
            @if ($playlist->description)
                <p class="small muted" data-playlist-desc>{{ $playlist->description }}</p>
            @endif
            <div class="pl-hero-meta small">
                <a href="{{ route('profile.show', $playlist->user) }}" class="pl-owner">
                    <x-avatar :user="$playlist->user" :size="22" /> {{ $playlist->user->name }}
                </a>
                <span>{{ $playlist->songs->count() }} {{ \Illuminate\Support\Str::plural('song', $playlist->songs->count()) }}</span>
                @if ($playlist->songs->isNotEmpty())<span>{{ $playlist->totalDurationLabel() }}</span>@endif
                <span>{{ $playlist->playsLabel() }} {{ \Illuminate\Support\Str::plural('play', $playlist->plays) }}</span>
            </div>

            <div class="pl-hero-actions">
                @if ($playlist->songs->isNotEmpty())
                    <button class="btn" type="button" data-play-all data-count-url="{{ route('playlists.play', $playlist) }}">
                        <x-icon name="play" :size="15" /> Play
                    </button>
                    <button class="btn btn-ghost" type="button" data-play-all="shuffle" data-count-url="{{ route('playlists.play', $playlist) }}">
                        <x-icon name="shuffle" :size="16" /> Shuffle
                    </button>
                @endif
                <button class="btn btn-ghost" type="button" data-copy-link="{{ route('playlists.show', $playlist) }}">
                    <x-icon name="send" :size="15" /> Share
                </button>

                @if ($isOwner)
                    <button class="btn btn-ghost" type="button" data-edit-playlist
                            data-action="{{ route('playlists.update', $playlist) }}"
                            data-name="{{ $playlist->name }}" data-description="{{ $playlist->description }}">
                        <x-icon name="pencil" :size="15" /> Edit
                    </button>
                    <form method="POST" action="{{ route('playlists.destroy', $playlist) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger" type="button"
                                data-confirm="The playlist is removed. The songs stay in the library."
                                data-confirm-title="Delete this playlist?" data-confirm-label="Delete playlist">
                            <x-icon name="trash" :size="15" />
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </header>

    @if ($playlist->songs->isEmpty())
        <div class="empty" style="padding:2.5rem 1rem">
            <div class="em-icon"><x-icon name="music" :size="32" /></div>
            <h3>This playlist is empty</h3>
            <p>
                @if ($isOwner)
                    Open <a href="{{ route('songs.index') }}" data-music-link>Songs</a> and use the &hellip; menu on a track to add it here.
                @else
                    {{ $playlist->user->name }} has not added any songs yet.
                @endif
            </p>
        </div>
    @else
        @if ($isOwner)
            <p class="tiny muted" style="margin:0 0 .5rem">Drag songs by the handle to reorder them.</p>
        @endif

        <div class="mu-tracks" data-tracklist
             @if ($isOwner) data-reorder-url="{{ route('playlists.reorder', $playlist) }}" @endif>
            @foreach ($playlist->songs as $song)
                @include('songs.partials.row', [
                    'song' => $song,
                    'index' => $loop->iteration,
                    'sortable' => $isOwner,
                    'removeUrl' => $isOwner ? route('playlists.songs.remove', [$playlist, $song]) : null,
                ])
            @endforeach
        </div>
    @endif
@endsection
