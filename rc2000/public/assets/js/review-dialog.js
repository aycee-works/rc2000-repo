/* assets/js/review-dialog.js - opens and closes the review popup (<dialog id="review-dialog">).
   PHP renders the popup only when the URL has ?id=... (for example from a table's Review link
   or from the Dashboard). This script opens it, and when it closes (X, Cancel, Esc or a click
   on the dark backdrop) it removes ?id= from the address bar so a refresh does not reopen it. */

(function () {
    'use strict';

    var dlg = document.getElementById('review-dialog');
    if (!dlg || typeof dlg.showModal !== 'function') { return; }

    dlg.querySelectorAll('[data-dialog-close]').forEach(function (b) {
        b.addEventListener('click', function () { dlg.close(); });
    });

    // Close on a backdrop click, but not when a text selection merely ends outside the popup
    var pressedOnBackdrop = false;
    dlg.addEventListener('mousedown', function (e) { pressedOnBackdrop = (e.target === dlg); });
    dlg.addEventListener('click', function (e) {
        if (e.target === dlg && pressedOnBackdrop) { dlg.close(); }
    });

    dlg.addEventListener('close', function () {
        try { window.history.replaceState(null, '', RC.reviewClosedUrl()); } catch (e) { /* ignore */ }
        document.querySelectorAll('tr.is-selected').forEach(function (tr) { tr.classList.remove('is-selected'); });
    });

    if (dlg.hasAttribute('data-autoopen')) { dlg.showModal(); }
})();
