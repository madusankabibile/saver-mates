{{-- The music player lives in the right column. music.js swaps only the middle
     panel between music pages, so this keeps playing while you browse. --}}
<section class="panel np" data-player
         data-like-url="{{ url('/songs/__ID__/like') }}"
         data-play-url="{{ url('/songs/__ID__/play') }}"
         data-playlist-add-url="{{ url('/songs/playlists/__ID__/songs') }}"
         data-playlist-store-url="{{ route('playlists.store') }}"
         data-signed-in="{{ auth()->check() ? '1' : '' }}">

    <div class="np-art" data-np-art style="--hue: 152">
        <img data-np-cover alt="" hidden>
        <span class="np-art-empty"><x-icon name="music" :size="44" /></span>
        <canvas class="np-viz" data-np-viz aria-hidden="true"></canvas>
    </div>

    <div class="np-info">
        <div class="np-text">
            <div class="np-title" data-np-title>Nothing playing</div>
            <div class="np-artist" data-np-artist>Pick a song to start listening</div>
        </div>
        @auth
            <button class="mu-like np-like" type="button" data-np-like hidden aria-label="Like this song">
                <span class="ic-off"><x-icon name="heart" :size="19" /></span>
                <span class="ic-on"><x-icon name="heart-fill" :size="19" /></span>
            </button>
        @endauth
    </div>

    <div class="np-seek">
        <span data-np-time>0:00</span>
        <input type="range" min="0" max="1000" value="0" step="1" data-np-seek aria-label="Seek">
        <span data-np-dur>0:00</span>
    </div>

    <div class="np-controls">
        <button class="np-btn" type="button" data-np-shuffle title="Shuffle (S)" aria-pressed="false"><x-icon name="shuffle" :size="18" /></button>
        <button class="np-btn" type="button" data-np-prev title="Previous (Shift+P)"><x-icon name="skip-prev" :size="20" /></button>
        <button class="np-btn np-play" type="button" data-np-toggle title="Play / pause (Space)">
            <span class="np-i-play"><x-icon name="play" :size="22" /></span>
            <span class="np-i-pause"><x-icon name="pause" :size="22" /></span>
        </button>
        <button class="np-btn" type="button" data-np-next title="Next (Shift+N)"><x-icon name="skip-next" :size="20" /></button>
        <button class="np-btn" type="button" data-np-repeat title="Repeat (R)" data-mode="off">
            <span class="np-i-repeat"><x-icon name="repeat" :size="18" /></span>
            <span class="np-i-one"><x-icon name="repeat-one" :size="18" /></span>
        </button>
    </div>

    <div class="np-extra">
        <button class="np-btn np-btn-sm" type="button" data-np-mute title="Mute (M)">
            <span class="np-i-vol"><x-icon name="volume" :size="17" /></span>
            <span class="np-i-mute"><x-icon name="mute" :size="17" /></span>
        </button>
        <input type="range" min="0" max="100" value="80" data-np-volume aria-label="Volume">

        <div class="dropdown" data-dropdown="sleep">
            <button class="np-btn np-btn-sm" type="button" data-dropdown-toggle title="Sleep timer" aria-expanded="false">
                <x-icon name="moon" :size="17" /> <span class="tiny" data-np-sleep-label></span>
            </button>
            <div class="dropdown-panel np-sleep-menu" data-dropdown-panel hidden>
                <div class="dropdown-head"><h3>Sleep timer</h3></div>
                <div style="padding:.3rem 0">
                    @foreach ([0 => 'Off', 15 => '15 minutes', 30 => '30 minutes', 60 => '1 hour', -1 => 'End of this song'] as $min => $label)
                        <button class="menu-link" type="button" data-sleep="{{ $min }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

<section class="panel np-queue-panel">
    <div class="panel-head">
        <x-icon name="queue" :size="18" />
        <h3>Up next</h3>
        <button class="btn btn-ghost btn-sm" type="button" data-np-clear style="margin-left:auto">Clear</button>
    </div>
    <div class="np-queue" data-np-queue>
        <p class="small muted">Play a song or a playlist and the queue shows up here.</p>
    </div>
    <p class="tiny muted np-keys">
        <kbd>Space</kbd> play &middot; <kbd>Shift</kbd>+<kbd>N</kbd>/<kbd>P</kbd> next / previous &middot; <kbd>&larr;</kbd><kbd>&rarr;</kbd> seek
    </p>
</section>

{{-- Compact bar for phones, where the right column sits below the page. --}}
<div class="np-mini" data-np-mini hidden>
    <span class="np-mini-cover" data-np-mini-cover style="--hue: 152"><x-icon name="music" :size="18" /></span>
    <span class="np-mini-text">
        <span class="np-title" data-np-title>Nothing playing</span>
        <span class="np-artist" data-np-artist></span>
    </span>
    <button class="np-btn np-play np-btn-sm" type="button" data-np-toggle aria-label="Play / pause">
        <span class="np-i-play"><x-icon name="play" :size="18" /></span>
        <span class="np-i-pause"><x-icon name="pause" :size="18" /></span>
    </button>
    <button class="np-btn np-btn-sm" type="button" data-np-next aria-label="Next"><x-icon name="skip-next" :size="18" /></button>
    <span class="np-mini-progress"><i data-np-mini-bar></i></span>
</div>

<audio data-audio preload="metadata"></audio>

<script type="application/json" data-my-playlists>@json($myPlaylists)</script>
