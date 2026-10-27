/* ==========================================================================
   Chunked uploader. PHP on the server accepts 40 MB per request, movies are
   several GB, so files are sent in 8 MB pieces to /uploads/chunks and then
   finished with one normal request naming the upload_id.
   ========================================================================== */

(function () {
    'use strict';

    const CHUNK = 8 * 1024 * 1024;
    const RETRIES = 4;

    /* crypto.randomUUID() needs HTTPS; intranet pages are often plain http. */
    function uuid() {
        const b = crypto.getRandomValues(new Uint8Array(16));
        b[6] = (b[6] & 0x0f) | 0x40;
        b[8] = (b[8] & 0x3f) | 0x80;
        const h = Array.from(b, (x) => x.toString(16).padStart(2, '0')).join('');
        return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
    }

    function sendChunk(endpoint, form, onProgress, job) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            job.xhr = xhr;
            xhr.open('POST', endpoint);
            xhr.setRequestHeader('X-CSRF-TOKEN', SM.csrf());
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.onprogress = (e) => e.lengthComputable && onProgress(e.loaded);
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) return resolve();
                let message = 'The server rejected the upload (' + xhr.status + ').';
                try {
                    const body = JSON.parse(xhr.responseText);
                    message = body.message || Object.values(body.errors || {})[0]?.[0] || message;
                } catch (_) { /* not JSON */ }
                const err = new Error(message);
                err.status = xhr.status;
                reject(err);
            };
            xhr.onerror = () => reject(new Error('Network error'));
            xhr.onabort = () => reject(Object.assign(new Error('Upload cancelled'), { cancelled: true }));
            xhr.send(form);
        });
    }

    /**
     * Upload a File in chunks.
     * Returns a job: { promise, cancel() }. promise resolves to { uploadId, totalChunks }.
     * onProgress receives { sent, total, percent, speed (bytes/s), eta (s) }.
     */
    function upload(file, { endpoint, onProgress } = {}) {
        const job = { cancelled: false, xhr: null };
        const uploadId = uuid();
        const totalChunks = Math.max(1, Math.ceil(file.size / CHUNK));
        const started = performance.now();

        job.cancel = () => {
            job.cancelled = true;
            job.xhr?.abort();
        };

        const report = (sent) => {
            if (!onProgress) return;
            const elapsed = (performance.now() - started) / 1000;
            const speed = elapsed > 0.5 ? sent / elapsed : 0;
            onProgress({
                sent,
                total: file.size,
                percent: file.size ? Math.min(100, (sent / file.size) * 100) : 100,
                speed,
                eta: speed ? (file.size - sent) / speed : null,
            });
        };

        job.promise = (async () => {
            for (let index = 0; index < totalChunks; index++) {
                const start = index * CHUNK;
                const blob = file.slice(start, Math.min(file.size, start + CHUNK));

                for (let attempt = 0; ; attempt++) {
                    if (job.cancelled) throw Object.assign(new Error('Upload cancelled'), { cancelled: true });

                    const form = new FormData();
                    form.append('upload_id', uploadId);
                    form.append('index', index);
                    form.append('chunk', blob, 'chunk');

                    try {
                        await sendChunk(endpoint, form, (loaded) => report(start + loaded), job);
                        break;
                    } catch (err) {
                        // Validation errors will not fix themselves; network blips might.
                        if (err.cancelled || err.status === 422 || err.status === 419 || attempt >= RETRIES) throw err;
                        await new Promise((r) => setTimeout(r, 800 * (attempt + 1)));
                    }
                }

                report(Math.min(file.size, start + CHUNK));
            }

            return { uploadId, totalChunks };
        })();

        return job;
    }

    function formatBytes(n) {
        if (n < 1024) return n + ' B';
        const units = ['KB', 'MB', 'GB', 'TB'];
        let i = -1;
        do { n /= 1024; i++; } while (n >= 1024 && i < units.length - 1);
        return (n >= 10 ? Math.round(n) : n.toFixed(1)) + ' ' + units[i];
    }

    function formatEta(s) {
        if (s == null || !isFinite(s)) return '';
        s = Math.round(s);
        if (s < 60) return s + 's left';
        const m = Math.floor(s / 60);
        if (m < 60) return m + ' min left';
        return Math.floor(m / 60) + ' h ' + (m % 60) + ' min left';
    }

    window.ChunkUploader = { upload, formatBytes, formatEta, CHUNK };
})();
