/* ==========================================================================
   SaverMate front end. Vanilla JS, no build step.
   Live behaviour (presence, chat, alerts) runs on short polls so the app
   works on a plain XAMPP intranet with no broadcast server.
   ========================================================================== */

(function () {
    'use strict';

    const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content || '';
    const csrf = () => meta('csrf-token');
    const signedIn = meta('user-id') !== '';

    /**
     * The app may be served from a subdirectory (http://host/savermates/public),
     * so every request has to be built off the application base rather than the
     * domain root. Without this, nothing that polls or posts via fetch works.
     */
    const base = meta('base-url').replace(/\/+$/, '');
    const url = (path) => base + (path.startsWith('/') ? path : '/' + path);

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    function post(endpoint, body) {
        const opts = {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        };

        if (body instanceof FormData) {
            opts.body = body;
        } else if (body) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }

        return fetch(endpoint, opts);
    }

    function getJson(endpoint) {
        return fetch(endpoint, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            credentials: 'same-origin',
        }).then((r) => (r.ok ? r.json() : Promise.reject(r)));
    }

    const escapeHtml = (s) =>
        String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /* Shared helpers for the page scripts (movies.js, music.js, uploader.js). */
    window.SM = { url, post, getJson, escapeHtml, modal, csrf, signedIn };

    /* ====================================================================
       Dropdown panels
       ==================================================================== */

    function closeDropdowns(except) {
        $$('[data-dropdown]').forEach((wrap) => {
            if (wrap !== except) {
                const panel = $('[data-dropdown-panel]', wrap);
                if (panel) panel.hidden = true;
                $('[data-dropdown-toggle]', wrap)?.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.addEventListener('click', (e) => {
        const toggle = e.target.closest('[data-dropdown-toggle]');

        if (toggle) {
            e.preventDefault();
            const wrap = toggle.closest('[data-dropdown]');
            const panel = $('[data-dropdown-panel]', wrap);
            const opening = panel.hidden;

            closeDropdowns(wrap);
            panel.hidden = !opening;
            toggle.setAttribute('aria-expanded', String(opening));

            if (opening && wrap.dataset.dropdown === 'alerts') {
                markAlertsRead();
            }
            return;
        }

        if (!e.target.closest('[data-dropdown-panel]')) {
            closeDropdowns();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeDropdowns();
            closeLightbox();
            $$('[data-modal]').forEach((m) => m.remove());
        }
    });

    /* ====================================================================
       Flash messages
       ==================================================================== */

    $$('.flash').forEach((flash) => {
        $('.flash-close', flash)?.addEventListener('click', () => flash.remove());
        if (!flash.classList.contains('is-error')) {
            setTimeout(() => {
                flash.style.transition = 'opacity .4s ease';
                flash.style.opacity = '0';
                setTimeout(() => flash.remove(), 420);
            }, 6000);
        }
    });

    /* ====================================================================
       Presence heartbeat + badge polling
       ==================================================================== */

    if (signedIn) {
        const beat = () => {
            if (document.visibilityState === 'visible') {
                post(url('/heartbeat')).catch(() => {});
            }
        };

        setInterval(beat, 45000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') beat();
        });

        /* Online colleagues panel ------------------------------------- */
        const onlinePanel = $('[data-online-list]');
        const onlineTally = $('[data-online-tally]');

        function refreshPresence() {
            if (document.visibilityState !== 'visible') return;

            getJson(url('/members/presence'))
                .then((data) => {
                    if (onlineTally) onlineTally.textContent = data.count;
                    if (!onlinePanel) return;

                    if (!data.members.length) {
                        onlinePanel.innerHTML =
                            '<p class="small muted" style="padding:.4rem .35rem">No colleagues online right now.</p>';
                        return;
                    }

                    onlinePanel.innerHTML = data.members
                        .map(
                            (m) => `
                        <a class="person-row" href="${m.chat_url}">
                          <span class="avatar a-34 is-online">
                            <img src="${escapeHtml(m.avatar)}" alt="">
                            <i class="presence"></i>
                          </span>
                          <span class="pr-body">
                            <span class="pr-name">${escapeHtml(m.name)}</span>
                            <span class="pr-meta">${escapeHtml(m.designation || '@' + m.username)}</span>
                          </span>
                          <span class="pr-action" aria-hidden="true">&#128172;</span>
                        </a>`
                        )
                        .join('');
                })
                .catch(() => {});
        }

        if (onlinePanel || onlineTally) {
            refreshPresence();
            setInterval(refreshPresence, 20000);
        }

        /* Alert bell ---------------------------------------------------- */
        const bellCount = $('[data-alert-count]');
        const bellList = $('[data-alert-list]');

        function refreshAlerts() {
            if (document.visibilityState !== 'visible') return;

            getJson(url('/alerts/feed'))
                .then((data) => {
                    if (bellCount) {
                        bellCount.textContent = data.count > 99 ? '99+' : data.count;
                        bellCount.hidden = data.count === 0;
                    }

                    $$('[data-rail-badge="alerts"]').forEach((el) => {
                        el.textContent = data.count;
                        el.hidden = data.count === 0;
                    });

                    if (!bellList) return;

                    if (!data.items.length) {
                        bellList.innerHTML =
                            '<p class="small muted" style="padding:1.2rem;text-align:center">Nothing yet. Reactions, comments and messages show up here.</p>';
                        return;
                    }

                    bellList.innerHTML = data.items
                        .map(
                            (a) => `
                        <a class="alert-item ${a.unread ? 'is-unread' : ''}" href="${a.url}">
                          <span class="avatar a-34"><img src="${escapeHtml(a.avatar)}" alt=""></span>
                          <span class="ai-body">
                            <span class="ai-text"><b>${escapeHtml(a.actor)}</b> ${escapeHtml(a.sentence)}</span>
                            ${a.preview ? `<span class="ai-preview">${escapeHtml(a.preview)}</span>` : ''}
                            <span class="ai-time">${escapeHtml(a.ago)}</span>
                          </span>
                        </a>`
                        )
                        .join('');
                })
                .catch(() => {});
        }

        function markAlertsRead() {
            post(url('/alerts/read'))
                .then(() => {
                    if (bellCount) bellCount.hidden = true;
                    $$('[data-rail-badge="alerts"]').forEach((el) => (el.hidden = true));
                    setTimeout(refreshAlerts, 400);
                })
                .catch(() => {});
        }

        window.markAlertsRead = markAlertsRead;

        if (bellCount) {
            refreshAlerts();
            setInterval(refreshAlerts, 20000);
        }

        /* Unread chat badge -------------------------------------------- */
        const chatBadges = $$('[data-chat-count]');

        function refreshChatBadge() {
            if (document.visibilityState !== 'visible') return;

            getJson(url('/chat/unread'))
                .then((data) => {
                    chatBadges.forEach((el) => {
                        el.textContent = data.count > 99 ? '99+' : data.count;
                        el.hidden = data.count === 0;
                    });
                })
                .catch(() => {});
        }

        if (chatBadges.length) {
            refreshChatBadge();
            setInterval(refreshChatBadge, 20000);
        }
    }

    /* ====================================================================
       Reactions
       ==================================================================== */

    let reactHoverTimer = null;

    /* Opening: un-hiding restarts the CSS entry animations. Closing plays the
       exit animation first, then hides. */
    function openReactPop(pop) {
        pop.classList.remove('is-closing');
        $$('.is-picked', pop).forEach((b) => b.classList.remove('is-picked'));
        pop.hidden = false;
    }

    function closeReactPop(pop) {
        if (!pop || pop.hidden || pop.classList.contains('is-closing')) return;
        pop.classList.add('is-closing');
        const done = () => {
            if (!pop.classList.contains('is-closing')) return; // reopened meanwhile
            pop.hidden = true;
            pop.classList.remove('is-closing');
        };
        pop.addEventListener('animationend', (e) => e.target === pop && done(), { once: true });
        setTimeout(done, 400); // in case animations are disabled
    }

    document.addEventListener('mouseover', (e) => {
        const wrap = e.target.closest('.react-wrap');
        if (wrap) {
            clearTimeout(reactHoverTimer);
            const pop = $('[data-react-pop]', wrap);
            if (pop && (pop.hidden || pop.classList.contains('is-closing'))) {
                $$('[data-react-pop]').forEach((p) => { if (p !== pop) closeReactPop(p); });
                openReactPop(pop);
            }
        }
    });

    document.addEventListener('mouseout', (e) => {
        const wrap = e.target.closest('.react-wrap');
        if (wrap) {
            const next = e.relatedTarget ? e.relatedTarget.closest('.react-wrap') : null;
            if (next !== wrap) {
                clearTimeout(reactHoverTimer);
                reactHoverTimer = setTimeout(() => {
                    closeReactPop($('[data-react-pop]', wrap));
                }, 220);
            }
        }
    });

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-react-open]');

        if (trigger) {
            e.preventDefault();
            const wrap = trigger.closest('.react-wrap');
            const pop = $('[data-react-pop]', wrap);
            if (pop) {
                const opening = pop.hidden || pop.classList.contains('is-closing');
                $$('[data-react-pop]').forEach((p) => { if (p !== pop) closeReactPop(p); });
                opening ? openReactPop(pop) : closeReactPop(pop);
            }
            return;
        }

        const choice = e.target.closest('[data-react]');

        if (choice) {
            e.preventDefault();
            const wrap = choice.closest('.react-wrap');
            const postId = wrap.dataset.postId;
            const pop = $('[data-react-pop]', wrap);
            choice.classList.add('is-picked');
            closeReactPop(pop);

            post(url(`/posts/${postId}/reactions`), { type: choice.dataset.react })
                .then((r) => r.json())
                .then((data) => applyReaction(wrap, data))
                .catch(() => {});
            return;
        }

        if (!e.target.closest('[data-react-pop]')) {
            $$('[data-react-pop]').forEach(closeReactPop);
        }
    });

    function applyReaction(wrap, data) {
        const btn = $('[data-react-open]', wrap);
        if (!btn) return;
        const emoji = $('.pa-emoji', btn);
        const label = $('.pa-label', btn);
        let palette = {};
        try {
            palette = JSON.parse(wrap.dataset.palette || '{}');
        } catch (_) {}

        if (data.mine) {
            if (emoji) emoji.innerHTML = palette[data.mine]?.emoji || '&#128077;';
            if (label) label.textContent = palette[data.mine]?.label || 'Like';
            btn.classList.add('is-mine');
        } else {
            if (emoji) emoji.innerHTML = '&#128077;';
            if (label) label.textContent = 'Like';
            btn.classList.remove('is-mine');
        }

        if (emoji) {
            emoji.classList.remove('bump');
            void emoji.offsetWidth;
            emoji.classList.add('bump');
        }

        /* Rebuild the tally strip above the action bar. */
        const card = wrap.closest('.post') || wrap.closest('.article-page') || wrap.closest('.card') || wrap.parentElement;
        const tally = card ? $('[data-tally]', card) : null;

        if (tally) {
            const types = Object.keys(data.summary || {});

            if (!data.total) {
                tally.innerHTML = '';
                tally.hidden = true;
            } else {
                tally.hidden = false;
                tally.innerHTML =
                    `<span class="tally-emojis">${types
                        .slice(0, 4)
                        .map((t) => `<span>${palette[t]?.emoji || '&#128077;'}</span>`)
                        .join('')}</span>` +
                    `<span>${data.total} ${data.total === 1 ? 'reaction' : 'reactions'}</span>` +
                    (tally.dataset.commentLink
                        ? `<a class="tally-link" href="${tally.dataset.commentLink}">${tally.dataset.commentCount}</a>`
                        : '');
            }
        }
    }

    /* ====================================================================
       Post composer: quick post, rich text article, colour card
       ==================================================================== */

    const composer = $('[data-composer]');

    if (composer) {
        const typeInput = $('[name="type"]', composer);
        const themeInput = $('[name="theme"]', composer);
        const titleInput = $('[name="title"]', composer);
        const textarea = $('[data-composer-text]', composer);
        const articleEditor = $('[data-article-editor]', composer);
        const preview = $('[data-colored-preview]', composer);
        const themeRow = $('[data-theme-row]', composer);
        const imageBlock = $('[data-image-block]', composer);
        const fileInput = $('[data-composer-files]', composer);
        const thumbs = $('[data-composer-thumbs]', composer);
        const hint = $('[data-composer-hint]', composer);
        const photosText = $('[data-photos-text]', composer);
        const submit = $('[data-composer-submit]', composer);
        const inlineFileInput = $('[data-article-inline-file]', composer);
        const colorPopover = $('[data-color-popover]', composer);
        const colorTitle = $('[data-color-title]', composer);
        const colorBar = $('[data-color-bar]', composer);
        const colorPicker = $('[data-color-picker]', composer);

        const PLACEHOLDERS = {
            standard: 'Share an update with the network',
            article: 'Write your article... Style with headings, colored text, lists, and images.',
            colored: 'Write a short line for your gradient card',
        };

        let mode = 'standard';
        let savedRange = null;
        let currentColorTarget = 'text'; // 'text' or 'bg'

        /* Check if composer currently contains user content */
        function hasContent() {
            if (titleInput && titleInput.value.trim().length > 0) return true;
            if (mode === 'article') {
                const text = articleEditor ? articleEditor.innerText.replace(/\u200B/g, '').trim() : '';
                const hasImg = articleEditor ? articleEditor.querySelector('img') !== null : false;
                if (text.length > 0 || hasImg) return true;
            } else {
                if (textarea && textarea.value.trim().length > 0) return true;
            }
            if (fileInput && fileInput.files && fileInput.files.length > 0) return true;
            return false;
        }

        /* Clear all input fields and content in the composer */
        function clearComposer() {
            if (titleInput) titleInput.value = '';
            if (textarea) textarea.value = '';
            if (articleEditor) articleEditor.innerHTML = '';
            if (fileInput) fileInput.value = '';
            if (thumbs) {
                thumbs.innerHTML = '';
                thumbs.hidden = true;
            }
            syncColoured();
            autoGrow();
        }

        /* Synchronize rich text editor HTML to the form's body field */
        function syncArticleToTextarea() {
            if (!articleEditor || !textarea) return;
            const text = articleEditor.innerText.replace(/\u200B/g, '').trim();
            const hasImg = articleEditor.querySelector('img') !== null;

            if (!text && !hasImg) {
                textarea.value = '';
            } else {
                textarea.value = articleEditor.innerHTML;
            }
        }

        function setMode(next) {
            mode = PLACEHOLDERS[next] ? next : 'standard';
            typeInput.value = mode;

            const isArticle = mode === 'article';
            const isColored = mode === 'colored';

            composer.classList.toggle('is-article', isArticle);

            $$('[data-mode]', composer).forEach((tab) =>
                tab.classList.toggle('is-active', tab.dataset.mode === mode)
            );

            $$('[data-article-only]', composer).forEach((el) => (el.hidden = !isArticle));

            if (articleEditor) articleEditor.hidden = !isArticle;
            if (textarea) textarea.hidden = isArticle;

            themeRow.hidden = !isColored;
            preview.hidden = !isColored;
            if (imageBlock) imageBlock.hidden = isColored;
            if (hint) {
                hint.hidden = isColored;
                hint.textContent = isArticle ? 'Cover & gallery photos (up to 4)' : 'Up to 4 photos';
            }
            if (photosText) {
                photosText.textContent = isArticle ? 'Cover & Photos' : 'Photos';
            }

            textarea.placeholder = PLACEHOLDERS[mode];
            submit.lastChild.textContent = isArticle ? ' Publish article' : ' Post';

            // A colour card is text only, so drop any photos already picked.
            if (isColored) {
                if (fileInput) fileInput.value = '';
                if (thumbs) {
                    thumbs.innerHTML = '';
                    thumbs.hidden = true;
                }
            }

            renderThumbs();
            syncColoured();
            autoGrow();

            if (isArticle && articleEditor) {
                articleEditor.focus();
            }
        }

        /* Switching between tabs prompts when contents exist */
        $$('[data-mode]', composer).forEach((tab) => {
            tab.addEventListener('click', () => {
                const targetMode = tab.dataset.mode;
                if (targetMode === mode) return;

                if (hasContent()) {
                    modal({
                        title: 'Switch post type?',
                        icon: '⚠️',
                        body: '<p>Switching tabs will clear your current contents. Are you sure you want to proceed?</p>',
                        confirmLabel: 'Clear & switch',
                        danger: true,
                        onConfirm: () => {
                            clearComposer();
                            setMode(targetMode);
                        },
                    });
                } else {
                    setMode(targetMode);
                }
            });
        });

        /* Colour card live preview ------------------------------------- */
        function syncColoured() {
            if (preview.hidden) return;

            const text = textarea.value.trim();
            preview.textContent = text || 'Your words, on colour';
            preview.style.opacity = text ? '1' : '.72';

            const n = text.length;
            preview.style.fontSize =
                n <= 40 ? '1.9rem' : n <= 90 ? '1.55rem' : n <= 180 ? '1.25rem' : '1.05rem';
        }

        function autoGrow() {
            if (mode === 'article') return;
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 320) + 'px';
        }

        $$('[data-theme]', composer).forEach((swatch) => {
            swatch.addEventListener('click', () => {
                themeInput.value = swatch.dataset.theme;
                preview.style.background = swatch.dataset.css;
                $$('[data-theme]', composer).forEach((s) =>
                    s.classList.toggle('is-active', s === swatch)
                );
            });
        });

        textarea.addEventListener('input', () => {
            syncColoured();
            autoGrow();
        });

        /* Selection and Range preservation ----------------------------- */

        function saveSelection() {
            const sel = window.getSelection();
            if (sel && sel.rangeCount > 0) {
                const range = sel.getRangeAt(0);
                if (articleEditor && articleEditor.contains(range.commonAncestorContainer)) {
                    savedRange = range.cloneRange();
                }
            }
        }

        function restoreSelection() {
            if (savedRange) {
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(savedRange);
            }
        }

        function insertNodeAtCursor(node) {
            restoreSelection();
            const sel = window.getSelection();
            if (sel && sel.rangeCount > 0) {
                const range = sel.getRangeAt(0);
                range.deleteContents();
                range.insertNode(node);
                range.setStartAfter(node);
                range.setEndAfter(node);
                sel.removeAllRanges();
                sel.addRange(range);
            } else if (articleEditor) {
                articleEditor.appendChild(node);
            }
            saveSelection();
            syncArticleToTextarea();
        }

        function exec(command, value = null) {
            if (articleEditor) articleEditor.focus();
            restoreSelection();
            document.execCommand(command, false, value);
            if (articleEditor) articleEditor.focus();
            saveSelection();
            syncArticleToTextarea();
            updateToolbarState();
        }

        function updateToolbarState() {
            if (mode !== 'article' || !articleEditor) return;

            const commands = ['bold', 'italic', 'underline', 'insertUnorderedList', 'insertOrderedList', 'justifyLeft', 'justifyCenter', 'justifyRight'];
            commands.forEach((cmd) => {
                const btn = $(`[data-cmd="${cmd}"]`, composer);
                if (btn) {
                    try {
                        const active = document.queryCommandState(cmd);
                        btn.classList.toggle('is-active', !!active);
                    } catch {}
                }
            });
        }

        if (articleEditor) {
            ['keyup', 'mouseup', 'touchend'].forEach((evt) => {
                articleEditor.addEventListener(evt, () => {
                    saveSelection();
                    updateToolbarState();
                });
            });

            articleEditor.addEventListener('focus', () => {
                try {
                    document.execCommand('styleWithCSS', false, true);
                    document.execCommand('defaultParagraphSeparator', false, 'p');
                } catch {}
            });

            articleEditor.addEventListener('input', () => {
                syncArticleToTextarea();
                saveSelection();
                updateToolbarState();
            });

            articleEditor.addEventListener('keydown', (e) => {
                if (!(e.ctrlKey || e.metaKey)) return;
                const map = { b: 'bold', i: 'italic', u: 'underline' };
                const cmd = map[e.key.toLowerCase()];
                if (cmd) {
                    e.preventDefault();
                    exec(cmd);
                }
            });
        }

        /* Toolbar formatting buttons ----------------------------------- */

        $$('[data-cmd]', composer).forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                exec(btn.dataset.cmd);
            });
        });

        /* Colored text -------------------------------------------------- */

        const colorTriggers = $$('[data-color-trigger]', composer);
        colorTriggers.forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                saveSelection();
                if (colorPopover) colorPopover.hidden = !colorPopover.hidden;
            });
        });

        document.addEventListener('click', (e) => {
            if (colorPopover && !colorPopover.hidden && !e.target.closest('.color-tool-wrap')) {
                colorPopover.hidden = true;
            }
        });

        function applyColor(color) {
            if (!color) return;
            try {
                document.execCommand('styleWithCSS', false, true);
            } catch {}
            exec('foreColor', color);
            if (colorBar) colorBar.style.background = color;
            if (colorPopover) colorPopover.hidden = true;
        }

        if (colorPopover) {
            $$('[data-color]', colorPopover).forEach((dot) => {
                dot.addEventListener('click', (e) => {
                    e.preventDefault();
                    applyColor(dot.dataset.color);
                });
            });

            if (colorPicker) {
                colorPicker.addEventListener('input', () => {
                    applyColor(colorPicker.value);
                });
            }

            const clearColorBtn = $('[data-color-clear]', colorPopover);
            if (clearColorBtn) {
                clearColorBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    try {
                        document.execCommand('styleWithCSS', false, true);
                    } catch {}
                    exec('foreColor', '#111827');
                    if (colorBar) colorBar.style.background = '#2563eb';
                    colorPopover.hidden = true;
                });
            }
        }

        /* Image upload options for Articles ----------------------------- */

        function uploadAndInsertImage(file) {
            if (!file || !file.type.startsWith('image/')) return;

            const placeholder = document.createElement('div');
            placeholder.className = 'uploading-img-placeholder';
            placeholder.style.cssText = 'padding:1rem;background:var(--surface-sunk);border:1px dashed var(--line);border-radius:var(--r-md);margin:.6rem 0;color:var(--muted);font-size:.88rem;text-align:center;display:flex;align-items:center;justify-content:center;gap:.5rem';
            placeholder.innerHTML = '<span>Uploading image&hellip;</span>';
            insertNodeAtCursor(placeholder);

            const formData = new FormData();
            formData.append('image', file);

            post(url('/posts/upload-image'), formData)
                .then((r) => (r.ok ? r.json() : Promise.reject(r)))
                .then((data) => {
                    if (data.url) {
                        const img = document.createElement('img');
                        img.src = data.url;
                        img.alt = file.name || 'Article photo';
                        img.loading = 'lazy';
                        placeholder.replaceWith(img);
                    } else {
                        placeholder.remove();
                        modal({ title: 'Upload failed', body: '<p>Could not upload image.</p>', confirmLabel: 'OK' });
                    }
                    syncArticleToTextarea();
                })
                .catch(() => {
                    placeholder.remove();
                    modal({ title: 'Upload failed', body: '<p>Error uploading image. Please check file format and size.</p>', confirmLabel: 'OK' });
                    syncArticleToTextarea();
                });
        }

        const insertImageBtn = $('[data-action="insert-image"]', composer);
        if (insertImageBtn) {
            insertImageBtn.addEventListener('click', (e) => {
                e.preventDefault();
                saveSelection();

                modal({
                    title: 'Insert Image into Article',
                    body: `
                        <div style="display:flex;flex-direction:column;gap:1rem">
                            <div>
                                <label style="display:block;font-size:.82rem;font-weight:600;margin-bottom:.35rem;color:var(--ink)">Upload from computer</label>
                                <input type="file" accept="image/*" class="input" data-modal-img-file style="width:100%;padding:.45rem">
                                <span class="tiny muted" style="display:block;margin-top:.25rem">Supports JPG, PNG, GIF, WebP (up to 6MB)</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:.6rem">
                                <span style="flex:1;height:1px;background:var(--line)"></span>
                                <span class="tiny muted" style="text-transform:uppercase;font-weight:600;font-size:.7rem">or image URL</span>
                                <span style="flex:1;height:1px;background:var(--line)"></span>
                            </div>
                            <div>
                                <label style="display:block;font-size:.82rem;font-weight:600;margin-bottom:.35rem;color:var(--ink)">Web link</label>
                                <input type="url" class="input" data-modal-img-url placeholder="https://example.com/photo.jpg" style="width:100%">
                            </div>
                        </div>
                    `,
                    confirmLabel: 'Insert image',
                    onConfirm: (root) => {
                        const fileEl = $('[data-modal-img-file]', root);
                        const urlEl = $('[data-modal-img-url]', root);

                        if (fileEl && fileEl.files && fileEl.files.length > 0) {
                            uploadAndInsertImage(fileEl.files[0]);
                        } else if (urlEl && urlEl.value.trim()) {
                            const imgUrl = urlEl.value.trim();
                            const img = document.createElement('img');
                            img.src = imgUrl;
                            img.alt = 'Article photo';
                            img.loading = 'lazy';
                            insertNodeAtCursor(img);
                        }
                    },
                });
            });
        }

        if (inlineFileInput) {
            inlineFileInput.addEventListener('change', () => {
                if (inlineFileInput.files && inlineFileInput.files.length > 0) {
                    uploadAndInsertImage(inlineFileInput.files[0]);
                    inlineFileInput.value = '';
                }
            });
        }

        /* Drag and drop image onto article editor */
        if (articleEditor) {
            articleEditor.addEventListener('dragover', (e) => {
                e.preventDefault();
                articleEditor.classList.add('is-dragover');
            });
            articleEditor.addEventListener('dragleave', () => {
                articleEditor.classList.remove('is-dragover');
            });
            articleEditor.addEventListener('drop', (e) => {
                e.preventDefault();
                articleEditor.classList.remove('is-dragover');
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    const file = e.dataTransfer.files[0];
                    if (file.type.startsWith('image/')) {
                        uploadAndInsertImage(file);
                    }
                }
            });

            /* Paste image from clipboard */
            articleEditor.addEventListener('paste', (e) => {
                if (e.clipboardData && e.clipboardData.items) {
                    for (let i = 0; i < e.clipboardData.items.length; i++) {
                        const item = e.clipboardData.items[i];
                        if (item.type.startsWith('image/')) {
                            e.preventDefault();
                            const file = item.getAsFile();
                            if (file) {
                                uploadAndInsertImage(file);
                            }
                            return;
                        }
                    }
                }
            });
        }

        /* Photos / Cover photo thumbnails in footer -------------------- */

        function renderThumbs() {
            if (!thumbs || !fileInput) return;
            thumbs.innerHTML = '';
            const files = Array.from(fileInput.files || []).slice(0, 4);
            thumbs.hidden = files.length === 0;

            files.forEach((file, index) => {
                const box = document.createElement('div');
                box.className = 'attach-thumb';

                if (mode === 'article' && index === 0) {
                    const badge = document.createElement('span');
                    badge.className = 'thumb-badge';
                    badge.textContent = 'Cover';
                    box.appendChild(badge);
                }

                const img = document.createElement('img');
                img.alt = file.name;
                img.src = URL.createObjectURL(file);
                box.appendChild(img);

                thumbs.appendChild(box);
            });
        }

        if (fileInput) {
            fileInput.addEventListener('change', () => {
                renderThumbs();
                if (fileInput.files.length && mode === 'colored') setMode('standard');
            });
        }

        /* Form submit handler ------------------------------------------ */

        composer.addEventListener('submit', () => {
            if (mode === 'article') {
                syncArticleToTextarea();
            }
        });

        // Initialize editor state
        setMode(typeInput.value || 'standard');
        if (typeInput.value === 'article' && articleEditor && textarea.value) {
            articleEditor.innerHTML = textarea.value;
        }
    }

    /* ====================================================================
       Comment reply toggles
       ==================================================================== */

    document.addEventListener('click', (e) => {
        const toggle = e.target.closest('[data-reply-toggle]');
        if (!toggle) return;

        e.preventDefault();
        const form = document.getElementById(toggle.dataset.replyToggle);
        if (!form) return;

        form.hidden = !form.hidden;
        if (!form.hidden) $('textarea', form)?.focus();
    });

    /* Submit a comment with Enter, newline with Shift+Enter. */
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' || e.shiftKey) return;
        const ta = e.target.closest('.comment-form textarea');
        if (!ta) return;

        e.preventDefault();
        // requestSubmit fires the submit event, so pages that post comments in place can intercept it.
        if (ta.value.trim()) ta.closest('form').requestSubmit();
    });

    /* ====================================================================
       Lightbox for images, PDFs and media
       ==================================================================== */

    function closeLightbox() {
        $('.lightbox')?.remove();
        document.body.style.overflow = '';
    }

    function openLightbox(src, mime) {
        closeLightbox();

        const box = document.createElement('div');
        box.className = 'lightbox';

        let inner;

        if (mime && mime.startsWith('video/')) {
            inner = `<video src="${escapeHtml(src)}" controls autoplay></video>`;
        } else if (mime && mime.startsWith('audio/')) {
            inner = `<audio src="${escapeHtml(src)}" controls autoplay style="width:min(520px,90vw)"></audio>`;
        } else if (mime && (mime === 'application/pdf' || mime.startsWith('text/'))) {
            inner = `<iframe src="${escapeHtml(src)}" title="Preview"></iframe>`;
        } else {
            inner = `<img src="${escapeHtml(src)}" alt="Preview">`;
        }

        box.innerHTML = `<button class="lightbox-close" type="button" aria-label="Close preview">&#10005;</button>${inner}`;
        box.addEventListener('click', (e) => {
            if (e.target === box || e.target.closest('.lightbox-close')) closeLightbox();
        });

        document.body.appendChild(box);
        document.body.style.overflow = 'hidden';
    }

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-lightbox]');
        if (!trigger) return;

        e.preventDefault();
        openLightbox(trigger.dataset.lightbox, trigger.dataset.mime || '');
    });

    /* ====================================================================
       Generic modals: confirm, rename, new folder
       ==================================================================== */

    function modal({ title, icon, body, confirmLabel, danger, onConfirm, focus }) {
        const back = document.createElement('div');
        back.className = 'modal-backdrop';
        back.setAttribute('data-modal', '');

        back.innerHTML = `
          <div class="modal" role="dialog" aria-modal="true" aria-label="${escapeHtml(title)}">
            <div class="modal-head">
              ${icon ? `<span class="alert-icon">${icon}</span>` : ''}
              <h3>${escapeHtml(title)}</h3>
            </div>
            <div class="modal-body">${body}</div>
            <div class="modal-foot">
              <button type="button" class="btn btn-ghost" data-modal-cancel>Cancel</button>
              <button type="button" class="btn ${danger ? 'btn-coral' : ''}" data-modal-ok>${escapeHtml(confirmLabel)}</button>
            </div>
          </div>`;

        const close = () => back.remove();

        back.addEventListener('click', (e) => {
            if (e.target === back || e.target.closest('[data-modal-cancel]')) close();
        });

        $('[data-modal-ok]', back).addEventListener('click', () => {
            if (onConfirm(back) !== false) close();
        });

        back.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
                e.preventDefault();
                $('[data-modal-ok]', back).click();
            }
        });

        document.body.appendChild(back);

        const target = focus ? $(focus, back) : $('[data-modal-ok]', back);
        target?.focus();
        if (target?.select) target.select();

        return back;
    }

    function submitHidden(action, method, fields) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        form.style.display = 'none';

        const add = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        };

        add('_token', csrf());
        if (method && method !== 'POST') add('_method', method);
        Object.entries(fields || {}).forEach(([k, v]) => add(k, v));

        document.body.appendChild(form);
        form.submit();
    }

    /* Destructive actions get a real dialog, never a browser confirm(). */
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-confirm]');
        if (!trigger) return;

        e.preventDefault();

        const form = trigger.closest('form');

        modal({
            title: trigger.dataset.confirmTitle || 'Are you sure?',
            icon: '&#9888;&#65039;',
            body: `<p>${escapeHtml(trigger.dataset.confirm)}</p>`,
            confirmLabel: trigger.dataset.confirmLabel || 'Delete',
            danger: true,
            onConfirm: () => {
                // An explicit data-action wins over an enclosing form (admin rows sit inside a bulk form).
                if (trigger.dataset.action) submitHidden(trigger.dataset.action, trigger.dataset.method || 'DELETE', {});
                else if (form) form.submit();
            },
        });
    });

    /* Rename a drive file or folder. */
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-rename]');
        if (!trigger) return;

        e.preventDefault();

        modal({
            title: trigger.dataset.renameTitle || 'Rename',
            icon: '&#9999;&#65039;',
            body: `<div class="field"><label for="rename-input">Name</label>
                   <input type="text" id="rename-input" value="${escapeHtml(trigger.dataset.renameValue)}" maxlength="150"></div>`,
            confirmLabel: 'Save name',
            focus: '#rename-input',
            onConfirm: (root) => {
                const value = $('#rename-input', root).value.trim();
                if (!value) return false;
                submitHidden(trigger.dataset.rename, 'POST', { name: value });
            },
        });
    });

    /* Create a folder in the current drive location. */
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-new-folder]');
        if (!trigger) return;

        e.preventDefault();

        modal({
            title: 'New folder',
            icon: '&#128193;',
            body: `<div class="field"><label for="folder-input">Folder name</label>
                   <input type="text" id="folder-input" placeholder="Reports" maxlength="120">
                   <p class="hint">New folders are private unless the folder they sit in is already public.</p></div>`,
            confirmLabel: 'Create folder',
            focus: '#folder-input',
            onConfirm: (root) => {
                const value = $('#folder-input', root).value.trim();
                if (!value) return false;
                submitHidden(trigger.dataset.newFolder, 'POST', {
                    name: value,
                    parent_id: trigger.dataset.parent || '',
                });
            },
        });
    });

    /* ====================================================================
       Drive upload
       ==================================================================== */

    const uploadForm = $('[data-upload-form]');

    if (uploadForm) {
        const input = $('input[type="file"]', uploadForm);
        const dropzone = $('[data-dropzone]', uploadForm);

        /* Submitting is driven by the input itself, so the toolbar label, the
           drop zone and a drag-and-drop all take the same path. */
        input.addEventListener('change', () => {
            if (!input.files.length) return;

            if (dropzone) {
                dropzone.classList.add('is-busy');
                $('.dz-main', dropzone).textContent =
                    'Uploading ' + input.files.length + (input.files.length === 1 ? ' file...' : ' files...');
            }

            uploadForm.submit();
        });

        if (dropzone) {
            dropzone.addEventListener('click', () => input.click());

            dropzone.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    input.click();
                }
            });

            ['dragenter', 'dragover'].forEach((type) =>
                dropzone.addEventListener(type, (e) => {
                    e.preventDefault();
                    dropzone.classList.add('is-over');
                })
            );

            ['dragleave', 'drop'].forEach((type) =>
                dropzone.addEventListener(type, (e) => {
                    e.preventDefault();
                    dropzone.classList.remove('is-over');
                })
            );

            dropzone.addEventListener('drop', (e) => {
                if (e.dataTransfer?.files?.length) {
                    input.files = e.dataTransfer.files;
                    input.dispatchEvent(new Event('change'));
                }
            });
        }
    }

    /* ====================================================================
       Tabs (profile page)
       ==================================================================== */

    $$('[data-tabs]').forEach((group) => {
        const tabs = $$('[data-tab]', group);
        const panels = $$('[data-panel]');

        function activate(name, pushHash) {
            const known = tabs.some((t) => t.dataset.tab === name);
            if (!known) return false;

            tabs.forEach((t) => {
                const on = t.dataset.tab === name;
                t.classList.toggle('is-active', on);
                t.setAttribute('aria-selected', String(on));
                t.tabIndex = on ? 0 : -1;
            });

            panels.forEach((p) => (p.hidden = p.dataset.panel !== name));

            if (pushHash) {
                history.replaceState(null, '', '#' + name);
            }

            return true;
        }

        tabs.forEach((tab) =>
            tab.addEventListener('click', () => activate(tab.dataset.tab, true))
        );

        /* Arrow keys move between tabs, as a tablist should. */
        group.addEventListener('keydown', (e) => {
            if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;

            const i = tabs.findIndex((t) => t.classList.contains('is-active'));
            const next = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];

            e.preventDefault();
            activate(next.dataset.tab, true);
            next.focus();
        });

        // Deep links (#about) and paginated post pages both land on the right tab.
        activate(location.hash.replace('#', '') || tabs[0]?.dataset.tab, false);
    });

    /* ====================================================================
       Shared files browser (profile)
       ==================================================================== */

    const sharedBrowser = $('[data-shared]');

    if (sharedBrowser) {
        const search = $('[data-shared-search]', sharedBrowser);
        const tree = $('[data-shared-tree]', sharedBrowser);
        const noMatch = $('[data-shared-empty]', sharedBrowser);
        const termOut = $('[data-shared-term]', sharedBrowser);
        const summary = $('[data-shared-summary]', sharedBrowser);
        const summaryText = summary ? summary.textContent.trim() : '';

        const setOpen = (node, open) => {
            $('[data-tree-toggle]', node).setAttribute('aria-expanded', String(open));
            node.querySelector(':scope > .tree-body').hidden = !open;
        };

        /* Expand and collapse ------------------------------------------ */
        tree.addEventListener('click', (e) => {
            const head = e.target.closest('[data-tree-toggle]');
            if (!head) return;

            const node = head.closest('[data-tree-folder]');
            setOpen(node, head.getAttribute('aria-expanded') !== 'true');
        });

        $('[data-shared-expand]', sharedBrowser)?.addEventListener('click', () =>
            $$('[data-tree-folder]', tree).forEach((n) => setOpen(n, true))
        );

        $('[data-shared-collapse]', sharedBrowser)?.addEventListener('click', () =>
            $$('[data-tree-folder]', tree).forEach((n) => setOpen(n, false))
        );

        /* Search -------------------------------------------------------- */
        const matches = (el, term) => (el.dataset.name || '').toLowerCase().includes(term);

        /**
         * Hides anything that does not match, keeps a folder visible when one of
         * its descendants matches, and opens the path down to every hit.
         * Returns whether this node contains a match.
         */
        function filterNode(node, term) {
            const selfHit = matches(node, term);
            let childHit = false;

            $$(':scope > .tree-body > [data-tree-folder]', node).forEach((child) => {
                if (filterNode(child, term)) childHit = true;
            });

            $$(':scope > .tree-body > [data-tree-file]', node).forEach((file) => {
                // Everything inside a matching folder stays listed.
                const hit = selfHit || matches(file, term);
                file.hidden = !hit;
                file.classList.toggle('is-match', !!term && matches(file, term));
                if (hit) childHit = true;
            });

            const keep = selfHit || childHit;
            node.hidden = !keep;
            node.classList.toggle('is-match', !!term && selfHit);

            if (term) setOpen(node, keep);

            return keep;
        }

        function applySearch(raw) {
            const term = raw.trim().toLowerCase();
            let visible = 0;

            $$(':scope > [data-tree-folder]', tree).forEach((node) => {
                if (filterNode(node, term)) visible++;
            });

            noMatch.hidden = visible > 0;
            tree.hidden = visible === 0;
            if (termOut) termOut.textContent = raw.trim();

            if (summary) {
                summary.textContent = term
                    ? visible + (visible === 1 ? ' folder matches' : ' folders match')
                    : summaryText;
            }

            if (!term) {
                // Back to the resting state: roots open, nested folders closed.
                $$('[data-tree-folder]', tree).forEach((node) => {
                    node.hidden = false;
                    node.classList.remove('is-match');
                    $$(':scope > .tree-body > [data-tree-file]', node).forEach((f) => {
                        f.hidden = false;
                        f.classList.remove('is-match');
                    });
                    setOpen(node, node.parentElement === tree);
                });
            }
        }

        let searchTimer;
        search.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => applySearch(search.value), 140);
        });

        search.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                search.value = '';
                applySearch('');
            }
        });
    }

    /* Avatar / cover live preview on the profile form. */
    $$('[data-image-preview]').forEach((input) => {
        input.addEventListener('change', () => {
            const target = $(input.dataset.imagePreview);
            if (target && input.files[0]) {
                target.src = URL.createObjectURL(input.files[0]);
            }
        });
    });

    /* ====================================================================
       Chat
       ==================================================================== */

    const chat = $('[data-chat]');

    if (chat) {
        const scroll = $('[data-chat-scroll]', chat);
        const form = $('[data-chat-form]', chat);
        const textarea = $('[data-chat-text]', chat);
        const fileInput = $('[data-chat-files]', chat);
        const pending = $('[data-pending]', chat);
        const statusEl = $('[data-partner-status]', chat);
        const conversationId = chat.dataset.conversation;

        let lastId = parseInt(chat.dataset.lastId || '0', 10);
        let since = chat.dataset.since || '';
        let drivePicks = [];
        let uploadFiles = [];
        let sending = false;

        const atBottom = () => scroll.scrollHeight - scroll.scrollTop - scroll.clientHeight < 140;
        const toBottom = () => (scroll.scrollTop = scroll.scrollHeight);

        toBottom();

        /**
         * Append bubbles, skipping any whose id is already on screen. A poll
         * started just before you hit send comes back carrying your own new
         * message, so without this check it would be shown twice.
         */
        const stage = document.createElement('div');

        function appendBubbles(htmlList) {
            let added = false;

            htmlList.forEach((html) => {
                stage.innerHTML = html;
                const node = stage.firstElementChild;
                if (!node) return;

                const id = node.dataset.message;
                if (id && scroll.querySelector(`[data-message="${id}"]`)) return;

                scroll.appendChild(node);
                added = true;
            });

            return added;
        }

        /* Pending attachment chips ------------------------------------ */
        function renderPending() {
            pending.innerHTML = '';

            uploadFiles.forEach((file, i) => {
                pending.insertAdjacentHTML(
                    'beforeend',
                    `<span class="pending-chip from-upload">&#128206;<span>${escapeHtml(file.name)}</span>
                     <button type="button" data-drop-upload="${i}" aria-label="Remove">&#10005;</button></span>`
                );
            });

            drivePicks.forEach((file) => {
                pending.insertAdjacentHTML(
                    'beforeend',
                    `<span class="pending-chip">&#9729;&#65039;<span>${escapeHtml(file.name)}</span>
                     <button type="button" data-drop-drive="${file.id}" aria-label="Remove">&#10005;</button></span>`
                );
            });

            pending.hidden = uploadFiles.length === 0 && drivePicks.length === 0;
        }

        pending.addEventListener('click', (e) => {
            const up = e.target.closest('[data-drop-upload]');
            const dr = e.target.closest('[data-drop-drive]');

            if (up) {
                uploadFiles.splice(parseInt(up.dataset.dropUpload, 10), 1);
                renderPending();
            }

            if (dr) {
                drivePicks = drivePicks.filter((f) => String(f.id) !== dr.dataset.dropDrive);
                renderPending();
            }
        });

        fileInput.addEventListener('change', () => {
            uploadFiles = uploadFiles.concat(Array.from(fileInput.files)).slice(0, 5);
            fileInput.value = '';
            renderPending();
        });

        /* Attach from SaverMate Drive: your own files plus the network drive */
        $('[data-drive-picker]', chat)?.addEventListener('click', () => {
            const picked = new Map(drivePicks.map((f) => [String(f.id), f]));
            let scope = 'all';

            const dialog = modal({
                title: 'Attach from SaverMate Drive',
                icon: '&#9729;&#65039;',
                body: `<div class="picker-tools">
                         <input type="search" id="picker-search" placeholder="Search files by name">
                         <div class="seg seg-sm picker-scope">
                           <button type="button" data-scope="all" class="is-active">All</button>
                           <button type="button" data-scope="mine">My drive</button>
                           <button type="button" data-scope="network">Network</button>
                         </div>
                       </div>
                       <div class="picker-list" data-picker-list>
                         <p class="small muted center">Loading files...</p>
                       </div>
                       <p class="tiny muted picker-count" data-picker-count></p>`,
                confirmLabel: 'Attach selected',
                focus: '#picker-search',
                onConfirm: () => {
                    // The dialog's selection replaces the attached drive files (unticking removes one).
                    drivePicks = Array.from(picked.values());
                    renderPending();
                },
            });

            const list = $('[data-picker-list]', dialog);
            const count = $('[data-picker-count]', dialog);
            const search = $('#picker-search', dialog);

            const updateCount = () => {
                count.textContent = picked.size ? `${picked.size} selected` : 'Your private files stay private: they are only shared with this person.';
            };

            const row = (f) => `
                <div class="picker-row ${picked.has(String(f.id)) ? 'is-picked' : ''}" data-id="${f.id}" data-name="${escapeHtml(f.name)}"
                     tabindex="0" role="button" aria-pressed="${picked.has(String(f.id))}">
                  <span class="file-glyph" style="background:${escapeHtml(f.color)}">${escapeHtml(f.glyph)}</span>
                  <span class="pk-body">
                    <span class="pk-name">${escapeHtml(f.name)}</span>
                    <span class="pk-meta">${f.owner ? escapeHtml(f.owner) + ' &middot; ' : ''}${escapeHtml(f.folder)} &middot; ${escapeHtml(f.size)}</span>
                  </span>
                  ${f.is_public ? '<span class="badge-public">Public</span>' : '<span class="badge-private">Private</span>'}
                </div>`;

            const section = (title, files) => files.length
                ? `<div class="picker-group">${escapeHtml(title)} <span>${files.length}</span></div>${files.map(row).join('')}`
                : '';

            let requestId = 0;
            function load() {
                const id = ++requestId;
                const term = search.value.trim();
                getJson(url(`/chat/drive-picker?scope=${scope}&q=${encodeURIComponent(term)}`))
                    .then((data) => {
                        if (id !== requestId) return; // a newer search is on its way
                        const html = section('My drive', data.mine) + section('Network drive', data.network);
                        list.innerHTML = html || `<p class="small muted center" style="padding:1rem">${
                            term ? 'No files match "' + escapeHtml(term) + '".' : 'No files here yet.'}</p>`;
                    })
                    .catch(() => {
                        list.innerHTML = '<p class="small muted center">Could not load the drive.</p>';
                    });
            }

            const toggle = (el) => {
                const id = el.dataset.id;
                if (picked.has(id)) picked.delete(id);
                else picked.set(id, { id, name: el.dataset.name });
                el.classList.toggle('is-picked', picked.has(id));
                el.setAttribute('aria-pressed', String(picked.has(id)));
                updateCount();
            };

            let timer;
            search.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(load, 220);
            });

            $$('[data-scope]', dialog).forEach((b) => b.addEventListener('click', () => {
                scope = b.dataset.scope;
                $$('[data-scope]', dialog).forEach((x) => x.classList.toggle('is-active', x === b));
                load();
            }));

            list.addEventListener('click', (e) => {
                const el = e.target.closest('.picker-row');
                if (el) toggle(el);
            });

            list.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    const el = e.target.closest('.picker-row');
                    if (el) {
                        e.preventDefault();
                        toggle(el);
                    }
                }
            });

            updateCount();
            load();
        });

        /* Sending ------------------------------------------------------ */
        function send() {
            if (sending) return;

            const text = textarea.value.trim();
            if (!text && !uploadFiles.length && !drivePicks.length) return;

            sending = true;

            const data = new FormData();
            data.append('body', text);
            uploadFiles.forEach((f) => data.append('attachments[]', f));
            drivePicks.forEach((f) => data.append('drive_files[]', f.id));

            post(form.action, data)
                .then((r) => (r.ok ? r.json() : Promise.reject(r)))
                .then((payload) => {
                    appendBubbles([payload.html]);
                    lastId = Math.max(lastId, payload.id);
                    textarea.value = '';
                    textarea.style.height = '';
                    uploadFiles = [];
                    drivePicks = [];
                    renderPending();
                    toBottom();
                })
                .catch(() => {
                    form.submit();
                })
                .finally(() => {
                    sending = false;
                    textarea.focus();
                });
        }

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            send();
        });

        textarea.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                send();
            }
        });

        textarea.addEventListener('input', () => {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 140) + 'px';
        });

        /* Receiving ---------------------------------------------------- */
        function pollMessages() {
            if (document.visibilityState !== 'visible') return;

            getJson(url(`/chat/${conversationId}/poll?after=${lastId}&since=${encodeURIComponent(since)}`))
                .then((data) => {
                    // Messages edited or deleted since the last poll.
                    (data.changed || []).forEach((m) => window.SM.replaceBubble?.(m.id, m.html));
                    if (data.now) since = data.now;

                    if (data.html.length) {
                        const stick = atBottom();

                        if (appendBubbles(data.html) && stick) {
                            toBottom();
                        }
                    }

                    // Always advance, even when every bubble was a duplicate,
                    // so the next poll does not fetch the same messages again.
                    lastId = Math.max(lastId, data.last_id);

                    // Turn ticks double as people read your messages.
                    window.SM.updateReceipts?.(scroll, data);

                    if (statusEl) {
                        statusEl.textContent = data.partner_status;
                        statusEl.classList.toggle('is-online', data.partner_online);
                        const av = $('[data-partner-avatar]', chat);
                        av?.classList.toggle('is-online', data.partner_online);
                    }
                })
                .catch(() => {});
        }

        setInterval(pollMessages, 3000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') pollMessages();
        });

        textarea.focus();
    }

    /* ====================================================================
       Chat: filter the conversation list, keep the open one in view
       ==================================================================== */

    const threadFilter = $('[data-thread-filter]');
    threadFilter?.addEventListener('input', () => {
        const term = threadFilter.value.trim().toLowerCase();
        let shown = 0;
        $$('.thread-item').forEach((item) => {
            const match = !term || item.dataset.threadName.includes(term);
            item.hidden = !match;
            if (match) shown++;
        });
        $('[data-thread-none]').hidden = shown > 0;
    });

    $('.thread-item.is-active')?.scrollIntoView({ block: 'nearest' });

    /* ====================================================================
       Newsfeed infinite scroll: fetch the next page as the reader nears the
       bottom and append its posts. Reactions, comments, lightbox and delete
       all use delegated listeners, so appended posts work as-is.
       ==================================================================== */

    const feedList = $('[data-feed-list]');
    const feedMore = $('[data-feed-more]');

    if (feedList && feedMore && 'IntersectionObserver' in window) {
        const loading = $('[data-feed-loading]', feedMore);
        const retry = $('[data-feed-retry]', feedMore);
        const end = $('[data-feed-end]', feedMore);
        let next = feedMore.dataset.next || '';
        let busy = false;

        $('[data-feed-pager]')?.setAttribute('hidden', '');

        const loadMore = async () => {
            if (busy || !next) return;
            busy = true;
            loading.hidden = false;
            retry.hidden = true;

            try {
                const res = await fetch(next, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
                if (!res.ok) throw new Error(res.status);

                const doc = new DOMParser().parseFromString(await res.text(), 'text/html');

                // New posts published meanwhile shift pages down; skip any already shown.
                $$('[data-feed-list] > .post', doc).forEach((post) => {
                    if (!document.getElementById(post.id)) feedList.appendChild(document.importNode(post, true));
                });

                next = $('[data-feed-more]', doc)?.dataset.next || '';
                end.hidden = !!next;
            } catch (_) {
                retry.hidden = false;
            } finally {
                busy = false;
                loading.hidden = true;
            }

            // A tall screen may still show the end marker: keep filling.
            if (next && feedMore.getBoundingClientRect().top < window.innerHeight + 900) loadMore();
        };

        new IntersectionObserver((entries) => {
            if (entries.some((e) => e.isIntersecting)) loadMore();
        }, { rootMargin: '0px 0px 900px 0px' }).observe(feedMore);

        retry.addEventListener('click', loadMore);
    }

    /* ====================================================================
       Sign-in / create-account popup
       Opened by any [data-auth-open="login|register"]; the forms post with
       fetch so validation errors appear in place without a page reload.
       ==================================================================== */

    const authModal = $('[data-auth-modal]');

    if (authModal) {
        let lastFocus = null;

        const asideTitles = {
            login: 'Welcome back to the network',
            register: 'Join your colleagues on SaverMate',
        };

        const showTab = (name) => {
            if (!$(`[data-auth-form="${name}"]`, authModal)) name = 'login';

            $$('[data-auth-form]', authModal).forEach((f) => (f.hidden = f.dataset.authForm !== name));
            $$('.am-tabs [data-auth-tab]', authModal).forEach((t) => {
                const active = t.dataset.authTab === name;
                t.classList.toggle('is-active', active);
                t.setAttribute('aria-selected', String(active));
            });
            $('[data-am-aside-title]', authModal).textContent = asideTitles[name];

            const form = $(`[data-auth-form="${name}"]`, authModal);
            ($('.input-error', form) || $('input:not([type=hidden])', form))?.focus();
        };

        const openAuth = (name) => {
            lastFocus = document.activeElement;
            closeDropdowns();
            authModal.hidden = false;
            document.body.classList.add('has-auth-modal');
            showTab(name || 'login');
        };

        const closeAuth = () => {
            authModal.hidden = true;
            document.body.classList.remove('has-auth-modal');
            lastFocus?.focus?.();
        };

        window.SM.openAuth = openAuth;

        document.addEventListener('click', (e) => {
            const opener = e.target.closest('[data-auth-open]');
            if (opener) {
                e.preventDefault();
                openAuth(opener.dataset.authOpen);
                return;
            }

            const tab = e.target.closest('[data-auth-tab]');
            if (tab && authModal.contains(tab)) {
                e.preventDefault();
                showTab(tab.dataset.authTab);
                return;
            }

            if (e.target === authModal || e.target.closest('[data-auth-close]')) closeAuth();
        });

        document.addEventListener('keydown', (e) => {
            if (authModal.hidden) return;

            if (e.key === 'Escape') closeAuth();

            // Keep Tab cycling inside the popup.
            if (e.key === 'Tab') {
                const focusable = $$('button, a[href], input:not([type=hidden]), textarea, summary', authModal)
                    .filter((el) => el.offsetParent !== null);
                const first = focusable[0];
                const last = focusable[focusable.length - 1];

                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });

        const clearErrors = (form) => {
            $$('[data-error-for]', form).forEach((p) => {
                p.hidden = true;
                p.textContent = '';
            });
            $$('.input-error', form).forEach((i) => i.classList.remove('input-error'));
            $('.auth-form-error', form)?.remove();
        };

        const showErrors = (form, errors) => {
            let firstBad = null;

            Object.entries(errors).forEach(([field, messages]) => {
                const slot = $(`[data-error-for="${field}"]`, form);
                const input = form.elements[field];

                if (slot) {
                    slot.textContent = messages[0];
                    slot.hidden = false;
                } else {
                    const box = document.createElement('div');
                    box.className = 'auth-form-error';
                    box.textContent = messages[0];
                    $('.sub', form).after(box);
                }

                if (input?.classList) {
                    input.classList.add('input-error');
                    firstBad = firstBad || input;
                }

                // Reveal optional fields if one of them failed.
                input?.closest?.('details')?.setAttribute('open', '');
            });

            firstBad?.focus();
        };

        $$('[data-auth-form]', authModal).forEach((form) => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                const button = $('[data-auth-submit]', form);
                const label = button.textContent;

                clearErrors(form);
                button.disabled = true;
                button.textContent = form.dataset.authForm === 'login' ? 'Signing in…' : 'Creating account…';

                try {
                    const res = await post(form.action, new FormData(form));
                    const data = await res.json().catch(() => ({}));

                    if (res.ok && data.redirect) {
                        window.location.assign(data.redirect);
                        return;
                    }

                    if (res.status === 419) {
                        showErrors(form, { _: ['Your session expired. Reload the page and try again.'] });
                    } else if (res.status === 429) {
                        showErrors(form, { _: ['Too many attempts. Wait a minute and try again.'] });
                    } else {
                        showErrors(form, data.errors || { _: [data.message || 'Something went wrong. Please try again.'] });
                    }
                } catch (_) {
                    showErrors(form, { _: ['Could not reach the server. Check your connection.'] });
                }

                button.disabled = false;
                button.textContent = label;
            });
        });

        if (authModal.dataset.start) openAuth(authModal.dataset.start);
    }

    /* Announcement banner: remember a dismissal for this message only. */
    $$('[data-announcement]').forEach((bar) => {
        const key = 'sm-announcement-' + bar.dataset.announcement;
        try {
            if (localStorage.getItem(key)) return bar.remove();
        } catch (_) {}

        $('.announcement-close', bar)?.addEventListener('click', () => {
            bar.remove();
            try { localStorage.setItem(key, '1'); } catch (_) {}
        });
    });

    /* ====================================================================
       Group chats: details panel, and a colleague picker used both to
       create a group ([data-new-group]) and to add people ([data-add-members]).
       ==================================================================== */

    const groupPanel = $('[data-group-panel]');

    if (groupPanel) {
        const showPanel = (open) => {
            groupPanel.hidden = !open;
            if (open) $('input, button', groupPanel)?.focus();
        };

        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-group-panel-open]')) showPanel(true);
            else if (e.target === groupPanel || e.target.closest('[data-group-panel-close]')) showPanel(false);
        });

        document.addEventListener('keydown', (e) => {
            // Leave Escape to a confirm dialog opened on top of the panel.
            if (e.key === 'Escape' && !groupPanel.hidden && !$('[data-modal]')) showPanel(false);
        });

        // Reopen after a rename, mute or member change so the result is visible.
        try {
            if (sessionStorage.getItem('sm-group-panel') === location.pathname) showPanel(true);
            sessionStorage.removeItem('sm-group-panel');
        } catch (_) {}

        groupPanel.addEventListener('submit', () => {
            try { sessionStorage.setItem('sm-group-panel', location.pathname); } catch (_) {}
        });
    }

    function postForm(action, fields) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        form.hidden = true;

        const add = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        };

        add('_token', csrf());
        fields.forEach(([name, value]) => add(name, value));
        document.body.appendChild(form);
        form.submit();
    }

    function openPeoplePicker({ title, confirmLabel, action, withName, groupId }) {
        const picked = new Map(); // id -> person

        const dialog = modal({
            title,
            icon: '&#128101;',
            confirmLabel,
            focus: withName ? '#group-name' : '#people-search',
            body: `
              ${withName ? `<div class="field">
                  <label for="group-name">Group name</label>
                  <input type="text" id="group-name" maxlength="80" placeholder="Ward 4 nurses">
                  <p class="error-text" data-err-name hidden>Give the group a name.</p>
                </div>` : ''}
              <div class="field" style="margin-bottom:.6rem">
                <label for="people-search">${withName ? 'Members' : 'Find colleagues'}</label>
                <input type="search" id="people-search" placeholder="Search by name, username or job role" autocomplete="off">
                <p class="error-text" data-err-people hidden>Pick at least one colleague.</p>
              </div>
              <div class="people-picked" data-picked hidden></div>
              <div class="people-list" data-people><p class="small muted center" style="padding:1rem">Loading&hellip;</p></div>`,
            onConfirm: (root) => {
                const name = withName ? $('#group-name', root).value.trim() : '';
                $('[data-err-name]', root)?.toggleAttribute('hidden', !!name || !withName);
                $('[data-err-people]', root).hidden = picked.size > 0;

                if ((withName && !name) || picked.size === 0) return false;

                const fields = [...picked.keys()].map((id) => ['members[]', id]);
                if (withName) fields.unshift(['name', name]);
                if (groupId) { try { sessionStorage.setItem('sm-group-panel', location.pathname); } catch (_) {} }
                postForm(action, fields);
            },
        });

        dialog.querySelector('.modal').style.maxWidth = '560px';

        const list = $('[data-people]', dialog);
        const chips = $('[data-picked]', dialog);
        const search = $('#people-search', dialog);
        let people = [];
        let timer = null;

        const renderChips = () => {
            chips.hidden = picked.size === 0;
            chips.innerHTML = [...picked.values()].map((p) =>
                `<span class="pending-chip"><span>${escapeHtml(p.name)}</span>
                 <button type="button" data-unpick="${p.id}" aria-label="Remove ${escapeHtml(p.name)}">&#10005;</button></span>`
            ).join('');
            const ok = $('[data-modal-ok]', dialog);
            ok.textContent = picked.size ? `${confirmLabel} (${picked.size})` : confirmLabel;
        };

        const renderList = () => {
            list.innerHTML = people.length
                ? people.map((p) => `
                    <label class="picker-row ${picked.has(p.id) ? 'is-picked' : ''}">
                      <input type="checkbox" value="${p.id}" ${picked.has(p.id) ? 'checked' : ''}>
                      <span class="avatar ${p.online ? 'is-online' : ''}" style="width:34px;height:34px">
                        <img src="${escapeHtml(p.avatar)}" alt=""><i class="presence"></i>
                      </span>
                      <span class="pk-body">
                        <span class="pk-name">${escapeHtml(p.name)}</span>
                        <span class="pk-meta">${escapeHtml(p.meta)}</span>
                      </span>
                    </label>`).join('')
                : '<p class="small muted center" style="padding:1rem">No colleagues match.</p>';
        };

        const load = () => {
            const q = encodeURIComponent(search.value.trim());
            getJson(url(`/chat/people?q=${q}${groupId ? `&group=${groupId}` : ''}`))
                .then((data) => { people = data; renderList(); })
                .catch(() => { list.innerHTML = '<p class="small muted center" style="padding:1rem">Could not load colleagues.</p>'; });
        };

        search.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(load, 220);
        });

        list.addEventListener('change', (e) => {
            const box = e.target.closest('input[type=checkbox]');
            if (!box) return;
            const person = people.find((p) => String(p.id) === box.value);
            if (box.checked && person) picked.set(person.id, person);
            else picked.delete(Number(box.value));
            box.closest('.picker-row').classList.toggle('is-picked', box.checked);
            renderChips();
        });

        chips.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-unpick]');
            if (!btn) return;
            picked.delete(Number(btn.dataset.unpick));
            renderChips();
            renderList();
        });

        load();
    }

    document.addEventListener('click', (e) => {
        const create = e.target.closest('[data-new-group]');
        const add = e.target.closest('[data-add-members]');

        if (create) {
            e.preventDefault();
            openPeoplePicker({ title: 'New group', confirmLabel: 'Create group', action: create.dataset.newGroup, withName: true });
        } else if (add) {
            e.preventDefault();
            openPeoplePicker({ title: 'Add people', confirmLabel: 'Add', action: add.dataset.addMembers, groupId: add.dataset.group });
        }
    });

    /* ====================================================================
       Read receipts: keep the ticks on your own messages current from each
       chat poll, and list who read a group message when its ticks are tapped.
       ==================================================================== */

    const TICKS = '<svg class="ticks" width="18" height="12" viewBox="0 0 18 12" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path class="tick-1" d="m1.5 6.5 3.2 3.2L11 2.5"/><path class="tick-2" d="m7.6 9.4.3.3L14.2 2.5"/></svg>';

    function setReceipt(row, state, seen, total) {
        const r = $('[data-receipt]', row);
        if (!r) return;

        r.classList.remove('is-sent', 'is-partial', 'is-read');
        r.classList.add('is-' + state);

        const count = $('[data-receipt-count]', r);
        if (count) {
            count.textContent = seen && state !== 'read' ? seen : '';
            r.title = seen ? `Read by ${seen} of ${total}` : 'Sent';
        } else if (state === 'read') {
            r.title = 'Read';
        }
    }

    // Called by the chat poll with the latest receipt data.
    window.SM.updateReceipts = (scroll, data) => {
        $$('.bubble-row.is-mine', scroll).forEach((row) => {
            const id = parseInt(row.dataset.message, 10);

            if (data.read_upto && id <= data.read_upto) {
                setReceipt(row, 'read');
            } else if (Array.isArray(data.readers)) {
                const counted = data.readers.filter(([joined]) => joined < id);
                const seen = counted.filter(([, read]) => read >= id).length;
                const total = counted.length;
                setReceipt(row, total && seen >= total ? 'read' : seen ? 'partial' : 'sent', seen, total);
            }
        });
    };

    /* ====================================================================
       Message menu: the ⋮ button at the top right of a bubble opens Copy
       text, and on your own messages Edit, Info and Delete. The menu floats
       over the page so the chat's scroll area never clips it.
       ==================================================================== */

    const chatScroll = $('[data-chat-scroll]');

    // Swap a bubble for its re-rendered version (after edit, delete or a poll).
    window.SM.replaceBubble = (id, html) => {
        const old = chatScroll?.querySelector(`[data-message="${id}"]`);
        if (!old) return;
        const holder = document.createElement('div');
        holder.innerHTML = html.trim();
        if (holder.firstElementChild) old.replaceWith(holder.firstElementChild);
    };

    let msgMenu = null;

    const closeMsgMenu = () => {
        if (!msgMenu) return;
        msgMenu.opener.setAttribute('aria-expanded', 'false');
        msgMenu.opener.closest('.bubble-row')?.classList.remove('has-menu');
        msgMenu.el.remove();
        msgMenu = null;
    };

    const ICONS = {
        copy: '<rect x="8" y="8" width="11.5" height="11.5" rx="2"/><path d="M5.5 15.5h-.3A1.7 1.7 0 0 1 3.5 13.8V5.2a1.7 1.7 0 0 1 1.7-1.7h8.6a1.7 1.7 0 0 1 1.7 1.7v.3"/>',
        edit: '<path d="M4.5 19.5h3L19 8a2.1 2.1 0 0 0-3-3L4.5 16.5Z"/><path d="m15.2 5.8 3 3"/>',
        info: '<circle cx="12" cy="12" r="8.2"/><path d="M12 11v5"/><circle cx="12" cy="8" r=".6" fill="currentColor"/>',
        trash: '<path d="M4.8 7.5h14.4"/><path d="M9.5 7.5V5.8a1.3 1.3 0 0 1 1.3-1.3h2.4a1.3 1.3 0 0 1 1.3 1.3v1.7"/><path d="M6.6 7.5 7.4 19a1.3 1.3 0 0 0 1.3 1.2h6.6a1.3 1.3 0 0 0 1.3-1.2l.8-11.5"/>',
    };
    const icon = (name) => `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[name]}</svg>`;

    function openMsgMenu(btn) {
        const row = btn.closest('.bubble-row');
        const body = $('[data-body]', row)?.textContent ?? '';
        const items = [];

        if (body) items.push(['copy', 'Copy text']);
        if (btn.dataset.editUrl) items.push(['edit', 'Edit']);
        if (btn.dataset.infoUrl) items.push(['info', 'Info']);
        if (btn.dataset.deleteUrl) items.push(['trash', 'Delete']);
        if (!items.length) return;

        const el = document.createElement('div');
        el.className = 'msg-menu';
        el.setAttribute('role', 'menu');
        el.innerHTML = items.map(([name, label]) =>
            `<button type="button" role="menuitem" data-msg-action="${name}" class="${name === 'trash' ? 'danger' : ''}">${icon(name)}<span>${label}</span></button>`
        ).join('');
        document.body.appendChild(el);

        // Below the button, right-aligned; flip above when there is no room.
        const r = btn.getBoundingClientRect();
        const left = Math.max(8, Math.min(r.right - el.offsetWidth, window.innerWidth - el.offsetWidth - 8));
        const below = r.bottom + 4 + el.offsetHeight < window.innerHeight - 8;
        el.style.left = left + 'px';
        el.style.top = (below ? r.bottom + 4 : r.top - el.offsetHeight - 4) + 'px';

        btn.setAttribute('aria-expanded', 'true');
        row.classList.add('has-menu');
        msgMenu = { el, opener: btn, row, body };
        $('button', el).focus();
    }

    document.addEventListener('click', (e) => {
        const opener = e.target.closest('[data-msg-menu]');
        const action = e.target.closest('[data-msg-action]');

        if (action && msgMenu) {
            const { opener: btn, body } = msgMenu;
            closeMsgMenu();
            runMsgAction(action.dataset.msgAction, btn, body);
            return;
        }

        if (opener) {
            e.preventDefault();
            const same = msgMenu && msgMenu.opener === opener;
            closeMsgMenu();
            if (!same) openMsgMenu(opener);
            return;
        }

        if (msgMenu && !e.target.closest('.msg-menu')) closeMsgMenu();
    });

    document.addEventListener('keydown', (e) => {
        if (!msgMenu) return;

        if (e.key === 'Escape') {
            const btn = msgMenu.opener;
            closeMsgMenu();
            btn.focus();
        } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const items = $$('button', msgMenu.el);
            const i = items.indexOf(document.activeElement);
            items[(i + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length].focus();
        }
    });

    chatScroll?.addEventListener('scroll', closeMsgMenu);
    window.addEventListener('resize', closeMsgMenu);

    const sendJson = (endpoint, fields) => {
        const fd = new FormData();
        Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
        return post(endpoint, fd).then(async (r) => {
            const data = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Something went wrong.'));
            return data;
        });
    };

    function runMsgAction(name, btn, body) {
        if (name === 'copy') {
            navigator.clipboard?.writeText(body).catch(() => {});
            return;
        }

        if (name === 'edit') {
            const dialog = modal({
                title: 'Edit message',
                icon: '&#9999;&#65039;',
                body: `<div class="field" style="margin:0">
                         <label for="edit-body" class="sr-only">Message</label>
                         <textarea id="edit-body" rows="4" maxlength="4000">${escapeHtml(body)}</textarea>
                         <p class="error-text" data-edit-error hidden></p>
                       </div>`,
                confirmLabel: 'Save',
                focus: '#edit-body',
                onConfirm: (root) => {
                    const ok = $('[data-modal-ok]', root);
                    const error = $('[data-edit-error]', root);
                    ok.disabled = true;
                    sendJson(btn.dataset.editUrl, { body: $('#edit-body', root).value })
                        .then((data) => {
                            window.SM.replaceBubble(data.id, data.html);
                            root.remove();
                        })
                        .catch((err) => {
                            error.textContent = err.message;
                            error.hidden = false;
                            ok.disabled = false;
                        });
                    return false; // closed once saved
                },
            });
            const area = $('#edit-body', dialog);
            area.setSelectionRange(area.value.length, area.value.length);
            // Ctrl/Cmd + Enter saves.
            area.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) $('[data-modal-ok]', dialog).click();
            });
            return;
        }

        if (name === 'trash') {
            modal({
                title: 'Delete message?',
                icon: '&#9888;&#65039;',
                body: '<p>The message is deleted for everyone in this chat. Files uploaded with it are removed too.</p>',
                confirmLabel: 'Delete for everyone',
                danger: true,
                onConfirm: () => {
                    sendJson(btn.dataset.deleteUrl, { _method: 'DELETE' })
                        .then((data) => window.SM.replaceBubble(data.id, data.html))
                        .catch((err) => modal({ title: 'Could not delete', body: `<p>${escapeHtml(err.message)}</p>`, confirmLabel: 'OK', onConfirm: () => {} }));
                },
            });
            return;
        }

        if (name === 'info') showMessageInfo(btn.dataset.infoUrl);
    }

    function showMessageInfo(endpoint) {
        const people = (list, empty) => list.length
            ? `<ul class="readers-list">${list.map((p) => `
                <li><img src="${escapeHtml(p.avatar)}" alt="">
                    <div><b>${escapeHtml(p.name)}</b><span>${escapeHtml(p.meta)}</span></div></li>`).join('')}</ul>`
            : `<p class="small muted">${empty}</p>`;

        const line = (state, label, value) => `
            <div class="info-line"><span class="receipt is-${state}">${TICKS}</span>
              <div><b>${label}</b><span>${escapeHtml(value)}</span></div></div>`;

        const dialog = modal({
            title: 'Message info',
            icon: '&#8505;&#65039;',
            body: '<p class="small muted center" style="padding:1rem">Loading&hellip;</p>',
            confirmLabel: 'Done',
            onConfirm: () => {},
        });
        $('[data-modal-cancel]', dialog)?.remove();

        getJson(endpoint)
            .then((d) => {
                const head = line('sent', 'Sent', d.sent) + (d.edited ? line('sent', 'Edited', d.edited) : '');

                $('.modal-body', dialog).innerHTML = d.group
                    ? `<div class="readers-group">${head}</div>
                       <div class="readers-group">
                         <h4><span class="receipt is-read">${TICKS}</span> Read by ${d.readers.length}</h4>
                         ${people(d.readers, 'Nobody has read it yet.')}
                       </div>
                       <div class="readers-group">
                         <h4><span class="receipt is-sent">${TICKS}</span> Not read yet &middot; ${d.waiting.length}</h4>
                         ${people(d.waiting, 'Everyone has read it.')}
                       </div>`
                    : `<div class="readers-group">${head}
                         ${line(d.read ? 'read' : 'sent', d.read ? 'Read' : 'Not read yet', d.read || 'Not opened yet')}
                       </div>`;
            })
            .catch(() => {
                $('.modal-body', dialog).innerHTML = '<p class="small muted">Could not load this message’s details.</p>';
            });
    }
})();
