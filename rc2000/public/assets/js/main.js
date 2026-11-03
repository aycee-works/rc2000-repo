/* assets/js/main.js - shared behavior loaded on every admin page */

(function () {
    'use strict';

    // ---- Helpers other scripts can reuse (window.RC) ----
    var RC = window.RC = window.RC || {};

    RC.csrfToken = function () {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    };

    RC.baseUrl = function () {
        var m = document.querySelector('meta[name="app-base"]');
        return m ? m.getAttribute('content') : '';
    };

    /**
     * POST/GET to a JSON endpoint with the CSRF header attached.
     * Returns a Promise that resolves to the parsed JSON ({success, message, data}).
     * Usage: RC.api('admin/api/dashboard.php').then(...)
     */
    RC.api = function (path, options) {
        options = options || {};
        var headers = { 'X-CSRF-Token': RC.csrfToken(), 'Accept': 'application/json' };
        var init = { method: options.method || 'GET', headers: headers, credentials: 'same-origin' };
        if (options.body instanceof FormData) {
            init.body = options.body;
        } else if (options.body) {
            headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(options.body);
        }
        return fetch(RC.baseUrl() + '/' + path.replace(/^\//, ''), init).then(function (res) {
            if (res.status === 401) {
                window.location.href = RC.baseUrl() + '/admin/login.php';
                return Promise.reject(new Error('Not authenticated'));
            }
            return res.json().catch(function () {
                return { success: false, message: 'Unexpected server response.', data: null };
            });
        });
    };

    /** Current page URL without ?id= and #hash (used when a review popup closes or after a save). */
    RC.reviewClosedUrl = function () {
        var u = new URL(window.location.href);
        u.searchParams.delete('id');
        u.hash = '';
        return u.toString();
    };

    // ---- Dismiss flash messages; auto-hide success ones ----
    document.addEventListener('click', function (e) {
        var close = e.target.closest('.flash-close');
        if (close) {
            close.closest('.flash').remove();
        }
    });
    document.querySelectorAll('.flash-success').forEach(function (el) {
        setTimeout(function () { el.remove(); }, 6000);
    });

    // ---- "Forgot password?" notice on the login page ----
    var forgotLink = document.getElementById('forgot-link');
    var forgotNotice = document.getElementById('forgot-notice');
    if (forgotLink && forgotNotice) {
        forgotLink.addEventListener('click', function (e) {
            e.preventDefault();
            forgotNotice.hidden = !forgotNotice.hidden;
        });
    }

    // ---- Confirm dialogs: <form data-confirm="Delete this?"> or <button data-confirm="..."> ----
    document.addEventListener('submit', function (e) {
        var msg = e.target.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
        }
    });
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-confirm], a[data-confirm]');
        if (btn && !window.confirm(btn.getAttribute('data-confirm'))) {
            e.preventDefault();
        }
    });
})();
