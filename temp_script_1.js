
    (function() {
        var NOTIF_URL = '"BLADE"';
        var CSRF = document.querySelector('meta[name="csrf-token"]').content;

        function fetchNotifications() {
            fetch(NOTIF_URL, { headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var alerts = data.alerts || [];
                    var banner = document.getElementById('notif-banner');
                    var navBadge = document.getElementById('nav-todo-badge');
                    var botBadge = document.getElementById('bottom-todo-badge');

                    if (!alerts.length) {
                        if (banner) banner.style.display = 'none';
                        if (navBadge) navBadge.style.display = 'none';
                        if (botBadge) botBadge.style.display = 'none';
                        return;
                    }

                    var count = alerts.length;
                    if (navBadge) { navBadge.textContent = count; navBadge.style.display = 'inline-block'; }
                    if (botBadge) { botBadge.textContent = count; botBadge.style.display = 'inline-block'; }

                    var urgent = alerts.filter(function(a) { return a.type === 'checkin_open' || a.type === 'checkin_soon'; });
                    if (urgent.length && banner) {
                        var first = urgent[0];
                        document.getElementById('notif-banner-icon').textContent = first.icon;
                        document.getElementById('notif-banner-text').textContent = first.title + ' — ' + first.body;
                        document.getElementById('notif-banner-link').href = first.url;
                        banner.style.display = 'block';
                    }
                })
                .catch(function() {});
        }
        setTimeout(fetchNotifications, 2000);

        if (window.Echo) {
            window.Echo.private('App.Models.User."BLADE"')
                .listen('StudentAlertsUpdated', function(e) {
                    fetchNotifications();
                });
        }
    })();
    