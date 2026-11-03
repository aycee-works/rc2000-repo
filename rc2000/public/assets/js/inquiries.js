/* assets/js/inquiries.js - Save response / Mark resolved / Close inquiry without leaving the page.
   Sends the form to admin/api/inquiries.php. PHP enforces every rule (for example, an
   inquiry cannot be resolved until a response is saved); the checks here only give
   faster feedback. On success the page reloads so the table, history and flash
   message all show the saved data. */

(function () {
    'use strict';

    var form = document.getElementById('inquiry-form');
    if (!form) { return; }

    var message = document.getElementById('review-message');
    var buttons = form.querySelectorAll('button[data-action]');
    var statusSelect = form.elements.status;
    var responseBox = form.elements.response;
    var labels = {};
    buttons.forEach(function (b) { labels[b.getAttribute('data-action')] = b.textContent; });

    function showMessage(text, type) {
        message.className = 'review-message flash flash-' + type;
        message.textContent = text;
        message.hidden = false;
    }

    function setBusy(busy, active) {
        buttons.forEach(function (b) {
            // keep buttons that were disabled by PHP (e.g. already resolved) disabled
            if (busy) { b.setAttribute('data-was-disabled', b.disabled ? '1' : '0'); b.disabled = true; }
            else { b.disabled = b.getAttribute('data-was-disabled') === '1'; }
            b.classList.toggle('is-loading', busy);
            b.textContent = (busy && b === active) ? 'Saving…' : labels[b.getAttribute('data-action')];
        });
    }

    // On a closed inquiry the reply box stays locked until the status is changed to Open
    if (form.getAttribute('data-status') === 'closed') {
        statusSelect.addEventListener('change', function () {
            responseBox.disabled = statusSelect.value === 'closed';
        });
    }

    function submitAction(action, button) {
        message.hidden = true;
        var typed = responseBox.value.trim();

        if (action === 'resolve' && typed === '' && form.getAttribute('data-has-response') !== '1') {
            showMessage('Write and save a response to the submitter before marking this inquiry as resolved.', 'error');
            return;
        }
        if (action === 'close' &&
            !window.confirm('Close this inquiry? Its messages and responses are kept, and you can reopen it later.')) {
            return;
        }

        var data = new FormData(form);
        data.append('action', action);

        setBusy(true, button);
        RC.api('admin/api/inquiries.php', { method: 'POST', body: data })
            .then(function (res) {
                if (!res.success) {
                    showMessage(res.message, 'error');
                    setBusy(false);
                } else {
                    window.location.href = RC.reviewClosedUrl();   // flash message is shown after reload
                }
            })
            .catch(function () {
                showMessage('Could not reach the server. Please try again.', 'error');
                setBusy(false);
            });
    }

    buttons.forEach(function (b) {
        b.addEventListener('click', function () { submitAction(b.getAttribute('data-action'), b); });
    });

    form.addEventListener('submit', function (e) { e.preventDefault(); });
})();
