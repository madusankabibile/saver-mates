/* ==========================================================================
   Software: upload page and show page interactions.
   Relies on window.SM from app.js and ChunkUploader from uploader.js.
   ========================================================================== */

(function () {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    /* ====================================================================
       Upload page
       ==================================================================== */

    window.initSoftwareUpload = function initSoftwareUpload(form) {
        const fileInput   = $('[data-sw-input]', form);
        const drop        = $('[data-sw-drop]', form);
        const picked      = $('[data-sw-picked]', form);
        const details     = $('[data-sw-details]', form);
        const actions     = $('[data-sw-actions]', form);
        const errorEl     = $('[data-sw-error]', form);
        const progressBox = $('[data-sw-progressbox]', form);
        const bar         = $('[data-sw-progress-bar]', form);
        const label       = $('[data-sw-progress-label]', form);
        const stats       = $('[data-sw-progress-stats]', form);
        const lookupNote  = $('[data-sw-lookup-note]', form);
        const thumbInput  = $('[data-sw-thumb-input]', form);
        const thumbPrev   = $('[data-sw-thumb-preview]', form);

        let file          = null;
        let job           = null;
        let lookupToken   = null;
        let autoFilled    = {};    // id -> value we put in (so we can detect user edits)
        let userEdited    = {};    // id -> true when user has changed the field themselves

        const VALID_EXTS = /\.(exe|msi|dmg|pkg|deb|rpm|apk|zip|gz|bz2|xz|appimage|snap|flatpak|7z|rar|run|sh|jar|bin|iso)$/i;

        const showError = (msg) => {
            errorEl.textContent = msg;
            errorEl.hidden = !msg;
        };

        /* Mark a field as user-edited when the user types in it. */
        ['sw-title', 'sw-description', 'sw-version', 'sw-os_type', 'sw-category'].forEach((id) => {
            const el = $('#' + id, form);
            if (!el) return;
            el.addEventListener('input', () => { userEdited[id] = el.value !== autoFilled[id]; });
            el.addEventListener('change', () => { userEdited[id] = el.value !== autoFilled[id]; });
        });

        /* Fill a field only if it's still empty or holds our earlier value. */
        function autoFill(id, value) {
            const el = $('#' + id, form);
            if (!el || !value) return;
            if (userEdited[id]) return;
            if (el.tagName === 'SELECT') {
                const has = $$('option', el).some((o) => o.value === value);
                if (!has) return;
            }
            el.value = autoFilled[id] = value;
        }

        /* Parse a clean title from the file name. */
        function titleFromFilename(name) {
            let s = name
                .replace(VALID_EXTS, '')
                .replace(/\.tar$/, '')
                .replace(/[\[\(][^\]\)]*[\]\)]/g, ' ')  // remove [x64] (portable) etc.
                .replace(/[\s_\-]+v?\d+[\.\d]+[a-z0-9\-]*/gi, ' ')  // strip versions
                .replace(/[\s_\-]+(setup|installer|install|portable|x64|x86|amd64|arm64|win32|win64|windows|win|macos|mac|linux|ubuntu|debian|fedora|alpha|beta|rc\d*|stable|release|final|full|lite|pro|free|trial|community|enterprise|classic)\b/gi, ' ')
                .replace(/[_\-\.]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
            // Title-case each word.
            return s.replace(/\b\w/g, (c) => c.toUpperCase()) || name;
        }

        /* Try to parse a version string from the file name. */
        function versionFromFilename(name) {
            const m = name.match(/[\s_\-]v?(\d+(?:\.\d+)+(?:[a-z0-9\-]*)?)/i);
            return m ? m[1] : null;
        }

        /* Auto-lookup with 600ms debounce. */
        let lookupTimer = null;
        function scheduleLookup(f) {
            clearTimeout(lookupTimer);
            lookupTimer = setTimeout(() => doLookup(f), 600);
        }

        async function doLookup(f) {
            const token = (lookupToken = {});
            if (lookupNote) lookupNote.textContent = 'Looking up details for this software…';

            try {
                const url = form.dataset.lookupUrl + '?name=' + encodeURIComponent(f.name);
                const d   = await SM.getJson(url);

                if (token !== lookupToken) return;

                if (!d.found) {
                    if (lookupNote) lookupNote.textContent = 'No details found online. Fill in the fields below yourself.';
                    return;
                }

                autoFill('sw-title',       d.title);
                autoFill('sw-description', d.description);
                autoFill('sw-version',     d.version);
                autoFill('sw-os_type',     d.os_type);
                autoFill('sw-category',    d.category);

                if (lookupNote) lookupNote.textContent = `We found details for "${d.title}". Check and adjust anything you like.`;

                if (d.image_url) {
                    if (token !== lookupToken) return;
                    thumbPrev.innerHTML = `<img src="${d.image_url}" alt="Thumbnail preview" crossorigin="anonymous">`;
                    form._lookupImageUrl = d.image_url;
                    form._lookupThumb = null;
                }
            } catch (_) {
                if (token === lookupToken && lookupNote) {
                    lookupNote.textContent = 'Fill in the details below. We couldn\'t look up this file online.';
                }
            }
        }

        /* Set up thumbnail preview. */
        thumbInput.addEventListener('change', () => {
            const f = thumbInput.files[0];
            if (!f) return;
            form._lookupThumb = null;
            form._lookupImageUrl = null;
            const url = URL.createObjectURL(f);
            thumbPrev.innerHTML = `<img src="${url}" alt="Thumbnail preview">`;
        });

        /* Pick a file. */
        function pickFile(f) {
            if (!f) return;

            if (!VALID_EXTS.test(f.name) && !/^application\//.test(f.type) && f.type !== '') {
                // Warn but don't block — some formats have unusual types.
                showError('');
            } else {
                showError('');
            }

            file = f;
            form._lookupThumb = null;
            form._lookupImageUrl = null;
            lookupToken = null;
            autoFilled = {};
            userEdited = {};

            $('[data-sw-name]', form).textContent = f.name;
            $('[data-sw-size]', form).textContent = ChunkUploader.formatBytes(f.size);

            drop.hidden   = true;
            picked.hidden = false;
            details.hidden = false;
            actions.hidden = false;

            // Pre-fill title and version from filename.
            const suggestedTitle   = titleFromFilename(f.name);
            const suggestedVersion = versionFromFilename(f.name);

            autoFill('sw-title',   suggestedTitle);
            autoFill('sw-version', suggestedVersion);

            // Reset lookup note.
            if (lookupNote) lookupNote.textContent = 'Fill in the details below. We\'ll try to look up info from the file name.';

            // Schedule auto-lookup.
            scheduleLookup(f);
        }

        fileInput.addEventListener('change', () => pickFile(fileInput.files[0]));

        ['dragenter', 'dragover'].forEach((t) => drop.addEventListener(t, (e) => {
            e.preventDefault();
            drop.classList.add('is-over');
        }));
        ['dragleave', 'drop'].forEach((t) => drop.addEventListener(t, () => drop.classList.remove('is-over')));
        drop.addEventListener('drop', (e) => { e.preventDefault(); pickFile(e.dataTransfer.files[0]); });

        $('[data-sw-change]', form)?.addEventListener('click', () => {
            if (job) return;
            file = null;
            fileInput.value = '';
            picked.hidden = true;
            drop.hidden   = false;
        });

        let submitted = false;

        /* Prevent navigating away mid-upload. Named so we can remove it on success. */
        function onBeforeUnload(e) {
            if (job) { e.preventDefault(); e.returnValue = ''; }
        }
        window.addEventListener('beforeunload', onBeforeUnload);

        const setBusy = (busy) => {
            $$('input, textarea, select, button', form).forEach((el) => {
                if (!el.matches('[data-sw-submit]')) el.disabled = busy;
            });
            const sub = $('[data-sw-submit]', form);
            if (sub) {
                sub.innerHTML = busy
                    ? 'Cancel upload'
                    : '<svg class="ico" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V5.5"/><path d="m7.8 9.3 4.2-4 4.2 4"/><path d="M4.5 15v3a1.5 1.5 0 0 0 1.5 1.5h12a1.5 1.5 0 0 0 1.5-1.5v-3"/></svg> Upload software';
                sub.classList.toggle('btn-coral', busy);
            }
        };

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (submitted) return;

            if (job) {
                job.cancel();
                return;
            }

            const title = $('#sw-title', form)?.value.trim();

            if (!file)  return showError('Choose a software file to upload.');
            if (!title) return showError('Give the software a title.');
            showError('');

            setBusy(true);
            progressBox.hidden = false;
            if (label) label.textContent = 'Uploading file';

            job = ChunkUploader.upload(file, {
                endpoint: form.dataset.chunkUrl,
                onProgress: (p) => {
                    if (bar)   bar.style.width = p.percent.toFixed(1) + '%';
                    if (stats) stats.textContent =
                        `${ChunkUploader.formatBytes(p.sent)} of ${ChunkUploader.formatBytes(p.total)}`
                        + (p.speed ? ` · ${ChunkUploader.formatBytes(p.speed)}/s · ${ChunkUploader.formatEta(p.eta)}` : '');
                },
            });

            try {
                const { uploadId, totalChunks } = await job.promise;

                if (label) label.textContent = 'Saving…';
                if (stats) stats.textContent  = 'Finalising the upload…';

                const data = new FormData();
                data.append('upload_id',    uploadId);
                data.append('total_chunks', totalChunks);
                data.append('file_name',    file.name);
                data.append('title',        title);
                data.append('description',  $('#sw-description',  form)?.value || '');
                data.append('version',      $('#sw-version',      form)?.value || '');
                data.append('os_type',      $('#sw-os_type',      form)?.value || '');
                data.append('category',     $('#sw-category',     form)?.value || '');

                // Thumbnail: prefer user-chosen file, then lookup image URL (server fetches it).
                const thumbFile = thumbInput.files[0];
                if (thumbFile) {
                    data.append('thumbnail', thumbFile);
                } else if (form._lookupImageUrl) {
                    data.append('image_url', form._lookupImageUrl);
                }

                // Add CSRF token.
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                if (csrfMeta) data.append('_token', csrfMeta.getAttribute('content'));

                const res  = await SM.post(form.action, data);
                const body = await res.json().catch(() => ({}));

                if (!res.ok) {
                    throw new Error(body.message || Object.values(body.errors || {})[0]?.[0] || 'The upload could not be saved.');
                }

                submitted = true;
                job = null;
                if (label) label.textContent = 'Done!';
                if (bar)   bar.style.width   = '100%';

                // Remove the beforeunload guard and reset the form so the browser's
                // native file-input dirty detection does not show a leave-page dialog.
                window.removeEventListener('beforeunload', onBeforeUnload);
                form.reset();

                // fetch() follows the server's 302 automatically; res.url is the
                // final show-page URL.
                location.href = res.url || '/software';
            } catch (err) {
                job = null;
                setBusy(false);
                progressBox.hidden = true;
                if (bar) bar.style.width = '0';
                showError(err.cancelled ? 'Upload cancelled.' : (err.message || 'An error occurred.'));
            }
        });
    };

    /* ====================================================================
       Show page
       ==================================================================== */

    window.initSoftwareShow = function initSoftwareShow(el) {
        /* Like button */
        const likeBtn = $('[data-sw-like-url]', el);
        likeBtn?.addEventListener('click', () => {
            SM.post(likeBtn.dataset.swLikeUrl)
                .then((r) => r.json())
                .then((d) => {
                    likeBtn.classList.toggle('is-on', d.liked);
                    likeBtn.setAttribute('aria-pressed', String(d.liked));
                    const count = $('[data-sw-like-count]', likeBtn);
                    if (count) count.textContent = Number(d.count).toLocaleString();
                })
                .catch(() => {});
        });

        /* Share button: copy URL to clipboard */
        const shareBtn = $('[data-sw-share]', el);
        shareBtn?.addEventListener('click', () => {
            const link = shareBtn.dataset.swShare;
            const toast = (msg) => {
                // Reuse SM.modal or just show a brief message if SM unavailable.
                if (typeof SM !== 'undefined' && SM.toast) {
                    SM.toast(msg);
                } else {
                    const t = document.createElement('div');
                    t.textContent = msg;
                    t.style.cssText = 'position:fixed;bottom:1rem;left:50%;transform:translateX(-50%);background:#222;color:#fff;padding:.5rem 1rem;border-radius:.5rem;z-index:9999;font-size:.85rem';
                    document.body.appendChild(t);
                    setTimeout(() => t.remove(), 2000);
                }
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(link).then(() => toast('Link copied')).catch(() => legacyCopy(link) && toast('Link copied'));
            } else if (legacyCopy(link)) {
                toast('Link copied');
            }
        });
    };

    function legacyCopy(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;opacity:0';
        document.body.appendChild(ta);
        ta.select();
        let ok = false;
        try { ok = document.execCommand('copy'); } catch (_) { /* ignore */ }
        ta.remove();
        return ok;
    }

    /* ====================================================================
       Auto-init on DOMContentLoaded
       ==================================================================== */

    document.addEventListener('DOMContentLoaded', () => {
        const showEl = document.querySelector('[data-sw-show]');
        if (showEl) initSoftwareShow(showEl);
    });

})();
