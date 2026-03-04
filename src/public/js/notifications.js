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

        injectBannerStyles();

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

    function injectBannerStyles() {
        if (document.getElementById('notif-banner-styles')) return;
        var style = document.createElement('style');
        style.id = 'notif-banner-styles';
        style.textContent = [
            '#notif-reminder-banner {',
            '  position:fixed; bottom:20px; left:50%; transform:translateX(-50%) translateY(20px);',
            '  background:var(--card-bg,#fff); border:1px solid var(--border-color,#ddd);',
            '  border-left:4px solid #3f51b5; border-radius:8px; padding:12px 16px;',
            '  box-shadow:0 4px 20px rgba(0,0,0,.15); z-index:9999;',
            '  display:flex; align-items:center; gap:8px; font-size:.9rem;',
            '  color:var(--text-main,#333); opacity:0; transition:opacity .35s,transform .35s;',
            '  min-width:280px; max-width:90vw;',
            '}',
            '#notif-reminder-banner.notif-banner--in { opacity:1; transform:translateX(-50%) translateY(0); }',
            '.notif-banner-link { color:var(--link-color,#3f51b5); font-weight:600; margin:0 8px; text-decoration:none; }',
            '.notif-banner-link:hover { text-decoration:underline; }',
            '.notif-banner-close {',
            '  background:none; border:none; cursor:pointer; font-size:1.1rem;',
            '  color:var(--text-muted,#888); padding:0 2px; margin-left:auto; line-height:1;',
            '  border-radius:4px;',
            '}',
            '.notif-banner-close:hover { color:#ef4444; }'
        ].join('\n');
        document.head.appendChild(style);
    }

    // Delay check so it doesn't compete with page load
    setTimeout(checkAndNotify, 3000);
})();
