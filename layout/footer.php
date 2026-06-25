<?php
/**
 * layout/footer.php — Podnožje stranice
 *
 * Zatvara HTML tagove koje je otvorio header.php.
 */
?>

</div><!-- /.main -->
</div><!-- /.body-wrap -->
</div><!-- /#app -->

<!-- JavaScript za tab prebacivanje -->
<script>
function switchTab(tabGroup, tabName) {
    // Deaktiviraj sve tabove u grupi
    document.querySelectorAll('[data-tab-group="' + tabGroup + '"] .tab').forEach(function(t) {
        t.classList.remove('active');
    });
    document.querySelectorAll('[data-tab-group="' + tabGroup + '"] .tab-content').forEach(function(c) {
        c.classList.remove('active');
    });
    // Aktiviraj izabrani tab
    document.querySelector('[data-tab-group="' + tabGroup + '"] .tab[data-tab="' + tabName + '"]').classList.add('active');
    document.querySelector('[data-tab-group="' + tabGroup + '"] .tab-content[data-tab="' + tabName + '"]').classList.add('active');
}
</script>

<!-- WebSocket klijent za instant notifikacije (Node servis u ws-server/) -->
<script>
(function() {
    var currentUserId = <?= (int) ($_SESSION['user_id'] ?? 0) ?>;
    if (!currentUserId) return;

    var ws = null;

    function connect() {
        ws = new WebSocket('ws://localhost:8090');

        ws.onopen = function() {
            ws.send(JSON.stringify({ type: 'auth', korisnik_id: currentUserId }));
        };

        ws.onmessage = function(event) {
            var msg;
            try {
                msg = JSON.parse(event.data);
            } catch (e) {
                return;
            }
            if (msg.type === 'nova_notifikacija') {
                azurirajBadge();
                prikaziToast(msg.sadrzaj);
            }
        };

        ws.onclose = function() {
            setTimeout(connect, 5000);
        };

        ws.onerror = function() {
            ws.close();
        };
    }

    function azurirajBadge() {
        document.querySelectorAll('.sidebar .nav-item').forEach(function(item) {
            if (!item.textContent.trim().startsWith('Obaveštenja')) return;
            var badge = item.querySelector('.nav-badge');
            if (badge) {
                badge.textContent = (parseInt(badge.textContent, 10) || 0) + 1;
            } else {
                badge = document.createElement('span');
                badge.className = 'nav-badge';
                badge.textContent = '1';
                item.appendChild(badge);
            }
        });
    }

    function prikaziToast(sadrzaj) {
        var toast = document.createElement('div');
        toast.style.position = 'fixed';
        toast.style.bottom = '20px';
        toast.style.right = '20px';
        toast.style.background = '#181818';
        toast.style.border = '1px solid #f5c518';
        toast.style.color = '#f0ede8';
        toast.style.padding = '12px 18px';
        toast.style.fontFamily = "'IBM Plex Mono', monospace";
        toast.style.fontSize = '12px';
        toast.style.zIndex = '9999';
        toast.style.maxWidth = '320px';
        toast.textContent = sadrzaj;
        document.body.appendChild(toast);
        setTimeout(function() { toast.remove(); }, 5000);
    }

    connect();
})();
</script>

</body>
</html>
