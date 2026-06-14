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

</body>
</html>
