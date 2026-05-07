(function () {
    'use strict';

    if (!('Notification' in window)) return;
    if (Notification.permission === 'denied') return;

    // Base path injected by main layout via window.__BASE__ or derived from existing meta tag
    var BASE = (function () {
        var m = document.querySelector('meta[name="csrf-token"]');
        if (!m) return null; // not logged in
        // Use the script tag URL to derive base
        var scripts = document.querySelectorAll('script[src]');
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].src;
            var idx = src.indexOf('/public/js/notifications.js');
            if (idx > -1) return src.substring(0, idx);
        }
        return '';
    })();

    if (BASE === null) return; // not logged in

    function checkAndNotify() {
        fetch(BASE + '/notifications/status', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.reminderEnabled || !data.dailyGoal) return;
                if (data.wordsToday >= data.dailyGoal) return;

                var parts = (data.reminderTime || '20:00').split(':');
                var rHour = parseInt(parts[0], 10);
                var rMin  = parseInt(parts[1] || '0', 10);
                var now   = new Date();
                if (now.getHours() < rHour || (now.getHours() === rHour && now.getMinutes() < rMin)) return;

                var today    = now.toISOString().slice(0, 10);
                var shownKey = 'notif_reminder_' + today;
                if (localStorage.getItem(shownKey)) return;
                localStorage.setItem(shownKey, '1');

                if (Notification.permission === 'granted') {
                    showPush(data);
                } else {
                    showBanner(data);
                }
            })
            .catch(function () { /* silent */ });
    }

    function showPush(data) {
        var n = new Notification('Rappel d\u2019\u00e9criture', {
            body: data.wordsToday + '\u00a0/\u00a0' + data.dailyGoal + ' mots \u00e9crits aujourd\u2019hui. Continue !',
            icon: BASE + '/public/icons/icon-192.png',
            tag: 'writing-reminder'
        });
        n.onclick = function () { window.focus(); n.close(); };
    }

    function showBanner(data) {
        if (document.getElementById('notif-reminder-banner')) return;

        var banner = document.createElement('div');
        banner.id = 'notif-reminder-banner';
        banner.innerHTML =
            '<i class="fas fa-pencil-alt" style="margin-right:8px"></i>' +
            '<strong>Rappel\u00a0:</strong> ' +
            data.wordsToday + '\u00a0/\u00a0' + data.dailyGoal + ' mots aujourd\u2019hui.' +
            ' <a href="' + BASE + '/stats" class="notif-banner-link">Voir les stats</a>' +
            '<button class="notif-banner-close" aria-label="Fermer">&times;</button>';

        document.body.appendChild(banner);

        requestAnimationFrame(function () {
            banner.classList.add('notif-banner--in');
        });

        banner.querySelector('.notif-banner-close').addEventListener('click', function () {
            hideBanner(banner);
        });

        setTimeout(function () { hideBanner(banner); }, 12000);
    }

    function hideBanner(banner) {
        banner.classList.remove('notif-banner--in');
        setTimeout(function () { if (banner.parentNode) banner.parentNode.removeChild(banner); }, 400);
    }

    // Delay check so it doesn't compete with page load
    setTimeout(checkAndNotify, 3000);
})();
