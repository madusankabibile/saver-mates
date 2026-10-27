@extends('layouts.app')

{{-- Every music page: section tabs + page body in the middle, the player on the right.
     music.js swaps the middle panel between these pages so playback never stops. --}}

@section('shell-class', 'shell-music')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/media.css') }}?v={{ filemtime(public_path('css/media.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/uploader.js') }}?v={{ filemtime(public_path('js/uploader.js')) }}" defer></script>
    <script src="{{ asset('js/music.js') }}?v={{ filemtime(public_path('js/music.js')) }}" defer></script>
@endpush

@section('content')
    <div class="mu-top">
        <div>
            <h1>Music</h1>
            <p class="small muted">{{ number_format($totalSongs) }} {{ \Illuminate\Support\Str::plural('song', $totalSongs) }} shared on the network</p>
        </div>
        @auth
            <a class="btn btn-sm" href="{{ route('songs.create') }}"><x-icon name="upload" :size="15" /> Upload songs</a>
        @endauth
    </div>

    @include('songs.partials.nav')

    <div data-music-view>
        @yield('music')
    </div>
@endsection

@section('aside')
    @include('songs.partials.player')
@endsection
