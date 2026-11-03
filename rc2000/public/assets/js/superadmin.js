/* assets/js/superadmin.js - small helpers for the Super Admin pages */
(function () {
    'use strict';

    // Filters that apply as soon as they change
    document.querySelectorAll('select[data-autosubmit]').forEach(function (sel) {
        sel.addEventListener('change', function () { sel.form.submit(); });
    });

    // Open / close <dialog> popups
    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-open-dialog]');
        if (opener) {
            var d = document.getElementById(opener.getAttribute('data-open-dialog'));
            if (d && d.showModal) { d.showModal(); var f = d.querySelector('input'); if (f) { f.focus(); } }
        }
        var closer = e.target.closest('[data-dialog-close]');
        if (closer) {
            var dlg = closer.closest('dialog');
            if (dlg) { dlg.close(); }
        }
    });

    // Copy a value (set-password link / temporary password)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-copy-target]');
        if (!btn) { return; }
        var input = document.getElementById(btn.getAttribute('data-copy-target'));
        if (!input) { return; }
        input.select();
        var done = function () { btn.textContent = 'Copied'; setTimeout(function () { btn.textContent = 'Copy'; }, 2000); };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(input.value).then(done, function () { document.execCommand('copy'); done(); });
        } else {
            document.execCommand('copy');
            done();
        }
    });
})();
