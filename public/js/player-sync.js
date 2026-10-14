/* ==========================================================================
   Music continuity across pages and tabs.

   The site is made of ordinary pages, so leaving one stops its <audio>.
   Whichever player is on the next page (the full player on music pages,
   the mini player everywhere else) picks the song up where it left off,
   using the state music.js keeps in localStorage.

   Only one browser tab plays at a time: the playing tab "owns" playback and
   refreshes a heartbeat; starting music elsewhere takes ownership and the
   old tab pauses. Navigating keeps the same tab id (sessionStorage), so a
   tab resumes its own music but never starts a second copy of another's.
   ========================================================================== */

(function () {
    'use strict';

    const STATE_KEY = 'sm.music.state';
    const OWNER_KEY = 'sm.music.owner';
    const HEARTBEAT_MS = 1500;
    const STALE_MS = 5000;

    const read = (key, fallback) => {
        try {
            const v = localStorage.getItem(key);
            return v === null ? fallback : JSON.parse(v);
        } catch (_) {
            return fallback;
        }
    };

    const write = (key, value) => {
        try { localStorage.setItem(key, JSON.stringify(value)); } catch (_) { /* full or blocked */ }
    };

    let tabId = '';
    try {
        tabId = sessionStorage.getItem('sm.tab') || '';
        if (!tabId) {
            tabId = Math.random().toString(36).slice(2) + Date.now().toString(36);
            sessionStorage.setItem('sm.tab', tabId);
        }
    } catch (_) {
        tabId = Math.random().toString(36).slice(2);
    }

    let beat = null;
    let leaving = false;

    const owner = () => read(OWNER_KEY, null);

    const sync = {
        STATE_KEY,
        tabId,

        readState: () => read(STATE_KEY, {}),
        writeState: (state) => write(STATE_KEY, state),

        /** Another tab is playing right now. */
        playingElsewhere() {
            const o = owner();
            return !!o && o.tab !== tabId && o.playing && Date.now() - o.at < STALE_MS;
        },

        /**
         * Should this page carry on playing? True when music was playing as
         * the last page closed and no other tab has taken over since.
         */
        shouldResume(state) {
            return !!state.playing && !sync.playingElsewhere();
        },

        /** Where the song has got to: the saved position plus the time the page change took. */
        resumeAt(state, duration) {
            const gap = state.savedAt ? Math.max(0, (Date.now() - state.savedAt) / 1000) : 0;
            const at = (state.time || 0) + Math.min(gap, 15);
            return duration && at >= duration - 1 ? 0 : at;
        },

        /** This tab started playing: claim it and keep the heartbeat going. */
        claim() {
            write(OWNER_KEY, { tab: tabId, at: Date.now(), playing: true });
            clearInterval(beat);
            beat = setInterval(() => write(OWNER_KEY, { tab: tabId, at: Date.now(), playing: true }), HEARTBEAT_MS);
        },

        /** This tab paused. Ignored while the page unloads (that is not a real pause). */
        release() {
            if (leaving) return;
            clearInterval(beat);
            const o = owner();
            if (!o || o.tab === tabId) write(OWNER_KEY, { tab: tabId, at: Date.now(), playing: false });
        },

        /** Called when another tab starts playing, so this one can pause. */
        onTakenOver(callback) {
            window.addEventListener('storage', (e) => {
                if (e.key !== OWNER_KEY || !e.newValue) return;
                try {
                    const o = JSON.parse(e.newValue);
                    if (o.tab !== tabId && o.playing) callback();
                } catch (_) { /* ignore */ }
            });
        },

        /** True once the page has started unloading. */
        get leaving() { return leaving; },
    };

    // pagehide, not beforeunload: the upload page can cancel an unload.
    window.addEventListener('pagehide', () => { leaving = true; });
    // Back/forward cache restores the page: it is live again.
    window.addEventListener('pageshow', (e) => { if (e.persisted) leaving = false; });

    window.SMPlayerSync = sync;
})();
