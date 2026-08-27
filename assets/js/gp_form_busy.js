/* GenPay — one loading-state implementation for every action that moves money.
 *
 * Before this file the pattern existed in exactly two places (student/transfer.php
 * and parent/allowance.php) and was copy-pasted between them, while four other
 * money-moving flows had no loading state at all — a plain POST form could be
 * submitted twice with two clicks.
 *
 * Deliberately text-only: no spinner. The theme carries a global
 * prefers-reduced-motion guard that freezes animation, and a frozen spinner reads
 * as a hung page. The label swap plus the disabled state says the same thing and
 * says it in every motion setting.
 *
 * Usage
 *   Plain POST form:  <form method="POST" data-busy="Submitting…">
 *                     (any client-side validation that calls preventDefault is
 *                      respected — the button is left alone.)
 *   Async handler:    if (!gpBusy(btn, 'Sending…')) return;   // already in flight
 *                     ... await ...
 *                     gpBusyDone(btn);                        // on EVERY failure path
 */
(function () {
    'use strict';

    var DEFAULT = 'Processing…';

    /* Returns false if the button is already busy — use that to swallow
       double-clicks at the call site rather than firing a second request. */
    window.gpBusy = function (btn, text) {
        if (!btn || btn.dataset.gpBusy === '1') { return false; }
        btn.dataset.gpLabel = btn.innerHTML;
        btn.dataset.gpBusy = '1';
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.textContent = text || DEFAULT;
        return true;
    };

    window.gpBusyDone = function (btn) {
        if (!btn || btn.dataset.gpBusy !== '1') { return; }
        btn.innerHTML = btn.dataset.gpLabel;
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
        delete btn.dataset.gpBusy;
        delete btn.dataset.gpLabel;
    };

    /* Plain POST forms. The page navigates away on success, so there is nothing
       to restore — but a form-level validation handler that calls preventDefault
       must not leave the button stuck. Form listeners run at target, this runs at
       document, so defaultPrevented is already accurate by the time we see it. */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.matches || !form.matches('form[data-busy]')) { return; }
        if (e.defaultPrevented) { return; }
        gpBusy(form.querySelector('[type="submit"]'), form.dataset.busy);
    });
}());
