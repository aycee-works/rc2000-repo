/* assets/js/book-donations.js - saves the review panel without leaving the page.
   Sends the form to admin/api/book-donations.php (PHP does the real validation;
   the checks here only give faster feedback). On success the page reloads so the
   table, summary cards and flash message all show the saved data. */

(function () {
    'use strict';

    var form = document.getElementById('book-review-form');
    if (!form) { return; }

    var message = document.getElementById('review-message');
    var button = form.querySelector('button[type="submit"]');
    var buttonText = button.textContent;

    function showMessage(text, type) {
        message.className = 'review-message flash flash-' + type;
        message.textContent = text;
        message.hidden = false;
    }

    function setBusy(busy) {
        button.disabled = busy;
        button.classList.toggle('is-loading', busy);
        button.textContent = busy ? 'Saving…' : buttonText;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        message.hidden = true;

        // The quantity-received field was removed (the donations table has no such column);
        // validate it only if a form still provides it.
        if (form.elements.qty_received) {
            var qty = form.elements.qty_received.value.trim();
            if (qty !== '' && !/^\d+$/.test(qty)) {
                showMessage('Quantity received must be a whole number, 0 or more.', 'error');
                return;
            }
        }

        setBusy(true);
        RC.api('admin/api/book-donations.php', { method: 'POST', body: new FormData(form) })
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
    });
})();