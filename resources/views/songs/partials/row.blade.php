{{-- One track. data-track carries what the player needs; the "more" menu is built by music.js. --}}
@php
    $isMine = auth()->id() === $song->user_id;
    $sortable = $sortable ?? false;
@endphp

<div class="mu-row" data-track="{{ json_encode($song->toPlayerArray()) }}" data-song-id="{{ $song->id }}"
     @if ($isMine) data-edit-url="{{ route('songs.edit', $song) }}" data-delete-url="{{ route('songs.destroy', $song) }}" @endif
     @isset($removeUrl) data-remove-url="{{ $removeUrl }}" @endisset
     @if ($sortable) draggable="true" @endif>

    @if ($sortable)
        <span class="mu-grip" title="Drag to reorder"><x-icon name="grip" :size="16" /></span>
    @endif

    <button class="mu-num" type="button" data-play-row aria-label="Play {{ $song->title }}">
        <span class="mu-n">{{ $index }}</span>
        <span class="mu-p"><x-icon name="play" :size="15" /></span>
        <span class="mu-eq" aria-hidden="true"><i></i><i></i><i></i></span>
    </button>

    <span class="mu-cover" style="--hue: {{ $song->hue() }}">
        @if ($song->coverUrl())
            <img src="{{ $song->coverUrl() }}" alt="" loading="lazy">
        @else
            <x-icon name="music" :size="17" />
        @endif
    </span>

    <span class="mu-main">
        <span class="mu-title">{{ $song->title }}</span>
        @if ($song->artist)
            <a class="mu-artist" href="{{ route('songs.index', ['artist' => $song->artist]) }}" data-music-link>{{ $song->artist }}</a>
        @else
            <span class="mu-artist">Unknown artist</span>
        @endif
    </span>

    <span class="mu-album">{{ $song->album }}</span>

    <span class="mu-plays" title="{{ number_format($song->plays) }} plays">
        {{ \App\Support\MediaMeta::compactNumber($song->plays) }}
    </span>

    @auth
        @php $isLiked = in_array($song->id, $likedIds ?? []); @endphp
        <button class="mu-like {{ $isLiked ? 'is-on' : '' }}" type="button" data-like-song="{{ $song->id }}"
                aria-pressed="{{ $isLiked ? 'true' : 'false' }}" aria-label="Like {{ $song->title }}">
            <span class="ic-off"><x-icon name="heart" :size="17" /></span>
            <span class="ic-on"><x-icon name="heart-fill" :size="17" /></span>
        </button>
    @endauth

    <span class="mu-dur">{{ $song->durationLabel() }}</span>

    <button class="mu-more" type="button" data-row-menu aria-label="More options for {{ $song->title }}">
        <x-icon name="more" :size="18" />
    </button>
</div>
