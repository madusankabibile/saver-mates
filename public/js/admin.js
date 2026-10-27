/* ==========================================================================
   Admin panel: mobile navigation, bulk selection, chart tooltips.
   ========================================================================== */

(function () {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    /* Mobile sidebar ---------------------------------------------------- */

    const shell = $('[data-adm]');
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-adm-toggle]')) shell.classList.toggle('is-open');
        else if (e.target.closest('[data-adm-close]')) shell.classList.remove('is-open');
    });

    /* Bulk selection ----------------------------------------------------
       A form[data-bulk] holds row checkboxes (name="ids[]") and a bulk bar
       that appears once anything is ticked. Bulk buttons confirm first. */

    $$('[data-bulk]').forEach((form) => {
        const all = $('[data-bulk-all]', form);
        const boxes = $$('input[name="ids[]"]', form);
        const bar = $('[data-bulk-bar]', form);
        const count = $('[data-bulk-count]', form);

        const sync = () => {
            const picked = boxes.filter((b) => b.checked);
            boxes.forEach((b) => b.closest('tr')?.classList.toggle('is-picked', b.checked));
            bar.hidden = picked.length === 0;
            count.textContent = `${picked.length} selected`;
            if (all) {
                all.checked = picked.length > 0 && picked.length === boxes.length;
                all.indeterminate = picked.length > 0 && picked.length < boxes.length;
            }
        };

        all?.addEventListener('change', () => {
            boxes.forEach((b) => (b.checked = all.checked));
            sync();
        });
        boxes.forEach((b) => b.addEventListener('change', sync));
        $('[data-bulk-clear]', form)?.addEventListener('click', () => {
            boxes.forEach((b) => (b.checked = false));
            sync();
        });

        $$('[data-bulk-action]', form).forEach((btn) => {
            btn.addEventListener('click', () => {
                const n = boxes.filter((b) => b.checked).length;
                const run = () => {
                    const action = $('input[name="action"]', form);
                    if (action) action.value = btn.dataset.bulkAction;
                    form.submit();
                };

                if (!btn.dataset.bulkConfirm) return run();

                window.SM.modal({
                    title: btn.dataset.bulkConfirmTitle || 'Are you sure?',
                    icon: '&#9888;&#65039;',
                    body: `<p>${window.SM.escapeHtml(btn.dataset.bulkConfirm.replace('{n}', n))}</p>`,
                    confirmLabel: btn.textContent.trim(),
                    danger: true,
                    onConfirm: run,
                });
            });
        });

        sync();
    });

    /* Chart tooltips: each bar column carries data-tip lines. ------------ */

    let tip = null;

    document.addEventListener('mousemove', (e) => {
        const col = e.target.closest('[data-tip]');

        if (!col) {
            tip?.remove();
            tip = null;
            return;
        }

        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'chart-tip';
            document.body.appendChild(tip);
        }

        const [head, ...rows] = col.dataset.tip.split('|');
        tip.innerHTML = `<b>${window.SM.escapeHtml(head)}</b>` + rows.map((r) => window.SM.escapeHtml(r)).join('<br>');

        const x = Math.min(e.clientX + 14, window.innerWidth - tip.offsetWidth - 8);
        tip.style.left = x + 'px';
        tip.style.top = (e.clientY - tip.offsetHeight - 12) + 'px';
    });
})();
