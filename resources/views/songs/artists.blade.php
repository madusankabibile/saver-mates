@extends('songs.layout')

@section('title', 'Artists')

@section('music')
    <div class="mu-section-head">
        <div>
            <h2>Artists</h2>
            <p class="small muted">{{ $artists->count() }} {{ \Illuminate\Support\Str::plural('artist', $artists->count()) }}</p>
        </div>
    </div>

    @if ($artists->isEmpty())
        <div class="empty" style="padding:2.5rem 1rem">
            <div class="em-icon"><x-icon name="people" :size="32" /></div>
            <h3>No artists yet</h3>
            <p>Artists appear once songs with an artist name are uploaded.</p>
        </div>
    @else
        <div class="ar-grid">
            @foreach ($artists as $a)
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
@endsection
