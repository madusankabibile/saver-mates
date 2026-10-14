/* ==========================================================================
   Mini player: keeps a song going on every page outside the music section.
   Picks up the queue and position music.js saved (see player-sync.js),
   carries on playing, and offers play/pause, previous, next and a link
   back to the full player. On music pages music.js does all of this.
   ========================================================================== */

(function () {
    'use strict';

    const sync = window.SMPlayerSync;
    const bar = document.querySelector('[data-mini-player]');
    if (!sync || !bar || document.querySelector('[data-player]')) return;

    const $ = (sel) => bar.querySelector(sel);
    const saved = sync.readState();
    const state = {
        queue: Array.isArray(saved.queue) ? saved.queue : [],
        order: Array.isArray(saved.order) ? saved.order : [],
        pos: Number.isInteger(saved.pos) ? saved.pos : -1,
        repeat: ['off', 'all', 'one'].includes(saved.repeat) ? saved.repeat : 'off',
        volume: typeof saved.volume === 'number' ? saved.volume : 0.8,
        muted: !!saved.muted,
    };
    const current = () => state.queue[state.order[state.pos]] || null;

    // Only appears when a song was playing as the last page closed.
    if (!current() || !sync.shouldResume(saved)) return;

    const audio = new Audio();
    audio.preload = 'auto';
    audio.volume = state.volume;
    audio.muted = state.muted;

    let counted = !!saved.counted;

    function persist() {
        sync.writeState({
            ...sync.readState(),
            pos: state.pos,
            order: state.order,
            time: audio.currentTime || 0,
            playing: !audio.paused,
            savedAt: Date.now(),
            counted,
        });
    }

    const fmt = (s) => {
        s = Math.max(0, Math.floor(s || 0));
        return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
    };

    function render() {
        const t = current();
        if (!t) return;

        $('[data-mp-title]').textContent = t.title;
        $('[data-mp-artist]').textContent = t.artist || '';
        const cover = $('[data-mp-cover]');
        cover.style.setProperty('--hue', t.hue ?? 152);
        cover.innerHTML = t.cover ? `<img src="${window.SM.escapeHtml(t.cover)}" alt="">` : '&#9835;';
        renderButtons();

        if ('mediaSession' in navigator) {
            navigator.mediaSession.metadata = new MediaMetadata({
                title: t.title,
                artist: t.artist || '',
                artwork: t.cover ? [{ src: t.cover, sizes: '512x512' }] : [],
            });
        }
    }

    function renderButtons() {
        bar.classList.toggle('is-playing', !audio.paused);
        $('[data-mp-toggle]').setAttribute('aria-label', audio.paused ? 'Play' : 'Pause');
    }

    function load(track, startAt) {
        counted = startAt > 0 ? counted : false;
        audio.src = track.src;
        if (startAt) audio.addEventListener('loadedmetadata', () => (audio.currentTime = startAt), { once: true });
        render();
        play();
    }

    function play(fromResume = false) {
        audio.play().catch(() => {
            // The browser wants a click first: show it as paused with a hint.
            bar.classList.add('needs-tap');
            if (fromResume) $('[data-mp-artist]').textContent = 'Press play to carry on listening';
            renderButtons();
        });
    }

    function next(auto) {
        if (auto && state.repeat === 'one') {
            audio.currentTime = 0;
            return play();
        }
        if (state.pos + 1 < state.order.length) {
            state.pos++;
        } else if (state.repeat === 'all' || !auto) {
            state.pos = 0;
        } else {
            persist();
            return; // end of the queue
        }
        load(current(), 0);
        persist();
    }

    function prev() {
        if (audio.currentTime > 3 || state.pos <= 0) {
            audio.currentTime = 0;
            return play();
        }
        state.pos--;
        load(current(), 0);
        persist();
    }

    function toggle() {
        bar.classList.remove('needs-tap');
        if (audio.paused) {
            play();
            render();
        } else {
            audio.pause();
        }
    }

    /* Wire up ----------------------------------------------------------- */

    $('[data-mp-toggle]').addEventListener('click', toggle);
    $('[data-mp-next]').addEventListener('click', () => next(false));
    $('[data-mp-prev]').addEventListener('click', prev);
    $('[data-mp-close]').addEventListener('click', () => {
        audio.pause();
        bar.hidden = true;
        document.body.classList.remove('has-mini-bar');
    });

    audio.addEventListener('play', () => { renderButtons(); sync.claim(); persist(); });
    audio.addEventListener('pause', () => {
        renderButtons();
        if (!sync.leaving) {
            sync.release();
            persist();
        }
    });
    audio.addEventListener('ended', () => next(true));
    audio.addEventListener('error', () => setTimeout(() => next(true), 800));

    let lastSave = 0;
    audio.addEventListener('timeupdate', () => {
        const d = audio.duration || current()?.duration || 0;
        $('[data-mp-bar]').style.width = (d ? (audio.currentTime / d) * 100 : 0) + '%';
        $('[data-mp-time]').textContent = `${fmt(audio.currentTime)} / ${fmt(d)}`;

        // Count the play once it has really been listened to (once per song).
        const t = current();
        if (t && !counted && (audio.currentTime >= 30 || (audio.duration && audio.currentTime >= audio.duration * 0.5))) {
            counted = true;
            window.SM.post(window.SM.url(`/songs/${t.id}/play`), { duration: audio.duration || null }).catch(() => {});
        }

        if (Date.now() - lastSave > 2000) {
            lastSave = Date.now();
            persist();
        }
    });

    // The page is closing (a link was clicked): save the exact spot.
    window.addEventListener('pagehide', persist);

    // Another tab started music: step aside.
    sync.onTakenOver(() => { if (!audio.paused) audio.pause(); });

    // A movie starting on this page pauses the music.
    document.addEventListener('play', (e) => {
        if (e.target instanceof HTMLVideoElement && !audio.paused) audio.pause();
    }, true);

    if ('mediaSession' in navigator) {
        const set = (a, fn) => { try { navigator.mediaSession.setActionHandler(a, fn); } catch (_) { /* unsupported */ } };
        set('play', () => play());
        set('pause', () => audio.pause());
        set('nexttrack', () => next(false));
        set('previoustrack', prev);
    }

    /* Start ------------------------------------------------------------- */

    bar.hidden = false;
    document.body.classList.add('has-mini-bar');
    render();

    const at = sync.resumeAt(saved, current().duration);
    audio.src = current().src;
    if (at) audio.addEventListener('loadedmetadata', () => (audio.currentTime = at), { once: true });
    play(true);
})();
