/* ==========================================================================
   Music: the player in the right column, track lists, playlists and the
   upload page. Moving between music pages swaps only the middle panel
   (fetch + history.pushState), so the <audio> element in the right column
   keeps playing. Relies on window.SM from app.js.
   ========================================================================== */

(function () {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const playerEl = $('[data-player]');
    const audio = $('[data-audio]');
    if (!playerEl || !audio) return;

    const STATE_KEY = 'sm.music.state';
    const signedIn = playerEl.dataset.signedIn === '1';
    const urlFor = (key, id) => playerEl.dataset[key].replace('__ID__', id);

    const store = {
        get(key, fallback) {
            try {
                const v = localStorage.getItem(key);
                return v === null ? fallback : JSON.parse(v);
            } catch (_) {
                return fallback;
            }
        },
        set(key, value) {
            try { localStorage.setItem(key, JSON.stringify(value)); } catch (_) { /* full or blocked */ }
        },
    };

    const fmt = (s) => {
        if (!isFinite(s) || s < 0) s = 0;
        s = Math.floor(s);
        const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
        return h ? `${h}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}` : `${m}:${String(sec).padStart(2, '0')}`;
    };

    const typing = (el) => el && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName));

    /* ====================================================================
       Toast
       ==================================================================== */

    const toastEl = document.createElement('div');
    toastEl.className = 'mu-toast';
    toastEl.hidden = true;
    document.body.appendChild(toastEl);
    let toastTimer;

    function toast(text) {
        toastEl.textContent = text;
        toastEl.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => (toastEl.hidden = true), 2400);
    }

    /* ====================================================================
       Player state
       queue: tracks, order: indexes into queue in play order, pos: index into order
       ==================================================================== */

    const saved = store.get(STATE_KEY, {});
    const state = {
        queue: Array.isArray(saved.queue) ? saved.queue : [],
        order: Array.isArray(saved.order) ? saved.order : [],
        pos: Number.isInteger(saved.pos) ? saved.pos : -1,
        shuffle: !!saved.shuffle,
        repeat: ['off', 'all', 'one'].includes(saved.repeat) ? saved.repeat : 'off',
        volume: typeof saved.volume === 'number' ? saved.volume : 0.8,
        muted: !!saved.muted,
    };

    const current = () => state.queue[state.order[state.pos]] || null;

    // Shared with the mini player on other pages (player-sync.js).
    const sync = window.SMPlayerSync || {
        shouldResume: () => false, resumeAt: (s) => s.time || 0,
        claim() {}, release() {}, onTakenOver() {}, leaving: false,
    };

    function persist() {
        store.set(STATE_KEY, {
            queue: state.queue,
            order: state.order,
            pos: state.pos,
            shuffle: state.shuffle,
            repeat: state.repeat,
            volume: state.volume,
            muted: state.muted,
            time: audio.currentTime || 0,
            // Lets the next page carry on from here.
            playing: !audio.paused,
            savedAt: Date.now(),
            counted, // this track's play is already counted; don't count it again on the next page
        });
    }

    function shuffled(list) {
        const a = list.slice();
        for (let i = a.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [a[i], a[j]] = [a[j], a[i]];
        }
        return a;
    }

    /** Order the queue starting from queue index `start`; returns the new pos. */
    function buildOrder(start) {
        const all = state.queue.map((_, i) => i);
        if (state.shuffle) {
            state.order = [start, ...shuffled(all.filter((i) => i !== start))];
            return 0;
        }
        state.order = all;
        return start;
    }

    /* ====================================================================
       Playback
       ==================================================================== */

    let counted = false;

    function load(track, autoplay, startAt = 0) {
        if (!track) return;
        counted = false;
        audio.src = track.src;
        if (startAt) {
            audio.addEventListener('loadedmetadata', () => (audio.currentTime = startAt), { once: true });
        }
        render();
        if (autoplay) play();
    }

    function play() {
        if (!audio.src && current()) load(current(), false);
        ensureVisualizer();
        audioCtx?.resume?.();
        const wasResuming = resuming;
        resuming = false;
        audio.play().catch(() => {
            // Blocked until the user interacts (the browser's autoplay rule).
            if (wasResuming) toast('Press play to carry on listening');
            renderButtons();
        });
    }

    let resuming = false;

    function playList(tracks, startIndex = 0, opts = {}) {
        if (!tracks.length) return;
        state.queue = tracks;
        if (opts.shuffle !== undefined) state.shuffle = opts.shuffle;
        const start = opts.shuffle ? Math.floor(Math.random() * tracks.length) : startIndex;
        state.pos = buildOrder(start);
        load(current(), true);
        persist();
    }

    function next(auto = false) {
        if (!state.order.length) return;

        if (auto && state.repeat === 'one') {
            audio.currentTime = 0;
            return play();
        }

        if (auto && stopAfterCurrent) {
            stopAfterCurrent = false;
            setSleep(0);
            return;
        }

        if (state.pos + 1 < state.order.length) {
            state.pos++;
        } else if (state.repeat === 'all' || !auto) {
            if (state.shuffle) state.order = shuffled(state.order);
            state.pos = 0;
        } else {
            render();
            return; // end of the queue
        }

        load(current(), true);
        persist();
    }

    function prev() {
        if (audio.currentTime > 3 || state.pos <= 0) {
            audio.currentTime = 0;
            return play();
        }
        state.pos--;
        load(current(), true);
        persist();
    }

    function jumpTo(orderPos) {
        state.pos = orderPos;
        load(current(), true);
        persist();
    }

    function insertNext(track) {
        state.queue.push(track);
        const idx = state.queue.length - 1;
        if (state.pos < 0) {
            state.order = [idx];
            state.pos = 0;
            load(track, true);
        } else {
            state.order.splice(state.pos + 1, 0, idx);
        }
        renderQueue();
        persist();
    }

    function enqueue(track) {
        state.queue.push(track);
        state.order.push(state.queue.length - 1);
        if (state.pos < 0) {
            state.pos = 0;
            load(track, true);
        }
        renderQueue();
        persist();
    }

    audio.addEventListener('ended', () => next(true));

    /* Count a play once the track has really been listened to. */
    audio.addEventListener('timeupdate', () => {
        const t = current();
        if (!t || counted) return;
        if (audio.currentTime >= 30 || (audio.duration && audio.currentTime >= audio.duration * 0.5)) {
            counted = true;
            SM.post(urlFor('playUrl', t.id), { duration: audio.duration || null }).catch(() => {});
        }
    });

    /* ====================================================================
       Rendering
       ==================================================================== */

    const els = {
        cover: $('[data-np-cover]', playerEl),
        art: $('[data-np-art]', playerEl),
        time: $('[data-np-time]', playerEl),
        dur: $('[data-np-dur]', playerEl),
        seek: $('[data-np-seek]', playerEl),
        shuffle: $('[data-np-shuffle]', playerEl),
        repeat: $('[data-np-repeat]', playerEl),
        volume: $('[data-np-volume]', playerEl),
        like: $('[data-np-like]', playerEl),
        queue: $('[data-np-queue]'),
        mini: $('[data-np-mini]'),
        miniBar: $('[data-np-mini-bar]'),
        miniCover: $('[data-np-mini-cover]'),
    };

    function render() {
        const t = current();

        $$('[data-np-title]').forEach((el) => (el.textContent = t ? t.title : 'Nothing playing'));
        $$('[data-np-artist]').forEach((el) => (el.textContent = t ? t.artist : 'Pick a song to start listening'));

        els.art.style.setProperty('--hue', t ? t.hue : 152);
        els.miniCover.style.setProperty('--hue', t ? t.hue : 152);
        if (t && t.cover) {
            els.cover.src = t.cover;
            els.cover.hidden = false;
            els.miniCover.innerHTML = `<img src="${SM.escapeHtml(t.cover)}" alt="">`;
        } else {
            els.cover.hidden = true;
            els.cover.removeAttribute('src');
            els.miniCover.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 17.5V6.2l10-2v11"/><circle cx="6.6" cy="17.6" r="2.4"/><circle cx="16.6" cy="15.3" r="2.4"/></svg>';
        }

        if (els.like) {
            els.like.hidden = !t;
            if (t) setLikeButton(els.like, isLiked(t.id));
        }

        els.mini.hidden = !t;
        document.body.classList.toggle('has-mini-player', !!t);

        renderButtons();
        renderQueue();
        highlightRows();
        mediaSession(t);
    }

    function renderButtons() {
        const playing = !audio.paused;
        $$('[data-np-toggle]').forEach((b) => b.classList.toggle('is-playing', playing));
        playerEl.classList.toggle('is-playing', playing);
        els.shuffle.classList.toggle('is-on', state.shuffle);
        els.shuffle.setAttribute('aria-pressed', String(state.shuffle));
        els.repeat.dataset.mode = state.repeat;
        els.repeat.classList.toggle('is-on', state.repeat !== 'off');
        els.repeat.title = { off: 'Repeat: off (R)', all: 'Repeat: all (R)', one: 'Repeat: this song (R)' }[state.repeat];
        $('[data-np-mute]', playerEl).classList.toggle('is-muted', state.muted || state.volume === 0);
    }

    function renderQueue() {
        if (!state.order.length || state.pos < 0) {
            els.queue.innerHTML = '<p class="small muted">Play a song or a playlist and the queue shows up here.</p>';
            return;
        }

        // Current track plus the next 30.
        const html = [];
        for (let p = state.pos; p < Math.min(state.order.length, state.pos + 31); p++) {
            const t = state.queue[state.order[p]];
            if (!t) continue;
            html.push(`
              <div class="np-q ${p === state.pos ? 'is-current' : ''}" data-q-pos="${p}">
                <span class="np-q-cover" style="--hue:${t.hue}">${t.cover ? `<img src="${SM.escapeHtml(t.cover)}" alt="" loading="lazy">` : ''}</span>
                <span class="np-q-text"><span class="np-q-title">${SM.escapeHtml(t.title)}</span><span class="np-q-artist">${SM.escapeHtml(t.artist)}</span></span>
                ${p === state.pos ? '<span class="mu-eq is-on" aria-hidden="true"><i></i><i></i><i></i></span>' : `<button type="button" class="np-q-remove" data-q-remove="${p}" aria-label="Remove from queue">&#10005;</button>`}
              </div>`);
        }
        const more = state.order.length - state.pos - 31;
        if (more > 0) html.push(`<p class="tiny muted" style="padding:.4rem .2rem">and ${more} more</p>`);
        els.queue.innerHTML = html.join('');
    }

    function highlightRows() {
        const t = current();
        const playing = !audio.paused;
        $$('.mu-row').forEach((row) => {
            const on = t && Number(row.dataset.songId) === t.id;
            row.classList.toggle('is-current', !!on);
            row.classList.toggle('is-playing', !!on && playing);
        });
    }

    audio.addEventListener('play', () => { renderButtons(); highlightRows(); startViz(); sync.claim(); persist(); });
    audio.addEventListener('pause', () => {
        renderButtons();
        highlightRows();
        // A page being closed is not a pause: keep "playing" so the next page resumes.
        if (!sync.leaving) {
            sync.release();
            persist();
        }
    });

    // Music started in another tab: this one steps aside.
    sync.onTakenOver(() => {
        if (!audio.paused) {
            audio.pause();
            toast('Paused: music is playing in another tab');
        }
    });

    let lastPersist = 0;
    audio.addEventListener('timeupdate', () => {
        const d = audio.duration || current()?.duration || 0;
        els.time.textContent = fmt(audio.currentTime);
        els.dur.textContent = fmt(d);
        if (!seeking) els.seek.value = d ? Math.round((audio.currentTime / d) * 1000) : 0;
        els.seek.style.setProperty('--fill', (d ? (audio.currentTime / d) * 100 : 0) + '%');
        els.miniBar.style.width = (d ? (audio.currentTime / d) * 100 : 0) + '%';

        if (Date.now() - lastPersist > 2000) {
            lastPersist = Date.now();
            persist();
        }
    });

    audio.addEventListener('loadedmetadata', () => (els.dur.textContent = fmt(audio.duration)));

    audio.addEventListener('error', () => {
        if (current()) toast('This track could not be played. Skipping.');
        setTimeout(() => next(true), 800);
    });

    /* ====================================================================
       Controls
       ==================================================================== */

    let seeking = false;
    els.seek.addEventListener('input', () => {
        seeking = true;
        const d = audio.duration || 0;
        els.time.textContent = fmt((els.seek.value / 1000) * d);
        els.seek.style.setProperty('--fill', els.seek.value / 10 + '%');
    });
    els.seek.addEventListener('change', () => {
        if (audio.duration) audio.currentTime = (els.seek.value / 1000) * audio.duration;
        seeking = false;
    });

    const toggle = () => {
        if (!current()) {
            // Nothing queued yet: start the list on the page.
            const rows = $$('[data-tracklist] .mu-row');
            if (rows.length) playList(rows.map(rowTrack), 0);
            return;
        }
        audio.paused ? play() : audio.pause();
    };

    $$('[data-np-toggle]').forEach((b) => b.addEventListener('click', toggle));
    $$('[data-np-next]').forEach((b) => b.addEventListener('click', () => next(false)));
    $('[data-np-prev]', playerEl).addEventListener('click', prev);

    const toggleShuffle = () => {
        state.shuffle = !state.shuffle;
        const cur = state.order[state.pos];
        if (cur !== undefined) {
            if (state.shuffle) {
                state.order = [cur, ...shuffled(state.order.filter((i) => i !== cur))];
                state.pos = 0;
            } else {
                state.order = state.queue.map((_, i) => i);
                state.pos = cur;
            }
        }
        toast(state.shuffle ? 'Shuffle on' : 'Shuffle off');
        renderButtons();
        renderQueue();
        persist();
    };
    els.shuffle.addEventListener('click', toggleShuffle);

    const cycleRepeat = () => {
        state.repeat = { off: 'all', all: 'one', one: 'off' }[state.repeat];
        toast({ off: 'Repeat off', all: 'Repeat all', one: 'Repeat this song' }[state.repeat]);
        renderButtons();
        persist();
    };
    els.repeat.addEventListener('click', cycleRepeat);

    function applyVolume() {
        audio.volume = state.volume;
        audio.muted = state.muted;
        els.volume.value = Math.round(state.volume * 100);
        els.volume.style.setProperty('--fill', (state.muted ? 0 : state.volume * 100) + '%');
        renderButtons();
    }

    els.volume.addEventListener('input', () => {
        state.volume = els.volume.value / 100;
        state.muted = false;
        applyVolume();
        persist();
    });

    const toggleMute = () => {
        state.muted = !state.muted;
        applyVolume();
        persist();
    };
    $('[data-np-mute]', playerEl).addEventListener('click', toggleMute);

    els.queue.addEventListener('click', (e) => {
        const remove = e.target.closest('[data-q-remove]');
        if (remove) {
            const p = Number(remove.dataset.qRemove);
            state.order.splice(p, 1);
            renderQueue();
            persist();
            return;
        }
        const item = e.target.closest('[data-q-pos]');
        if (item && Number(item.dataset.qPos) !== state.pos) jumpTo(Number(item.dataset.qPos));
    });

    $('[data-np-clear]').addEventListener('click', () => {
        // Keep the current song, drop everything after it.
        if (state.pos >= 0) state.order = state.order.slice(0, state.pos + 1);
        renderQueue();
        persist();
    });

    els.like?.addEventListener('click', () => current() && toggleLike(current().id));

    /* Sleep timer ---------------------------------------------------------- */
    let sleepTimer = null, sleepTick = null, stopAfterCurrent = false;
    const sleepLabel = $('[data-np-sleep-label]', playerEl);

    function setSleep(minutes) {
        clearTimeout(sleepTimer);
        clearInterval(sleepTick);
        stopAfterCurrent = false;
        sleepLabel.textContent = '';

        if (minutes === -1) {
            stopAfterCurrent = true;
            sleepLabel.textContent = 'end';
            toast('Stopping after this song');
        } else if (minutes > 0) {
            const endAt = Date.now() + minutes * 60000;
            const tick = () => (sleepLabel.textContent = Math.ceil((endAt - Date.now()) / 60000) + 'm');
            tick();
            sleepTick = setInterval(tick, 15000);
            sleepTimer = setTimeout(() => {
                audio.pause();
                setSleep(0);
                toast('Sleep timer: paused');
            }, minutes * 60000);
            toast(`Pausing in ${minutes} minutes`);
        } else if (minutes === 0 && sleepTimer !== null) {
            toast('Sleep timer off');
        }
    }

    $$('[data-sleep]', playerEl).forEach((b) => b.addEventListener('click', () => {
        setSleep(Number(b.dataset.sleep));
        b.closest('[data-dropdown-panel]').hidden = true;
    }));

    /* ====================================================================
       Visualizer (Web Audio). The audio file is same-origin, so routing the
       element through an AnalyserNode is allowed.
       ==================================================================== */

    let audioCtx = null, analyser = null, vizData = null, vizFrame = null;
    const canvas = $('[data-np-viz]', playerEl);
    const ctx2d = canvas.getContext('2d');

    function ensureVisualizer() {
        if (audioCtx || !window.AudioContext) return;
        try {
            audioCtx = new AudioContext();
            const source = audioCtx.createMediaElementSource(audio);
            analyser = audioCtx.createAnalyser();
            analyser.fftSize = 64;
            analyser.smoothingTimeConstant = 0.8;
            source.connect(analyser);
            analyser.connect(audioCtx.destination);
            vizData = new Uint8Array(analyser.frequencyBinCount);
        } catch (_) {
            audioCtx = null;
        }
    }

    function startViz() {
        if (!analyser || vizFrame) return;
        const draw = () => {
            const w = (canvas.width = canvas.clientWidth * devicePixelRatio);
            const h = (canvas.height = canvas.clientHeight * devicePixelRatio);
            ctx2d.clearRect(0, 0, w, h);

            if (audio.paused) {
                vizFrame = null;
                return;
            }

            analyser.getByteFrequencyData(vizData);
            const bars = 24;
            const gap = 3 * devicePixelRatio;
            const bw = (w - gap * (bars - 1)) / bars;
            for (let i = 0; i < bars; i++) {
                const v = vizData[Math.floor((i / bars) * vizData.length * 0.85)] / 255;
                const bh = Math.max(2 * devicePixelRatio, v * h);
                ctx2d.fillStyle = `rgba(255,255,255,${0.35 + v * 0.55})`;
                const x = i * (bw + gap);
                ctx2d.beginPath();
                ctx2d.roundRect ? ctx2d.roundRect(x, h - bh, bw, bh, 2 * devicePixelRatio) : ctx2d.rect(x, h - bh, bw, bh);
                ctx2d.fill();
            }
            vizFrame = requestAnimationFrame(draw);
        };
        vizFrame = requestAnimationFrame(draw);
    }

    /* ====================================================================
       Media Session: lock screen / keyboard media keys
       ==================================================================== */

    function mediaSession(t) {
        if (!('mediaSession' in navigator) || !t) return;
        navigator.mediaSession.metadata = new MediaMetadata({
            title: t.title,
            artist: t.artist,
            album: t.album || '',
            artwork: t.cover ? [{ src: t.cover, sizes: '512x512' }] : [],
        });
    }

    if ('mediaSession' in navigator) {
        const ms = navigator.mediaSession;
        const set = (a, fn) => { try { ms.setActionHandler(a, fn); } catch (_) { /* unsupported */ } };
        set('play', play);
        set('pause', () => audio.pause());
        set('previoustrack', prev);
        set('nexttrack', () => next(false));
        set('seekbackward', () => (audio.currentTime = Math.max(0, audio.currentTime - 10)));
        set('seekforward', () => (audio.currentTime = Math.min(audio.duration || 0, audio.currentTime + 10)));
        set('seekto', (d) => (audio.currentTime = d.seekTime));
    }

    /* ====================================================================
       Keyboard
       ==================================================================== */

    document.addEventListener('keydown', (e) => {
        if (typing(document.activeElement) || e.ctrlKey || e.metaKey || e.altKey) return;
        if (document.querySelector('[data-modal]')) return;

        const k = e.key;
        if (k === ' ') {
            if (/^(BUTTON|A)$/.test(document.activeElement?.tagName)) return;
            toggle();
        } else if (e.shiftKey && (k === 'N' || k === 'n')) {
            next(false);
        } else if (e.shiftKey && (k === 'P' || k === 'p')) {
            prev();
        } else if (k === 'ArrowRight' && current()) {
            audio.currentTime = Math.min(audio.duration || 0, audio.currentTime + 5);
        } else if (k === 'ArrowLeft' && current()) {
            audio.currentTime = Math.max(0, audio.currentTime - 5);
        } else if (k === 'm' || k === 'M') {
            toggleMute();
        } else if (k === 's' || k === 'S') {
            toggleShuffle();
        } else if (k === 'r' || k === 'R') {
            cycleRepeat();
        } else if ((k === 'l' || k === 'L') && current() && signedIn) {
            toggleLike(current().id);
        } else {
            return;
        }
        e.preventDefault();
    });

    /* ====================================================================
       Track rows
       ==================================================================== */

    const rowTrack = (row) => JSON.parse(row.dataset.track);
    const listRows = (row) => $$('.mu-row', row.closest('[data-tracklist]') || document);

    document.addEventListener('click', (e) => {
        const playBtn = e.target.closest('[data-play-row]');
        if (playBtn) {
            const row = playBtn.closest('.mu-row');
            const t = current();
            if (t && t.id === Number(row.dataset.songId)) return toggle();
            const rows = listRows(row);
            state.shuffle = false;
            return playList(rows.map(rowTrack), rows.indexOf(row), { shuffle: false });
        }

        // Double-click anywhere on a row also plays it (handled below); single
        // clicks on the cover/title play too, like most music apps.
        const rowHit = e.target.closest('.mu-row .mu-cover, .mu-row .mu-title');
        if (rowHit) {
            rowHit.closest('.mu-row').querySelector('[data-play-row]').click();
            return;
        }

        const playAll = e.target.closest('[data-play-all]');
        if (playAll) {
            const rows = $$('[data-tracklist] .mu-row');
            if (!rows.length) return;
            playList(rows.map(rowTrack), 0, { shuffle: playAll.dataset.playAll === 'shuffle' });
            if (playAll.dataset.countUrl) SM.post(playAll.dataset.countUrl).catch(() => {});
            return;
        }

        const likeBtn = e.target.closest('[data-like-song]');
        if (likeBtn) return toggleLike(Number(likeBtn.dataset.likeSong));

        const more = e.target.closest('[data-row-menu]');
        if (more) {
            e.stopPropagation();
            return openRowMenu(more.closest('.mu-row'), more);
        }

        const card = e.target.closest('[data-play-playlist]');
        if (card) {
            e.preventDefault();
            e.stopPropagation();
            autoplayAfterLoad = true;
            return navigate(card.closest('a').href, true);
        }

        const copy = e.target.closest('[data-copy-link]');
        if (copy) return copyLink(copy.dataset.copyLink);

        if (e.target.closest('[data-new-playlist]')) return newPlaylist();

        const edit = e.target.closest('[data-edit-playlist]');
        if (edit) return editPlaylist(edit);
    }, true);

    document.addEventListener('dblclick', (e) => {
        const row = e.target.closest('.mu-row');
        if (row && !e.target.closest('button, a')) $('[data-play-row]', row).click();
    });

    /* Likes ------------------------------------------------------------------ */
    const likedIds = new Set();
    $$('[data-like-song].is-on').forEach((b) => likedIds.add(Number(b.dataset.likeSong)));
    const isLiked = (id) => likedIds.has(id);

    function setLikeButton(btn, on) {
        btn.classList.toggle('is-on', on);
        btn.setAttribute('aria-pressed', String(on));
    }

    function toggleLike(id) {
        if (!signedIn) return toast('Sign in to like songs');
        SM.post(urlFor('likeUrl', id)).then((r) => r.json()).then((d) => {
            d.liked ? likedIds.add(id) : likedIds.delete(id);
            $$(`[data-like-song="${id}"]`).forEach((b) => setLikeButton(b, d.liked));
            if (current()?.id === id && els.like) setLikeButton(els.like, d.liked);
            toast(d.liked ? 'Added to Liked songs' : 'Removed from Liked songs');
        }).catch(() => toast('Could not update your likes'));
    }

    /* Row menu --------------------------------------------------------------- */
    let menu = null;
    const closeMenu = () => { menu?.remove(); menu = null; };
    document.addEventListener('click', (e) => { if (menu && !e.target.closest('.mu-menu')) closeMenu(); });
    document.addEventListener('keydown', (e) => e.key === 'Escape' && closeMenu());
    window.addEventListener('scroll', closeMenu, { passive: true });

    function myPlaylists() {
        try { return JSON.parse($('[data-my-playlists]').textContent) || []; } catch (_) { return []; }
    }

    function openRowMenu(row, anchor) {
        closeMenu();
        const track = rowTrack(row);
        const lists = myPlaylists();

        menu = document.createElement('div');
        menu.className = 'mu-menu';
        menu.innerHTML = `
            <button type="button" data-m="next">Play next</button>
            <button type="button" data-m="queue">Add to queue</button>
            ${signedIn ? `<div class="mu-menu-sep"></div>
              <div class="mu-menu-label">Add to playlist</div>
              <div class="mu-menu-lists">
                ${lists.map((p) => `<button type="button" data-m="add" data-playlist="${p.id}">${SM.escapeHtml(p.name)}</button>`).join('')}
              </div>
              <button type="button" data-m="new">+ New playlist&hellip;</button>` : ''}
            ${row.dataset.removeUrl ? '<div class="mu-menu-sep"></div><button type="button" data-m="remove">Remove from this playlist</button>' : ''}
            ${row.dataset.editUrl ? `<div class="mu-menu-sep"></div>
              <a href="${row.dataset.editUrl}" data-music-link>Edit details</a>
              <button type="button" data-m="delete" class="is-danger">Delete song</button>` : ''}`;

        document.body.appendChild(menu);

        const r = anchor.getBoundingClientRect();
        const mw = menu.offsetWidth, mh = menu.offsetHeight;
        menu.style.left = Math.max(8, Math.min(window.innerWidth - mw - 8, r.right - mw)) + 'px';
        menu.style.top = (r.bottom + mh + 8 > window.innerHeight ? Math.max(8, r.top - mh - 4) : r.bottom + 4) + 'px';

        menu.addEventListener('click', (e) => {
            const b = e.target.closest('[data-m]');
            if (!b) return;
            const action = b.dataset.m;
            closeMenu();

            if (action === 'next') { insertNext(track); toast('Playing next: ' + track.title); }
            if (action === 'queue') { enqueue(track); toast('Added to queue'); }
            if (action === 'add') addToPlaylist(Number(b.dataset.playlist), track);
            if (action === 'new') newPlaylist(track);
            if (action === 'remove') removeFromPlaylist(row);
            if (action === 'delete') deleteSong(row, track);
        });
    }

    /* Playlists -------------------------------------------------------------- */
    function addToPlaylist(playlistId, track) {
        SM.post(urlFor('playlistAddUrl', playlistId), { song_id: track.id })
            .then((r) => r.json())
            .then((d) => toast(d.added ? `Added to ${d.name}` : `Already in ${d.name}`))
            .catch(() => toast('Could not add to the playlist'));
    }

    function newPlaylist(track) {
        if (!signedIn) return toast('Sign in to create playlists');
        SM.modal({
            title: 'New playlist',
            icon: '&#127925;',
            body: `<div class="field"><label for="pl-name">Name</label>
                     <input type="text" id="pl-name" maxlength="120" placeholder="Rainy day songs"></div>
                   <div class="field"><label for="pl-desc">Description <span class="muted">(optional)</span></label>
                     <textarea id="pl-desc" rows="2" maxlength="1000" style="min-height:0"></textarea></div>
                   <p class="hint">Playlists are public: anyone on the network can play them.</p>`,
            confirmLabel: track ? 'Create and add song' : 'Create playlist',
            focus: '#pl-name',
            onConfirm: (root) => {
                const name = $('#pl-name', root).value.trim();
                if (!name) return false;
                SM.post(playerEl.dataset.playlistStoreUrl, {
                    name,
                    description: $('#pl-desc', root).value.trim() || null,
                    song_id: track ? track.id : null,
                }).then((r) => r.json()).then((d) => {
                    const holder = $('[data-my-playlists]');
                    holder.textContent = JSON.stringify([{ id: d.id, name: d.name }, ...myPlaylists()]);
                    toast(track ? `Created "${d.name}" with ${track.title}` : `Created "${d.name}"`);
                    if (!track) navigate(d.url, true);
                }).catch(() => toast('Could not create the playlist'));
            },
        });
    }

    function editPlaylist(btn) {
        SM.modal({
            title: 'Edit playlist',
            icon: '&#9999;&#65039;',
            body: `<div class="field"><label for="pl-name">Name</label>
                     <input type="text" id="pl-name" maxlength="120" value="${SM.escapeHtml(btn.dataset.name)}"></div>
                   <div class="field"><label for="pl-desc">Description</label>
                     <textarea id="pl-desc" rows="3" maxlength="1000" style="min-height:0">${SM.escapeHtml(btn.dataset.description || '')}</textarea></div>`,
            confirmLabel: 'Save',
            focus: '#pl-name',
            onConfirm: (root) => {
                const name = $('#pl-name', root).value.trim();
                if (!name) return false;
                const form = new FormData();
                form.append('name', name);
                form.append('description', $('#pl-desc', root).value.trim());
                SM.post(btn.dataset.action, form).then(() => navigate(location.href, false)).catch(() => toast('Could not save'));
            },
        });
    }

    function removeFromPlaylist(row) {
        const form = new FormData();
        form.append('_method', 'DELETE');
        SM.post(row.dataset.removeUrl, form).then((r) => {
            if (!r.ok) throw r;
            const list = row.closest('[data-tracklist]');
            row.remove();
            renumber(list);
            toast('Removed from the playlist');
        }).catch(() => toast('Could not remove the song'));
    }

    function deleteSong(row, track) {
        SM.modal({
            title: 'Delete this song?',
            icon: '&#9888;&#65039;',
            body: `<p>"${SM.escapeHtml(track.title)}" is deleted for everyone and removed from every playlist.</p>`,
            confirmLabel: 'Delete song',
            danger: true,
            onConfirm: () => {
                const form = new FormData();
                form.append('_method', 'DELETE');
                SM.post(row.dataset.deleteUrl, form).then((r) => {
                    if (!r.ok) throw r;
                    row.remove();
                    toast('Song deleted');
                }).catch(() => toast('Could not delete the song'));
            },
        });
    }

    function renumber(list) {
        if (!list) return;
        $$('.mu-row', list).forEach((r, i) => ($('.mu-n', r).textContent = i + 1));
    }

    /* Drag to reorder (playlist owner) ------------------------------------- */
    let dragRow = null;

    document.addEventListener('dragstart', (e) => {
        const row = e.target.closest?.('[data-reorder-url] .mu-row');
        if (!row) return;
        dragRow = row;
        row.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', row.dataset.songId);
    });

    document.addEventListener('dragover', (e) => {
        if (!dragRow) return;
        const over = e.target.closest?.('.mu-row');
        if (!over || over === dragRow || over.parentNode !== dragRow.parentNode) return;
        e.preventDefault();
        const r = over.getBoundingClientRect();
        over.parentNode.insertBefore(dragRow, e.clientY < r.top + r.height / 2 ? over : over.nextSibling);
    });

    document.addEventListener('dragend', () => {
        if (!dragRow) return;
        const list = dragRow.closest('[data-reorder-url]');
        dragRow.classList.remove('is-dragging');
        dragRow = null;
        renumber(list);
        SM.post(list.dataset.reorderUrl, { ids: $$('.mu-row', list).map((r) => Number(r.dataset.songId)) })
            .then(() => toast('Order saved'))
            .catch(() => toast('Could not save the new order'));
    });

    function copyLink(link) {
        const done = () => toast('Link copied');
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(link).then(done).catch(() => {});
            return;
        }
        const ta = document.createElement('textarea');
        ta.value = link;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy') && done(); } catch (_) { /* ignore */ }
        ta.remove();
    }

    /* ====================================================================
       Swap the middle panel between music pages so playback continues
       ==================================================================== */

    const base = new URL(document.querySelector('meta[name="base-url"]').content + '/', location.href);
    const musicPrefix = base.pathname.replace(/\/$/, '') + '/songs';
    let autoplayAfterLoad = false;
    let navToken = 0;

    const isMusicUrl = (u) => u.origin === location.origin && (u.pathname === musicPrefix || u.pathname.startsWith(musicPrefix + '/'));

    async function navigate(href, push) {
        const target = new URL(href, location.href);
        const token = ++navToken;
        const main = $('.main-panel');
        main.classList.add('is-loading');

        try {
            const res = await fetch(target, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
            const html = await res.text();
            if (token !== navToken) return;

            const doc = new DOMParser().parseFromString(html, 'text/html');
            const fresh = doc.querySelector('.main-panel');

            // Not a music page (e.g. redirected to sign-in): do a normal load.
            if (!res.ok || !fresh || !doc.querySelector('[data-player]')) {
                location.href = res.url || target.href;
                return;
            }

            main.innerHTML = fresh.innerHTML;
            document.title = doc.title;

            $$('.content > .flash').forEach((f) => f.remove());
            doc.querySelectorAll('.content > .flash').forEach((f) => main.before(document.importNode(f, true)));

            const lists = doc.querySelector('[data-my-playlists]');
            if (lists) $('[data-my-playlists]').textContent = lists.textContent;

            $$('[data-like-song].is-on', main).forEach((b) => likedIds.add(Number(b.dataset.likeSong)));

            const finalUrl = res.url || target.href;
            if (push) history.pushState({ music: true }, '', finalUrl);
            else if (finalUrl !== location.href) history.replaceState({ music: true }, '', finalUrl);

            if (push) window.scrollTo({ top: 0 });
            afterSwap();
        } catch (_) {
            location.href = target.href;
        } finally {
            main.classList.remove('is-loading');
        }
    }

    function afterSwap() {
        highlightRows();
        initSongUpload();
        if (autoplayAfterLoad) {
            autoplayAfterLoad = false;
            $('[data-play-all]')?.click();
        }
    }

    document.addEventListener('click', (e) => {
        const a = e.target.closest('a[href]');
        if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        if (a.target || a.hasAttribute('download') || a.dataset.noPjax !== undefined) return;

        const u = new URL(a.href, location.href);
        if (!isMusicUrl(u)) return;
        if (u.pathname === location.pathname && u.search === location.search && u.hash) return;

        e.preventDefault();
        closeMenu();
        navigate(u.href, true);
    });

    document.addEventListener('submit', (e) => {
        const form = e.target;
        // Only explicit GET forms (searches); other forms handle their own submit.
        if (e.defaultPrevented || (form.getAttribute('method') || '').toLowerCase() !== 'get') return;
        const u = new URL(form.action, location.href);
        if (!isMusicUrl(u)) return;
        e.preventDefault();
        u.search = new URLSearchParams(new FormData(form)).toString();
        navigate(u.href, true);
    });

    history.replaceState({ music: true }, '', location.href);
    window.addEventListener('popstate', (e) => {
        if (e.state?.music && isMusicUrl(new URL(location.href))) navigate(location.href, false);
    });

    /* Leaving the music section for good: remember where we were. */
    window.addEventListener('pagehide', persist);

    /* ====================================================================
       Upload page (several songs at once, two in flight)
       ==================================================================== */

    let activeUploads = 0;

    window.addEventListener('beforeunload', (e) => {
        if (activeUploads) { e.preventDefault(); e.returnValue = ''; }
    });

    function readDuration(file) {
        return new Promise((resolve) => {
            const a = new Audio();
            const src = URL.createObjectURL(file);
            const done = (v) => { URL.revokeObjectURL(src); resolve(v); };
            const timer = setTimeout(() => done(null), 5000);
            a.preload = 'metadata';
            a.onloadedmetadata = () => { clearTimeout(timer); done(isFinite(a.duration) ? a.duration : null); };
            a.onerror = () => { clearTimeout(timer); done(null); };
            a.src = src;
        });
    }

    function initSongUpload() {
        const root = $('[data-song-upload]');
        if (!root || root.dataset.ready) return;
        root.dataset.ready = '1';

        const input = $('[data-song-input]', root);
        const drop = $('[data-song-drop]', root);
        const list = $('[data-song-list]', root);
        const summary = $('[data-song-summary]', root);
        const pending = [];
        let running = 0, ok = 0, failed = 0, total = 0;

        function updateSummary() {
            summary.hidden = total === 0;
            const busy = !!running || pending.length > 0;
            const done = `${ok} of ${total} ${total === 1 ? 'song' : 'songs'} uploaded` + (failed ? ` · ${failed} failed` : '');
            $('[data-song-summary-text]', summary).textContent = busy ? `Uploading… ${done}` : `Finished: ${done}. Check the details below.`;
            summary.classList.toggle('is-busy', busy);
        }

        const accept = /\.(mp3|m4a|aac|ogg|oga|opus|wav|flac|weba)$/i;

        function add(files) {
            Array.from(files).forEach((file) => {
                const item = document.createElement('div');
                item.className = 'mu-up';
                item.innerHTML = `
                    <span class="mu-cover" style="--hue:${Math.floor(Math.random() * 360)}"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 17.5V6.2l10-2v11"/><circle cx="6.6" cy="17.6" r="2.4"/><circle cx="16.6" cy="15.3" r="2.4"/></svg></span>
                    <span class="mu-up-body">
                        <span class="mu-up-name">${SM.escapeHtml(file.name)}</span>
                        <span class="mu-up-status tiny muted">Waiting &middot; ${ChunkUploader.formatBytes(file.size)}</span>
                        <span class="bar-track"><i></i></span>
                    </span>
                    <span class="mu-up-action"></span>`;
                list.appendChild(item);
                total++;

                if (!accept.test(file.name)) {
                    status(item, 'Not an audio file', true);
                    failed++;
                    return;
                }
                pending.push({ file, item });
            });
            pump();
            updateSummary();
        }

        function status(item, text, error) {
            const s = $('.mu-up-status', item);
            s.textContent = text;
            s.classList.toggle('error-text', !!error);
            if (error) item.classList.add('is-failed');
        }

        function pump() {
            while (running < 2 && pending.length) {
                const job = pending.shift();
                running++;
                activeUploads++;
                uploadOne(job).finally(() => {
                    running--;
                    activeUploads--;
                    pump();
                    updateSummary();
                    if (!running && !pending.length && !document.body.contains(root)) {
                        toast(`${ok} ${ok === 1 ? 'song' : 'songs'} uploaded`);
                    }
                });
            }
        }

        async function uploadOne({ file, item }) {
            const bar = $('.bar-track i', item);
            try {
                const duration = await readDuration(file);
                status(item, 'Uploading…');
                const job = ChunkUploader.upload(file, {
                    endpoint: root.dataset.chunkUrl,
                    onProgress: (p) => {
                        bar.style.width = p.percent.toFixed(1) + '%';
                        status(item, `${Math.round(p.percent)}% · ${ChunkUploader.formatBytes(p.sent)} of ${ChunkUploader.formatBytes(p.total)}`);
                    },
                });
                const { uploadId, totalChunks } = await job.promise;

                status(item, 'Finding song details…');
                const form = new FormData();
                form.append('upload_id', uploadId);
                form.append('total_chunks', totalChunks);
                form.append('filename', file.name);
                if (duration) form.append('duration', duration);

                const res = await SM.post(root.dataset.storeUrl, form);
                const d = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(d.message || Object.values(d.errors || {})[0]?.[0] || 'Upload failed');

                ok++;
                item.classList.add('is-done');
                bar.style.width = '100%';
                if (d.cover) $('.mu-cover', item).innerHTML = `<img src="${SM.escapeHtml(d.cover)}" alt="">`;
                showEditor(item, d);
            } catch (err) {
                failed++;
                status(item, err.message || 'Upload failed', true);
            }
        }

        /* A finished upload becomes a small form: fix the details, press Save. */
        function showEditor(item, d) {
            const esc = SM.escapeHtml;
            $('.mu-up-body', item).innerHTML = `
                <form class="mu-up-form" method="post" novalidate>
                    <label><span>Title</span><input type="text" name="title" value="${esc(d.title)}" maxlength="250" required></label>
                    <label><span>Artist</span><input type="text" name="artist" value="${esc(d.artist_raw || '')}" maxlength="250" placeholder="Unknown artist"></label>
                    <label><span>Album</span><input type="text" name="album" value="${esc(d.album || '')}" maxlength="250"></label>
                    <span class="mu-up-save">
                        <button class="btn btn-sm" type="submit">Save</button>
                        <span class="tiny muted" data-saved>${esc(d.duration)} · uploaded</span>
                    </span>
                </form>
                <span class="mu-up-found ${d.found ? 'is-found' : ''}">
                    ${d.found === 'full'
                        ? `&#10003; Details found automatically${d.genre ? ` &middot; ${esc(d.genre)}` : ''}`
                        : d.found === 'partial'
                            ? '&#10003; Title and artist found. Add the album and cover any time with <b>More</b>.'
                            : 'Only the song name was kept. Add the artist, album and cover any time with <b>More</b>.'}
                </span>`;
            $('.mu-up-action', item).innerHTML =
                `<a class="btn btn-ghost btn-sm" href="${d.edit_url}" data-music-link title="Change the cover art and more">More</a>`;

            const form = $('form', item);
            const saved = $('[data-saved]', item);
            form.addEventListener('input', () => { saved.textContent = 'Unsaved changes'; saved.classList.remove('is-ok'); });
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                if (!form.title.value.trim()) return form.title.focus();
                const body = new FormData(form);
                SM.post(d.update_url, body).then((r) => {
                    if (!r.ok) throw r;
                    saved.textContent = 'Saved ✓';
                    saved.classList.add('is-ok');
                }).catch(() => { saved.textContent = 'Could not save'; });
            });
        }

        input.addEventListener('change', () => { add(input.files); input.value = ''; });
        ['dragenter', 'dragover'].forEach((t) => drop.addEventListener(t, (e) => { e.preventDefault(); drop.classList.add('is-over'); }));
        ['dragleave', 'drop'].forEach((t) => drop.addEventListener(t, () => drop.classList.remove('is-over')));
        drop.addEventListener('drop', (e) => { e.preventDefault(); add(e.dataTransfer.files); });
    }

    /* ====================================================================
       Edit page: "Find song details". Delegated, because music pages are
       swapped in without a full page load.
       ==================================================================== */

    function lookupSongs(box) {
        const q = $('[data-lookup-q]', box).value.trim();
        const results = $('[data-lookup-results]', box);
        const esc = SM.escapeHtml;
        if (!q) return;

        results.innerHTML = '<p class="small muted">Searching&hellip;</p>';

        SM.getJson(`${box.dataset.songLookup}?q=${encodeURIComponent(q)}`)
            .then((list) => {
                results.innerHTML = list.length
                    ? list.map((m) => `
                        <div class="mu-lookup-row">
                          <span class="mu-cover">${m.thumb ? `<img src="${esc(m.thumb)}" alt="" loading="lazy">` : ''}</span>
                          <span class="mu-lookup-text">
                            <b>${esc(m.title)}</b>
                            <span class="small muted">${esc([m.artist, m.album, m.duration].filter(Boolean).join(' · '))}</span>
                          </span>
                          <button class="btn btn-ghost btn-sm" type="button" data-lookup-use="${SM.escapeHtml(m.id)}">Use these details</button>
                        </div>`).join('')
                    : '<p class="small muted">Nothing found. Try fewer words, or fill in the details yourself below.</p>';
            })
            .catch(() => { results.innerHTML = '<p class="small muted">Searching is not available right now. You can still fill in the details below.</p>'; });
    }

    document.addEventListener('click', (e) => {
        const go = e.target.closest('[data-lookup-go]');
        const use = e.target.closest('[data-lookup-use]');
        const box = e.target.closest('[data-song-lookup]');
        if (!box) return;

        if (go) lookupSongs(box);

        if (use) {
            use.disabled = true;
            use.textContent = 'Updating…';
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = box.dataset.applyUrl;
            form.hidden = true;
            form.innerHTML = `<input name="_token" value="${SM.escapeHtml(SM.csrf())}"><input name="id" value="${SM.escapeHtml(use.dataset.lookupUse)}">`;
            document.body.appendChild(form);
            form.submit();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.matches('[data-lookup-q]')) {
            e.preventDefault();
            lookupSongs(e.target.closest('[data-song-lookup]'));
        }
    });

    /* ====================================================================
       Start: restore the last session (paused) and wire the page
       ==================================================================== */

    applyVolume();
    if (current()) {
        // Carry on if music was playing when the last page closed (and no
        // other tab has taken over); otherwise restore it paused.
        const resume = sync.shouldResume(saved);
        const at = resume ? sync.resumeAt(saved, current().duration) : (saved.time || 0);
        resuming = resume;
        load(current(), resume, at);
        counted = !!saved.counted;
        els.time.textContent = fmt(at);
        els.dur.textContent = fmt(current().duration || 0);
    } else {
        render();
    }
    afterSwap();
})();
