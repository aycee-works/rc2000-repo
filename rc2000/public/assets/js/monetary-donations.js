/* assets/js/monetary-donations.js - saves a verify / not-verified decision without leaving the page.
   Sends the form to admin/api/monetary-donations.php (PHP does the real validation;
   the checks here only give faster feedback). On success the page reloads so the
   table, summary cards and flash message all show the saved data. */

(function () {
    'use strict';

    var form = document.getElementById('money-review-form');
    if (!form) { return; }

    var message = document.getElementById('review-message');
    var buttons = form.querySelectorAll('button[data-decision]');
    var labels = {};
    buttons.forEach(function (b) { labels[b.getAttribute('data-decision')] = b.textContent; });

    function showMessage(text, type) {
        message.className = 'review-message flash flash-' + type;
        message.textContent = text;
        message.hidden = false;
    }

    function setBusy(busy, activeButton) {
        buttons.forEach(function (b) {
            b.disabled = busy;
            b.classList.toggle('is-loading', busy);
            b.textContent = (busy && b === activeButton) ? 'Saving…' : labels[b.getAttribute('data-decision')];
        });
    }

    function submitDecision(decision, button) {
        message.hidden = true;

        var amount = form.elements.verified_amount.value.replace(/[₱,\s]/g, '');
        var note = form.elements.note.value.trim();

        if (decision === 'verified' && !/^\d{1,10}(\.\d{1,2})?$/.test(amount)) {
            showMessage('Enter the verified amount as a number greater than 0, with up to 2 decimals.', 'error');
            return;
        }
        if (decision === 'not_verified' && note === '') {
            showMessage('Please record the reason in the verification note before marking this as not verified.', 'error');
            return;
        }

        var data = new FormData(form);
        data.append('decision', decision);

        setBusy(true, button);
        RC.api('admin/api/monetary-donations.php', { method: 'POST', body: data })
            .then(function (res) {
                if (!res.success) {
                    showMessage(res.message, 'error');
                    setBusy(false);
                } else if (res.data && res.data.changed === false) {
                    showMessage(res.message, 'info');
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
        b.addEventListener('click', function () {
            submitDecision(b.getAttribute('data-decision'), b);
        });
    });

    // Pressing Enter in a text box must not submit the form by itself
    form.addEventListener('submit', function (e) { e.preventDefault(); });
})();
