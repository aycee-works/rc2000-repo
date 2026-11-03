/* assets/js/dashboard.js - keeps the overview cards fresh without a page reload.
   The page is already rendered by PHP; this only updates the numbers every 60 s
   (and when the tab becomes visible again). */

(function () {
    'use strict';

    var REFRESH_MS = 60000;
    var busy = false;

    function refreshCards() {
        if (busy || document.hidden) { return; }
        busy = true;
        var cards = document.querySelectorAll('.stat-card');
        cards.forEach(function (c) { c.classList.add('is-refreshing'); });

        RC.api('admin/api/dashboard.php')
            .then(function (res) {
                if (!res.success || !res.data) { return; }
                Object.keys(res.data.cards).forEach(function (key) {
                    var el = document.querySelector('[data-stat="' + key + '"]');
                    if (el) { el.textContent = res.data.cards[key]; }
                });
            })
            .catch(function () { /* keep the numbers already shown */ })
            .then(function () {
                busy = false;
                cards.forEach(function (c) { c.classList.remove('is-refreshing'); });
            });
    }

    setInterval(refreshCards, REFRESH_MS);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { refreshCards(); }
    });
})();
