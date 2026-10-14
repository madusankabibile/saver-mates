@extends('songs.layout')

@section('title', 'Playlists')

@section('music')
    <div class="mu-section-head mu-list-head">
        <div>
            <h2>{{ $mine ? 'My playlists' : 'Playlists' }}</h2>
            <p class="small muted">Every playlist is public: anyone can open and play it.</p>
        </div>

        <div class="mu-list-actions">
            @auth
                <div class="seg seg-sm">
                    <a class="{{ ! $mine ? 'is-active' : '' }}" href="{{ route('playlists.index') }}" data-music-link>Everyone's</a>
                    <a class="{{ $mine ? 'is-active' : '' }}" href="{{ route('playlists.index', ['view' => 'mine']) }}" data-music-link>Mine</a>
                </div>
                <button class="btn btn-sm" type="button" data-new-playlist><x-icon name="plus" :size="15" /> New playlist</button>
            @endauth
        </div>
    </div>

    @if ($playlists->isEmpty())
        <div class="empty" style="padding:2.5rem 1rem">
            <div class="em-icon"><x-icon name="playlist" :size="32" /></div>
            <h3>{{ $mine ? 'You have no playlists yet' : 'No playlists yet' }}</h3>
            <p>Create one, then use the &hellip; menu on any song to add it.</p>
            @auth
                <button class="btn btn-sm" type="button" data-new-playlist>Create a playlist</button>
            @endauth
        </div>
    @else
        <div class="pl-grid">
            @foreach ($playlists as $playlist)
                @include('songs.partials.playlist-card', ['playlist' => $playlist])
            @endforeach
        </div>

        <div data-music-pager>
            @include('partials.pager', ['paginator' => $playlists])
        </div>
    @endif
@endsection
