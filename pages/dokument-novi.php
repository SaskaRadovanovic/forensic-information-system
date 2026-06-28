<?php
/**
 * pages/dokument-novi.php — Forma za kreiranje novog dokumenta
 *
 * Polja: naziv, predmet, tip dokumenta, opis, fajl.
 * Poluautomatsko predlaganje tagova na osnovu tipa i opisa.
 */

// Predmeti za select
$predmetiRes = $conn->query("SELECT id, naziv FROM predmet WHERE status = 'AKTIVAN' ORDER BY naziv");

// Svi tagovi iz baze (za predlaganje)
$sviTagoviRes = $conn->query("SELECT id, naziv, boja FROM tag ORDER BY naziv");
$sviTagoviZaPredlog = [];
while ($t = $sviTagoviRes->fetch_assoc()) {
    $sviTagoviZaPredlog[] = $t;
}
?>

<div class="page-breadcrumb">
    <a href="?page=dokumentacija" class="btn btn-ghost btn-sm">&larr; Dokumentacija</a>
</div>

<div class="page-eyebrow">Novi dokument</div>
<div class="page-title">Kreiraj dokument</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="?page=dokument-novi" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group">
                    <label>Naziv *</label>
                    <input type="text" name="naziv" required>
                </div>

                <div class="form-group">
                    <label>Predmet *</label>
                    <select name="predmet_id" required>
                        <option value="">— Izaberi predmet —</option>
                        <?php while ($p = $predmetiRes->fetch_assoc()): ?>
                        <option value="<?= $p['id'] ?>"><?= e($p['naziv']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tip dokumenta</label>
                    <select name="tip_dokumenta">
                        <option value="">— Izaberi tip —</option>
                        <?php foreach (['Izveštaj','Fotografija','Zapisnik','Veštačenje','Zbirni izveštaj','Ostalo'] as $t): ?>
                        <option value="<?= $t ?>"><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Nivo poverljivosti</label>
                    <select name="nivo_poverljivosti">
                        <option value="JAVNO">Javno</option>
                        <option value="INTERNO" selected>Interno</option>
                        <option value="POVERLJIVO">Poverljivo</option>
                        <option value="STROGO_POVERLJIVO">Strogo poverljivo</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Fajl</label>
                    <input type="file" name="fajl" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    <small style="color:var(--text-3);">Dozvoljeni formati: PDF, DOC, DOCX, JPG, PNG. Maks. 10MB.</small>
                </div>

                <div class="form-group full">
                    <label>Opis</label>
                    <textarea name="opis" rows="4"></textarea>
                </div>
            </div>

            <!-- Predloženi tagovi (poluautomatsko obelezavanje) -->
            <div id="predlozeni-tagovi-sekcija" class="form-group full" style="display:none;">
                <label>Predloženi tagovi</label>
                <p style="color:var(--text-3); font-size:12px; margin-bottom:8px;">Sistem predlaže tagove na osnovu tipa dokumenta i opisa. Označite koje želite da dodate.</p>
                <div id="predlozeni-tagovi-lista" style="display:flex; gap:6px; flex-wrap:wrap;"></div>
            </div>

            <div class="action-bar">
                <button type="submit" class="btn btn-primary">Kreiraj dokument</button>
                <a href="?page=dokumentacija" class="btn btn-ghost">Otkaži</a>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    // Tagovi iz baze
    var sviTagovi = <?= json_encode($sviTagoviZaPredlog, JSON_UNESCAPED_UNICODE) ?>;

    // Mapiranje tip → predlozeni nazivi tagova
    var mapaPoTipu = {
        'Veštačenje':      ['Vestacenje'],
        'Fotografija':     ['Foto-dokaz'],
        'Zapisnik':        ['Zapisnik'],
        'Izveštaj':        ['Izvestaj'],
        'Zbirni izveštaj': ['Izvestaj', 'Zbirni']
    };

    // Mapiranje ključna reč → naziv taga
    var mapaPoOpisu = {
        'pistolj': 'Vatreno oruzje', 'pištolj': 'Vatreno oruzje',
        'puška': 'Vatreno oruzje', 'puska': 'Vatreno oruzje',
        'oružje': 'Vatreno oruzje', 'oruzje': 'Vatreno oruzje',
        'kalibar': 'Vatreno oruzje', 'municija': 'Vatreno oruzje', 'metak': 'Vatreno oruzje',
        'nož': 'Hladno oruzje', 'noz': 'Hladno oruzje',
        'mačeta': 'Hladno oruzje', 'maceta': 'Hladno oruzje',
        'krv': 'Bioloski trag',
        'dnk': 'DNK', 'dns': 'DNK',
        'uzorak': 'Bioloski trag', 'bioloski': 'Bioloski trag', 'biološki': 'Bioloski trag',
        'hitno': 'Hitno', 'urgent': 'Hitno', 'zurno': 'Hitno', 'žurno': 'Hitno',
        'balistika': 'Balistika', 'balistič': 'Balistika',
        'finansij': 'Finansije', 'novac': 'Finansije', 'pranje': 'Finansije',
        'racun': 'Finansije', 'račun': 'Finansije'
    };

    var tipSelect = document.querySelector('select[name="tip_dokumenta"]');
    var opisTextarea = document.querySelector('textarea[name="opis"]');
    var sekcija = document.getElementById('predlozeni-tagovi-sekcija');
    var lista = document.getElementById('predlozeni-tagovi-lista');

    function nadjiPredlozene() {
        var predlozeniNazivi = {};

        // Po tipu
        var tip = tipSelect.value;
        if (mapaPoTipu[tip]) {
            mapaPoTipu[tip].forEach(function(n) { predlozeniNazivi[n] = true; });
        }

        // Po opisu
        var opis = (opisTextarea.value || '').toLowerCase();
        if (opis.length > 0) {
            for (var kljuc in mapaPoOpisu) {
                if (opis.indexOf(kljuc) !== -1) {
                    predlozeniNazivi[mapaPoOpisu[kljuc]] = true;
                }
            }
        }

        return Object.keys(predlozeniNazivi);
    }

    function azurirajPredloge() {
        var predlozeniNazivi = nadjiPredlozene();

        // Filtriraj samo tagove koji postoje u bazi
        var predlozeniTagovi = sviTagovi.filter(function(t) {
            return predlozeniNazivi.indexOf(t.naziv) !== -1;
        });

        lista.innerHTML = '';

        if (predlozeniTagovi.length === 0) {
            sekcija.style.display = 'none';
            return;
        }

        sekcija.style.display = '';

        predlozeniTagovi.forEach(function(tag) {
            var label = document.createElement('label');
            label.style.cssText = 'display:inline-flex; align-items:center; gap:4px; padding:4px 10px; border-radius:6px; cursor:pointer; font-size:13px; border:1px solid ' + tag.boja + '44; background:' + tag.boja + '11; color:' + tag.boja + ';';

            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'predlozeni_tagovi[]';
            cb.value = tag.id;
            cb.checked = true;

            label.appendChild(cb);
            label.appendChild(document.createTextNode(' ' + tag.naziv));
            lista.appendChild(label);
        });
    }

    tipSelect.addEventListener('change', azurirajPredloge);
    opisTextarea.addEventListener('input', azurirajPredloge);

    // Inicijalno pokretanje
    azurirajPredloge();
})();
</script>
