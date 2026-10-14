{{-- Keeps music going outside the music section (miniplayer.js). Hidden
     unless a song was playing when the previous page closed. --}}
<div class="mini-player" data-mini-player hidden role="region" aria-label="Now playing">
    <span class="mp-cover" data-mp-cover aria-hidden="true"></span>

    <a class="mp-text" href="{{ route('songs.index') }}" title="Open the music player">
        <span class="mp-title" data-mp-title></span>
        <span class="mp-artist" data-mp-artist></span>
    </a>

    <span class="mp-time" data-mp-time></span>

    <div class="mp-controls">
        <button type="button" class="mp-btn" data-mp-prev aria-label="Previous"><x-icon name="skip-prev" :size="16" /></button>
        <button type="button" class="mp-btn mp-play" data-mp-toggle aria-label="Pause">
            <span class="mp-i-play"><x-icon name="play" :size="18" /></span>
            <span class="mp-i-pause"><x-icon name="pause" :size="18" /></span>
        </button>
        <button type="button" class="mp-btn" data-mp-next aria-label="Next"><x-icon name="skip-next" :size="16" /></button>
        <button type="button" class="mp-btn mp-close" data-mp-close aria-label="Stop and close"><x-icon name="close" :size="15" /></button>
    </div>

    <span class="mp-progress" aria-hidden="true"><i data-mp-bar></i></span>
</div>
