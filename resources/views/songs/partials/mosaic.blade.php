{{-- Playlist artwork: a 2x2 grid of song covers, or a gradient when there are none. --}}
@php
    $tiles = $playlist->mosaic();
    $hue = crc32($playlist->name.$playlist->id) % 360;
@endphp

<span class="pl-art {{ count($tiles) >= 4 ? 'is-grid' : '' }} {{ $big ?? false ? 'is-big' : '' }}" style="--hue: {{ $hue }}">
    @if (count($tiles) >= 4)
        @foreach ($tiles as $tile)
            <span class="pl-tile" style="--hue: {{ $tile['hue'] }}">
                @if ($tile['cover'])<img src="{{ $tile['cover'] }}" alt="" loading="lazy">@endif
            </span>
        @endforeach
    @elseif (! empty($tiles) && $tiles[0]['cover'])
        <img src="{{ $tiles[0]['cover'] }}" alt="" loading="lazy">
    @else
        <x-icon name="playlist" :size="($big ?? false) ? 54 : 34" />
    @endif
    <span class="pl-play" data-play-playlist="{{ $playlist->id }}" aria-hidden="true"><x-icon name="play" :size="18" /></span>
</span>
