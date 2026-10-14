/* ==========================================================================
   Movies: watch page player extras, the upload page, and "watched" progress
   bars on movie cards. Relies on window.SM from app.js.
   ========================================================================== */

(function () {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    /* Small localStorage wrapper: storage can be unavailable (private mode). */
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
            try { localStorage.setItem(key, JSON.stringify(value)); } catch (_) { /* ignore */ }
        },
    };

    const PROGRESS_KEY = 'sm.movies.progress';

    const fmt = (s) => {
        s = Math.max(0, Math.floor(s || 0));
        const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
        return h ? `${h}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}` : `${m}:${String(sec).padStart(2, '0')}`;
    };

    const typing = (el) => el && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName));

    /* ====================================================================
       Watched progress on cards
       ==================================================================== */

    const progress = store.get(PROGRESS_KEY, {});

    $$('[data-progress-for]').forEach((bar) => {
        const entry = progress[bar.dataset.progressFor];
        const duration = entry?.d || Number(bar.dataset.duration) || 0;
        if (!entry || !duration) return;

        const pct = entry.done ? 100 : Math.min(100, (entry.t / duration) * 100);
        if (pct < 2) return;

        bar.hidden = false;
        bar.firstElementChild.style.width = pct + '%';
    });

    /* ====================================================================
       Missing thumbnails (imported movies): the uploader's browser grabs a
       frame from each, one at a time, and swaps it into the card.
       ==================================================================== */

    (async () => {
        for (const thumb of $$('[data-capture-src]')) {
            try {
                const [frame] = await captureFrames(thumb.dataset.captureSrc, [0.18, 0.3, 0.45], true);
                if (!frame) continue;

                const form = new FormData();
                form.append('thumbnail', frame.blob, 'thumbnail.jpg');
                if (frame.duration) form.append('duration', frame.duration);

                const res = await SM.post(thumb.dataset.captureUrl, form);
                const d = await res.json();
                if (!d.url) continue;

                $('.mv-fallback', thumb)?.remove();
                const img = document.createElement('img');
                img.src = d.url;
                img.alt = '';
                thumb.prepend(img);
            } catch (_) {
                // Undecodable in this browser (e.g. HEVC); the gradient stays.
            }
        }
    })();

    /* ====================================================================
       Watch page
       ==================================================================== */

    const watch = $('[data-watch]');
    if (watch) initWatch(watch);

    const commentsRoot = $('[data-movie-comments]');
    if (commentsRoot) initComments(commentsRoot);

    /* ====================================================================
       Comments: posted and removed with fetch, so the video never reloads
       ==================================================================== */

    function initComments(root) {
        const list = $('[data-comment-list]', root);
        const empty = $('[data-no-comments]', root);

        const setCount = (n) => {
            $('[data-comment-count]', root).textContent = Number(n).toLocaleString();
            $('[data-comment-word]', root).textContent = n === 1 ? 'Comment' : 'Comments';
            empty.hidden = list.children.length > 0;
        };

        const fragment = (html) => {
            const t = document.createElement('template');
            t.innerHTML = html.trim();
            return t.content.firstElementChild;
        };

        document.addEventListener('submit', async (e) => {
            const form = e.target.closest('[data-movie-comment-form]');
            if (!form) return;
            e.preventDefault();

            const textarea = $('textarea', form);
            const body = textarea.value.trim();
            if (!body || form.dataset.busy) return;

            form.dataset.busy = '1';
            const button = $('button[type="submit"]', form);
            button.disabled = true;

            try {
                const res = await SM.post(form.action, new FormData(form));
                const d = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(d.message || 'Could not post the comment.');

                const node = fragment(d.html);

                if (d.parent_id) {
                    const thread = list.querySelector(`[data-comment="${d.parent_id}"] [data-replies]`);
                    thread.hidden = false;
                    thread.appendChild(node);
                    form.hidden = true;
                } else {
                    list.prepend(node);
                }

                textarea.value = '';
                textarea.style.height = '';
                node.classList.add('is-new');
                setCount(d.count);
            } catch (err) {
                SM.modal({ title: 'Comment not posted', body: `<p>${SM.escapeHtml(err.message)}</p>`, confirmLabel: 'OK', onConfirm: () => {} });
            } finally {
                delete form.dataset.busy;
                button.disabled = false;
            }
        });

        document.addEventListener('click', (e) => {
            const del = e.target.closest('[data-delete-comment]');
            if (!del) return;

            SM.modal({
                title: 'Delete comment?',
                icon: '&#9888;&#65039;',
                body: '<p>This comment and any replies to it will be removed.</p>',
                confirmLabel: 'Delete',
                danger: true,
                onConfirm: () => {
                    const form = new FormData();
                    form.append('_method', 'DELETE');
                    SM.post(del.dataset.deleteComment, form).then((r) => r.json()).then((d) => {
                        const node = del.closest('[data-comment]');
                        const thread = node.parentElement.closest('[data-replies]');
                        node.remove();
                        if (thread && !thread.children.length) thread.hidden = true;
                        setCount(d.count);
                    }).catch(() => {});
                },
            });
        });

        // Opened from a notification: scroll the comments into view.
        if (location.hash === '#comments') root.scrollIntoView({ block: 'start' });
    }

    function initWatch(root) {
        const video = $('[data-player]', root);
        const wrap = $('[data-player-wrap]', root);
        const toastEl = $('[data-player-toast]', root);
        const movieId = root.dataset.movieId;
        let toastTimer;

        function toast(html, ms = 2600) {
            toastEl.innerHTML = html;
            toastEl.hidden = false;
            clearTimeout(toastTimer);
            if (ms) toastTimer = setTimeout(() => (toastEl.hidden = true), ms);
        }

        /* Resume where this browser left off ----------------------------- */
        video.addEventListener('loadedmetadata', () => {
            const saved = store.get(PROGRESS_KEY, {})[movieId];
            if (saved && !saved.done && saved.t > 15 && saved.t < video.duration - 30) {
                video.currentTime = saved.t;
                toast(`Resumed at ${fmt(saved.t)} <button type="button" data-restart>Start over</button>`, 7000);
            }
        }, { once: true });

        toastEl.addEventListener('click', (e) => {
            if (e.target.closest('[data-restart]')) {
                video.currentTime = 0;
                video.play();
                toastEl.hidden = true;
            }
        });

        let lastSave = 0;
        const saveProgress = (done = false) => {
            const all = store.get(PROGRESS_KEY, {});
            all[movieId] = { t: Math.floor(video.currentTime), d: Math.floor(video.duration || 0), done, at: Date.now() };

            // Keep the 200 most recent entries.
            const ids = Object.keys(all).sort((a, b) => all[b].at - all[a].at);
            ids.slice(200).forEach((id) => delete all[id]);
            store.set(PROGRESS_KEY, all);
        };

        video.addEventListener('timeupdate', () => {
            if (Date.now() - lastSave > 3000 && video.currentTime > 5) {
                lastSave = Date.now();
                saveProgress();
            }
        });
        video.addEventListener('pause', () => video.currentTime > 5 && saveProgress());

        /* Count a view after ten seconds of actual playback ---------------- */
        let watched = 0, lastTick = null, viewSent = false;
        video.addEventListener('timeupdate', () => {
            if (video.paused || viewSent) return;
            const now = video.currentTime;
            if (lastTick !== null && now > lastTick && now - lastTick < 2) watched += now - lastTick;
            lastTick = now;

            if (watched >= 10) {
                viewSent = true;
                SM.post(root.dataset.viewUrl).then((r) => r.json()).then((d) => {
                    const el = $('[data-view-count]', root);
                    if (el && d.views != null) el.textContent = Number(d.views).toLocaleString() + (d.views === 1 ? ' view' : ' views');
                }).catch(() => {});
            }
        });
        video.addEventListener('seeking', () => (lastTick = null));

        /* Playback failure (e.g. HEVC in a browser without the codec) ------ */
        video.addEventListener('error', () => {
            toast('This browser cannot play this video file. Use <b>Download</b> to watch it in a media player.', 0);
        }, true);
        $('source', video)?.addEventListener('error', () => {
            toast('This browser cannot play this video file. Use <b>Download</b> to watch it in a media player.', 0);
        });

        /* Subtitles: choice, size, sync ---------------------------------- */
        const tracks = Array.from(video.textTracks);
        const trackEls = $$('track', video);
        const subSelect = $('[data-sub-select]', root);
        const offsetEl = $('[data-sub-offset]', root);
        let offset = 0;
        const applied = tracks.map(() => 0);

        function shiftTrack(i) {
            const track = tracks[i];
            if (!track?.cues || !track.cues.length) return;
            const delta = offset - applied[i];
            if (!delta) return;
            Array.from(track.cues).forEach((cue) => {
                cue.startTime = Math.max(0, cue.startTime + delta);
                cue.endTime = Math.max(0, cue.endTime + delta);
            });
            applied[i] = offset;
        }

        trackEls.forEach((el, i) => el.addEventListener('load', () => shiftTrack(i)));

        function chooseSubtitle(value) {
            tracks.forEach((t, i) => (t.mode = String(i) === value ? 'showing' : 'disabled'));
            subSelect.value = value;
            if (value !== '') {
                shiftTrack(Number(value));
                store.set('sm.movies.subLang', tracks[Number(value)].language + '|' + tracks[Number(value)].label);
            } else {
                store.set('sm.movies.subLang', 'off');
            }
        }

        if (tracks.length) {
            // Prefer the viewer's last choice: same label, then same language.
            const pref = store.get('sm.movies.subLang', null);
            let pick = '';
            if (pref && pref !== 'off') {
                const [lang, label] = pref.split('|');
                let idx = tracks.findIndex((t) => t.label === label);
                if (idx < 0) idx = tracks.findIndex((t) => t.language === lang);
                if (idx >= 0) pick = String(idx);
            }
            chooseSubtitle(pick);
        }

        subSelect?.addEventListener('change', () => chooseSubtitle(subSelect.value));

        const shiftBy = (d) => {
            offset = Math.round((offset + d) * 10) / 10;
            offsetEl.textContent = (offset > 0 ? '+' : '') + offset.toFixed(1) + 's';
            tracks.forEach((t, i) => t.mode !== 'disabled' && shiftTrack(i));
            toast(`Subtitle delay ${offsetEl.textContent}`, 1200);
        };
        $$('[data-sub-shift]', root).forEach((b) => b.addEventListener('click', () => shiftBy(Number(b.dataset.subShift))));

        const setSubSize = (size) => {
            wrap.classList.remove('sub-s', 'sub-m', 'sub-l');
            wrap.classList.add('sub-' + size);
            $$('[data-sub-size]', root).forEach((b) => b.classList.toggle('is-active', b.dataset.subSize === size));
            store.set('sm.movies.subSize', size);
        };
        setSubSize(store.get('sm.movies.subSize', 'm'));
        $$('[data-sub-size]', root).forEach((b) => b.addEventListener('click', () => setSubSize(b.dataset.subSize)));

        /* Speed ----------------------------------------------------------- */
        const speed = $('[data-speed]', root);
        const rates = [0.5, 0.75, 1, 1.25, 1.5, 2];
        speed.addEventListener('change', () => (video.playbackRate = Number(speed.value)));
        const stepSpeed = (dir) => {
            const i = Math.max(0, Math.min(rates.length - 1, rates.indexOf(video.playbackRate) + dir));
            video.playbackRate = rates[i];
            speed.value = String(rates[i]);
            toast(`Speed ${rates[i]}x`, 1000);
        };

        /* Theater mode: the left panel steps aside so the player grows ------ */
        const setTheater = (on) => {
            document.body.classList.toggle('is-theater', on);
            $('[data-theater]', root).classList.toggle('is-active', on);
            store.set('sm.movies.theater', on);
        };
        setTheater(store.get('sm.movies.theater', false));
        $('[data-theater]', root).addEventListener('click', () => setTheater(!document.body.classList.contains('is-theater')));

        /* Autoplay the next movie ------------------------------------------ */
        const autoplay = $('[data-autoplay]', root);
        autoplay.checked = store.get('sm.movies.autoplay', true);
        autoplay.addEventListener('change', () => store.set('sm.movies.autoplay', autoplay.checked));

        const upnext = $('[data-upnext]', root);
        let countdown;

        video.addEventListener('ended', () => {
            saveProgress(true);
            if (!autoplay.checked || !root.dataset.nextUrl) return;

            let left = 8;
            $('[data-upnext-title]', upnext).textContent = root.dataset.nextTitle;
            $('[data-upnext-count]', upnext).textContent = left;
            upnext.hidden = false;

            countdown = setInterval(() => {
                left--;
                $('[data-upnext-count]', upnext).textContent = left;
                if (left <= 0) {
                    clearInterval(countdown);
                    location.href = root.dataset.nextUrl;
                }
            }, 1000);
        });

        $('[data-upnext-cancel]', upnext).addEventListener('click', () => {
            clearInterval(countdown);
            upnext.hidden = true;
        });
        $('[data-upnext-go]', upnext).addEventListener('click', () => (location.href = root.dataset.nextUrl));

        /* Keyboard shortcuts (YouTube style) ------------------------------- */
        document.addEventListener('keydown', (e) => {
            if (typing(document.activeElement) || e.ctrlKey || e.metaKey || e.altKey) return;

            const k = e.key;
            const seek = (d) => {
                video.currentTime = Math.max(0, Math.min(video.duration || 0, video.currentTime + d));
                toast((d > 0 ? '+' : '') + d + 's', 700);
            };

            switch (true) {
                case k === ' ' || k === 'k' || k === 'K':
                    // Space on a focused button should still click it.
                    if (k === ' ' && document.activeElement?.tagName === 'BUTTON') return;
                    video.paused ? video.play() : video.pause();
                    break;
                case k === 'j' || k === 'J': seek(-10); break;
                case k === 'l' || k === 'L': seek(10); break;
                case k === 'ArrowLeft': seek(-5); break;
                case k === 'ArrowRight': seek(5); break;
                case k === 'ArrowUp':
                    video.volume = Math.min(1, video.volume + 0.1);
                    toast(`Volume ${Math.round(video.volume * 100)}%`, 800);
                    break;
                case k === 'ArrowDown':
                    video.volume = Math.max(0, video.volume - 0.1);
                    toast(`Volume ${Math.round(video.volume * 100)}%`, 800);
                    break;
                case k === 'm' || k === 'M':
                    video.muted = !video.muted;
                    toast(video.muted ? 'Muted' : 'Sound on', 800);
                    break;
                case k === 'f' || k === 'F':
                    document.fullscreenElement ? document.exitFullscreen() : wrap.requestFullscreen?.();
                    break;
                case k === 't' || k === 'T': setTheater(!document.body.classList.contains('is-theater')); break;
                case k === 'c' || k === 'C':
                    if (!tracks.length) return;
                    chooseSubtitle(subSelect.value === '' ? '0' : '');
                    toast(subSelect.value === '' ? 'Subtitles off' : 'Subtitles: ' + tracks[Number(subSelect.value)].label, 1000);
                    break;
                case k === 'z' || k === 'Z': if (tracks.length) shiftBy(-0.5); break;
                case k === 'x' || k === 'X': if (tracks.length) shiftBy(0.5); break;
                case k === '<' || k === ',': stepSpeed(-1); break;
                case k === '>' || k === '.': stepSpeed(1); break;
                case /^[0-9]$/.test(k):
                    if (video.duration) video.currentTime = video.duration * (Number(k) / 10);
                    break;
                default:
                    return;
            }
            e.preventDefault();
        });

        // Fullscreen on the wrapper keeps our subtitle size classes in effect.
        video.addEventListener('dblclick', (e) => {
            e.preventDefault();
            document.fullscreenElement ? document.exitFullscreen() : wrap.requestFullscreen?.();
        });

        /* Like ------------------------------------------------------------- */
        const like = $('[data-like-url]', root);
        like?.addEventListener('click', () => {
            SM.post(like.dataset.likeUrl).then((r) => r.json()).then((d) => {
                like.classList.toggle('is-on', d.liked);
                like.setAttribute('aria-pressed', String(d.liked));
                $('[data-like-count]', like).textContent = Number(d.count).toLocaleString();
            }).catch(() => {});
        });

        /* Share: copy the link (clipboard API needs HTTPS, so fall back) ---- */
        $('[data-copy-link]', root)?.addEventListener('click', (e) => {
            const link = e.currentTarget.dataset.copyLink;
            const done = () => toast('Link copied', 1500);
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(link).then(done).catch(() => legacyCopy(link) && done());
            } else if (legacyCopy(link)) {
                done();
            }
        });

        /* Description: clamp long text -------------------------------------- */
        const descText = $('[data-desc-text]', root);
        const more = $('[data-desc-more]', root);
        if (descText && more && descText.scrollHeight > descText.clientHeight + 4) {
            more.hidden = false;
            more.addEventListener('click', () => {
                const open = descText.classList.toggle('is-open');
                more.textContent = open ? 'Show less' : 'Show more';
            });
        }

        /* Imported movie without a thumbnail: the uploader's browser grabs one */
        if (root.dataset.thumbUrl) {
            captureFrames(video.currentSrc || $('source', video).src, [0.18, 0.3, 0.45], true)
                .then(([frame]) => {
                    if (!frame) return;
                    const form = new FormData();
                    form.append('thumbnail', frame.blob, 'thumbnail.jpg');
                    if (frame.duration) form.append('duration', frame.duration);
                    return SM.post(root.dataset.thumbUrl, form).then((r) => r.json()).then((d) => {
                        if (d.url && video.paused && video.currentTime === 0) video.poster = d.url;
                    });
                })
                .catch(() => {});
        }
    }

    function legacyCopy(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        let ok = false;
        try { ok = document.execCommand('copy'); } catch (_) { /* ignore */ }
        ta.remove();
        return ok;
    }

    /* ====================================================================
       Frame capture (thumbnails without ffmpeg)
       ==================================================================== */

    /**
     * Seek a hidden video to each fraction of its length and grab a JPEG.
     * With single = true it stops at the first frame that is not near-black
     * (fades, black intros) and resolves to that one frame.
     * Resolves to [{ blob, url, time, duration, dark }].
     */
    function captureFrames(src, fractions, single = false) {
        return new Promise((resolve, reject) => {
            const v = document.createElement('video');
            v.muted = true;
            v.preload = 'auto';
            v.playsInline = true;
            v.src = src;

            const frames = [];
            let i = 0;
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d', { willReadFrequently: true });

            const finish = () => {
                v.removeAttribute('src');
                v.load();
                resolve(single ? [frames.find((f) => !f.dark) || frames[0]] : frames);
            };

            const timeout = setTimeout(() => (frames.length ? finish() : reject(new Error('timeout'))), 30000);

            v.addEventListener('error', () => {
                clearTimeout(timeout);
                reject(new Error('decode'));
            });

            v.addEventListener('loadedmetadata', () => {
                if (!v.videoWidth) {
                    clearTimeout(timeout);
                    return reject(new Error('no video track'));
                }
                const w = Math.min(1280, v.videoWidth);
                canvas.width = w;
                canvas.height = Math.round(w * v.videoHeight / v.videoWidth);
                v.currentTime = v.duration * fractions[0];
            });

            v.addEventListener('seeked', () => {
                ctx.drawImage(v, 0, 0, canvas.width, canvas.height);

                // Average brightness of a sparse sample.
                const data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
                let sum = 0, n = 0;
                for (let p = 0; p < data.length; p += 4 * 97) { sum += data[p] * 0.3 + data[p + 1] * 0.59 + data[p + 2] * 0.11; n++; }
                const dark = sum / n < 28;

                canvas.toBlob((blob) => {
                    frames.push({ blob, url: URL.createObjectURL(blob), time: v.currentTime, duration: v.duration, dark });
                    i++;
                    if (i >= fractions.length || (single && !dark)) {
                        clearTimeout(timeout);
                        finish();
                    } else {
                        v.currentTime = v.duration * fractions[i];
                    }
                }, 'image/jpeg', 0.86);
            });
        });
    }

    /* ====================================================================
       Upload page
       ==================================================================== */

    const uploadForm = $('[data-movie-upload]');
    if (uploadForm) initUpload(uploadForm);

    function guessLang(name) {
        const n = name.toLowerCase().replace(/\.(srt|vtt)$/, '');
        const tests = [
            ['si', /(^|[^a-z])(si|sin|sinhala|sinhalese)([^a-z]|$)/],
            ['ta', /(^|[^a-z])(ta|tam|tamil)([^a-z]|$)/],
            ['hi', /(^|[^a-z])(hi|hin|hindi)([^a-z]|$)/],
            ['es', /(^|[^a-z])(es|spa|spanish)([^a-z]|$)/],
            ['fr', /(^|[^a-z])(fr|fre|french)([^a-z]|$)/],
            ['ko', /(^|[^a-z])(ko|kor|korean)([^a-z]|$)/],
            ['en', /(^|[^a-z])(en|eng|english)([^a-z]|$)/],
        ];
        return (tests.find(([, re]) => re.test(n)) || ['en'])[0];
    }

    /** "Luck.2022.720p.WEBRip.x264" becomes "Luck (2022)". */
    function titleFromFile(name) {
        let s = name.replace(/\.[^.]+$/, '').replace(/\[[^\]]*\]/g, ' ').replace(/www\.\S+/gi, ' ');
        s = s.replace(/([a-z])((19|20)\d{2})/gi, '$1 $2').replace(/[._]+/g, ' ').replace(/\s+/g, ' ').trim();
        const m = s.match(/^(.*?)[\s(\-]*((19|20)\d{2})\b/);
        if (m && m[1].trim()) return `${m[1].trim().replace(/[\s(\-]+$/, '')} (${m[2]})`;
        return s.replace(/\b(720p|1080p|2160p|4k|webrip|bluray|web-dl|hdrip|x264|x265|hevc)\b.*$/i, '').trim() || s;
    }

    function initUpload(form) {
        const input = $('[data-video-input]', form);
        const drop = $('[data-video-drop]', form);
        const picked = $('[data-video-picked]', form);
        const details = $('[data-details]', form);
        const actions = $('[data-actions]', form);
        const warning = $('[data-format-warning]', form);
        const framesEl = $('[data-frames]', form);
        const preview = $('[data-thumb-preview]', form);
        const thumbInput = $('[data-thumb-input]', form);
        const subInput = $('[data-sub-input]', form);
        const subList = $('[data-sub-list]', form);
        const errorEl = $('[data-upload-error]', form);
        const progressBox = $('[data-progressbox]', form);
        const langOptions = $('[data-lang-options]').innerHTML;

        let file = null;
        let duration = null;
        let thumbBlob = null;       // chosen frame
        let customThumb = null;     // or the uploader's own image
        let subs = [];              // [{ file, lang }]
        let job = null;

        const showError = (msg) => {
            errorEl.textContent = msg;
            errorEl.hidden = !msg;
        };

        function setPreview(url) {
            preview.innerHTML = url ? `<img src="${url}" alt="Thumbnail preview">` : '<span class="small muted">No thumbnail chosen</span>';
        }

        function pickFile(f) {
            if (!f) return;
            const ext = (f.name.split('.').pop() || '').toLowerCase();
            if (!['mp4', 'm4v', 'webm', 'mkv', 'mov', 'ogv'].includes(ext)) {
                showError('Choose a video file: MP4, WebM, MKV, MOV or OGV.');
                return;
            }

            showError('');
            file = f;
            duration = null;
            thumbBlob = null;

            $('[data-video-name]', form).textContent = f.name;
            $('[data-video-size]', form).textContent = ChunkUploader.formatBytes(f.size);
            drop.hidden = true;
            picked.hidden = false;
            details.hidden = false;
            actions.hidden = false;
            warning.hidden = ['mp4', 'm4v', 'webm'].includes(ext);

            const title = $('#mv-title', form);
            if (!title.value.trim()) title.value = titleFromFile(f.name);

            framesEl.innerHTML = '';
            if (!customThumb) preview.innerHTML = '<span class="small muted">Picking frames from the video&hellip;</span>';
            $('[data-frame-note]', form).textContent = 'Pick one of the frames from your video, or upload your own image.';

            const src = URL.createObjectURL(f);
            captureFrames(src, [0.08, 0.2, 0.33, 0.47, 0.62])
                .then((frames) => {
                    duration = frames[0]?.duration || null;
                    frames.forEach((frame, i) => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'mv-frame';
                        b.innerHTML = `<img src="${frame.url}" alt="Frame at ${fmt(frame.time)}"><span>${fmt(frame.time)}</span>`;
                        b.addEventListener('click', () => {
                            $$('.mv-frame', framesEl).forEach((x) => x.classList.remove('is-active'));
                            b.classList.add('is-active');
                            thumbBlob = frame.blob;
                            customThumb = null;
                            thumbInput.value = '';
                            setPreview(frame.url);
                        });
                        framesEl.appendChild(b);
                        if (i === frames.findIndex((x) => !x.dark) || (i === 0 && frames.every((x) => x.dark))) b.click();
                    });
                })
                .catch(() => {
                    if (!customThumb) setPreview(null);
                    $('[data-frame-note]', form).textContent =
                        'Your browser could not read frames from this file (common for MKV / HEVC). Upload an image to use as the thumbnail.';
                })
                .finally(() => setTimeout(() => URL.revokeObjectURL(src), 60000));
        }

        input.addEventListener('change', () => pickFile(input.files[0]));
        ['dragenter', 'dragover'].forEach((t) => drop.addEventListener(t, (e) => { e.preventDefault(); drop.classList.add('is-over'); }));
        ['dragleave', 'drop'].forEach((t) => drop.addEventListener(t, () => drop.classList.remove('is-over')));
        drop.addEventListener('drop', (e) => { e.preventDefault(); pickFile(e.dataTransfer.files[0]); });

        $('[data-video-change]', form).addEventListener('click', () => {
            if (job) return;
            file = null;
            input.value = '';
            picked.hidden = true;
            drop.hidden = false;
            warning.hidden = true;
        });

        thumbInput.addEventListener('change', () => {
            const f = thumbInput.files[0];
            if (!f) return;
            customThumb = f;
            thumbBlob = null;
            $$('.mv-frame', framesEl).forEach((x) => x.classList.remove('is-active'));
            setPreview(URL.createObjectURL(f));
        });

        /* Subtitle rows ------------------------------------------------------ */
        function renderSubs() {
            subList.innerHTML = '';
            subs.forEach((s, i) => {
                const row = document.createElement('div');
                row.className = 'mv-sub-row';
                row.innerHTML = `<span class="mv-sub-name"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3.5" y="5.5" width="17" height="13" rx="2.2"/></svg> ${SM.escapeHtml(s.file.name)}</span>
                    <select aria-label="Language">${langOptions}</select>
                    <button class="mini-btn" type="button" aria-label="Remove">&#10005;</button>`;
                const select = $('select', row);
                select.value = s.lang;
                select.addEventListener('change', () => (s.lang = select.value));
                $('button', row).addEventListener('click', () => { subs.splice(i, 1); renderSubs(); });
                subList.appendChild(row);
            });
        }

        subInput.addEventListener('change', () => {
            Array.from(subInput.files).forEach((f) => {
                if (subs.length < 6 && /\.(srt|vtt)$/i.test(f.name)) subs.push({ file: f, lang: guessLang(f.name) });
            });
            subInput.value = '';
            renderSubs();
        });

        /* Submit: chunks first, then the details --------------------------- */
        const bar = $('[data-progress-bar]', form);
        const label = $('[data-progress-label]', form);
        const stats = $('[data-progress-stats]', form);

        window.addEventListener('beforeunload', (e) => {
            if (job) { e.preventDefault(); e.returnValue = ''; }
        });

        const setBusy = (busy) => {
            $$('input, textarea, select, button', form).forEach((el) => {
                if (!el.matches('[data-submit]')) el.disabled = busy;
            });
            $('[data-submit]', form).innerHTML = busy ? 'Cancel upload' : 'Upload movie';
            $('[data-submit]', form).classList.toggle('btn-coral', busy);
        };

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (job) {
                job.cancel();
                return;
            }

            const title = $('#mv-title', form).value.trim();
            if (!file) return showError('Choose a video to upload.');
            if (!title) return showError('Give the movie a title.');
            showError('');

            setBusy(true);
            progressBox.hidden = false;
            label.textContent = 'Uploading video';

            job = ChunkUploader.upload(file, {
                endpoint: form.dataset.chunkUrl,
                onProgress: (p) => {
                    bar.style.width = p.percent.toFixed(1) + '%';
                    stats.textContent = `${ChunkUploader.formatBytes(p.sent)} of ${ChunkUploader.formatBytes(p.total)}`
                        + (p.speed ? ` · ${ChunkUploader.formatBytes(p.speed)}/s · ${ChunkUploader.formatEta(p.eta)}` : '');
                },
            });

            try {
                const { uploadId, totalChunks } = await job.promise;

                label.textContent = 'Finishing up';
                stats.textContent = 'Putting the pieces together…';

                const data = new FormData();
                data.append('upload_id', uploadId);
                data.append('total_chunks', totalChunks);
                data.append('filename', file.name);
                data.append('title', title);
                data.append('description', $('#mv-description', form).value);
                data.append('category', $('#mv-category', form).value);
                if (duration) data.append('duration', duration);
                if (customThumb) data.append('thumbnail', customThumb);
                else if (thumbBlob) data.append('thumbnail', thumbBlob, 'thumbnail.jpg');
                subs.forEach((s, i) => {
                    data.append(`subtitles[${i}]`, s.file);
                    data.append(`subtitle_langs[${i}]`, s.lang);
                });

                const res = await SM.post(form.action, data);
                const body = await res.json().catch(() => ({}));

                if (!res.ok) {
                    throw new Error(body.message || Object.values(body.errors || {})[0]?.[0] || 'The upload could not be saved.');
                }

                job = null;
                label.textContent = 'Done';
                bar.style.width = '100%';
                location.href = body.url;
            } catch (err) {
                job = null;
                setBusy(false);
                progressBox.hidden = true;
                bar.style.width = '0';
                showError(err.cancelled ? 'Upload cancelled.' : err.message);
            }
        });
    }
})();
