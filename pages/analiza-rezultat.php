<?php
/**
 * pages/analiza-rezultat.php — Unos i prikaz rezultata analize
 *
 * VESTAK + U_TOKU + bez rezultata → forma za unos
 * ISTRAZITELJ/ADMINISTRATOR + rezultat neVerifikovan → dugme verifikacije
 * Svi → read-only prikaz ako je verifikovan
 * Prikazuje i istoriju statusa analize.
 */

$zahtevId = (int)($_GET['id'] ?? 0);
$userId   = $_SESSION['user_id'];
$uloga    = $_SESSION['uloga'];

// ─── Auto-read: tiho obeleži pročitanim sve notifikacije za ovaj zahtev ────
$conn->begin_transaction();
$stmtRead = $conn->prepare("UPDATE obavestenje SET procitano = 1 WHERE zahtev_id = ? AND korisnik_id = ? AND procitano = 0");
$stmtRead->bind_param('ii', $zahtevId, $userId);
$stmtRead->execute();
$brojOznacenihProcitanim = $stmtRead->affected_rows;
$stmtRead->close();
$conn->commit();

// Odmah umanji badge u sidebaru (header.php je već renderovan sa starim brojem)
if ($brojOznacenihProcitanim > 0) {
    echo "<script>(function(){\n"
       . "    document.querySelectorAll('.sidebar .nav-item').forEach(function(item){\n"
       . "        if (!item.textContent.trim().startsWith('Obaveštenja')) return;\n"
       . "        var badge = item.querySelector('.nav-badge');\n"
       . "        if (!badge) return;\n"
       . "        var nova = (parseInt(badge.textContent, 10) || 0) - {$brojOznacenihProcitanim};\n"
       . "        if (nova > 0) { badge.textContent = nova; } else { badge.remove(); }\n"
       . "    });\n"
       . "})();</script>";
}

// ─── Učitavanje zahteva ────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT z.*, d.sifra_dokaza, d.naziv AS dokaz_naziv,
           p.naziv AS predmet_naziv
    FROM zahtev_za_analizu z
    JOIN dokaz d ON d.id = z.dokaz_id
    JOIN predmet p ON p.id = z.predmet_id
    WHERE z.id = ?
");
$stmt->bind_param('i', $zahtevId);
$stmt->execute();
$zahtev = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$zahtev) {
    flashError('Zahtev za analizu nije pronađen.');
    header('Location: ?page=analize');
    exit;
}

// Provera prava pristupa
$mozePristupiti = false;
if ($uloga === 'VESTAK' && $zahtev['vestak_id'] == $userId) {
    $mozePristupiti = true;
}
if (in_array($uloga, ['ISTRAZITELJ', 'ADMINISTRATOR'])) {
    $mozePristupiti = true;
}

if (!$mozePristupiti) {
    flashError('Nemate pristup ovoj stranici.');
    header('Location: ?page=analize');
    exit;
}

// ─── Rezultat analize ──────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT r.*, k.ime, k.prezime FROM rezultat_analize r JOIN korisnik k ON k.id = r.uneao_id WHERE r.zahtev_id = ?");
$stmt->bind_param('i', $zahtevId);
$stmt->execute();
$rezultat = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ─── Istorija statusa ──────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT isa.*, k.ime, k.prezime
    FROM istorija_statusa_analize isa
    JOIN korisnik k ON k.id = isa.inicirao_id
    WHERE isa.zahtev_id = ?
    ORDER BY isa.datum_vreme DESC
");
$stmt->bind_param('i', $zahtevId);
$stmt->execute();
$istorijaStatusa = $stmt->get_result();
$stmt->close();

$status = $zahtev['status'];
$mozeUnositi = $uloga === 'VESTAK' && $zahtev['vestak_id'] == $userId && $status === 'U_TOKU' && !$rezultat;
$mozeVerifikovati = in_array($uloga, ['ISTRAZITELJ', 'ADMINISTRATOR']) && $rezultat && !$rezultat['verifikovan'];
?>

<div class="page-breadcrumb">
    <a href="?page=analiza-detalji&id=<?= $zahtevId ?>" class="btn btn-ghost btn-sm">&larr; Nazad na detalje</a>
</div>

<div class="page-eyebrow">Rezultat analize</div>
<div class="page-title">Analiza #<?= $zahtevId ?> — <?= e($zahtev['sifra_dokaza']) ?></div>

<!-- Info pregled -->
<div class="info-grid" style="margin-bottom:20px;">
    <div class="info-item">
        <div class="info-label">Tip analize</div>
        <div class="info-value"><?= e(tipAnalizeLabel($zahtev['tip_analize'])) ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Predmet</div>
        <div class="info-value"><?= e($zahtev['predmet_naziv']) ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Status</div>
        <div class="info-value"><span class="badge <?= badgeClass($status) ?>"><?= e(badgeLabel($status)) ?></span></div>
    </div>
    <?php if ($zahtev['opis']): ?>
    <div class="info-item" style="grid-column:1/-1;">
        <div class="info-label">Opis zahteva</div>
        <div class="info-value"><?= e($zahtev['opis']) ?></div>
    </div>
    <?php endif; ?>
</div>

<?php
// Kratki parametarski inputi koje veštak popunjava (vrsta dokaza, uzorka, itd.)
$poljaPoTipu = [
    'BALISTICKA' => [
        ['kljuc' => 'vrstaOruzja',    'naziv' => 'Vrsta oružja',            'placeholder' => 'npr. pištolj, puška, sačmarica...'],
        ['kljuc' => 'kalibar',        'naziv' => 'Kalibar',                 'placeholder' => 'npr. 9mm Parabellum, .38 Special...'],
        ['kljuc' => 'brojProjektila', 'naziv' => 'Broj projektila / čaura', 'placeholder' => 'npr. 3 projektila, 2 čaure...'],
    ],
    'DNK' => [
        ['kljuc' => 'vrstaUzorka',   'naziv' => 'Vrsta uzorka',            'placeholder' => 'npr. bris, krv, kosa sa korenom...'],
        ['kljuc' => 'brojUzoraka',   'naziv' => 'Broj uzoraka',            'placeholder' => 'npr. 1 referentni + 3 tragovna uzorka'],
        ['kljuc' => 'metodaAnalize', 'naziv' => 'Metoda / baza poređenja', 'placeholder' => 'npr. STR profil, Y-STR, mtDNK, CODIS...'],
    ],
    'DIGITALNA' => [
        ['kljuc' => 'vrstaUredjaja',    'naziv' => 'Vrsta uređaja',  'placeholder' => 'npr. mobilni telefon, laptop, USB...'],
        ['kljuc' => 'operativniSistem', 'naziv' => 'OS / platforma', 'placeholder' => 'npr. Windows 10, Android 12, iOS...'],
        ['kljuc' => 'stanje',           'naziv' => 'Stanje uređaja', 'placeholder' => 'npr. funkcionalan, oštećen, zaključan...'],
    ],
    'HEMIJSKA' => [
        ['kljuc' => 'vrstaUzorka',   'naziv' => 'Vrsta uzorka',            'placeholder' => 'npr. prašak, tečnost, gas, eksploziv...'],
        ['kljuc' => 'kolicina',      'naziv' => 'Količina / zapremina',    'placeholder' => 'npr. ~2g, 50ml, nepoznato...'],
        ['kljuc' => 'metodaAnalize', 'naziv' => 'Primenjena metodologija', 'placeholder' => 'npr. GC-MS, HPLC, ATR-FTIR, XRF...'],
    ],
    'TOKSIKOLOSKA' => [
        ['kljuc' => 'vrstaUzorka',           'naziv' => 'Vrsta biološkog uzorka',  'placeholder' => 'npr. venozna krv, urin, tkivo jetre...'],
        ['kljuc' => 'supstanceZaTestiranje', 'naziv' => 'Grupe supstanci za test', 'placeholder' => 'npr. opiati, benzodiazepini, alkohol...'],
    ],
    'DOKUMENTOLOSKA' => [
        ['kljuc' => 'vrstaDokumenta', 'naziv' => 'Vrsta dokumenta', 'placeholder' => 'npr. lična karta, ugovor, ček, diploma...'],
        ['kljuc' => 'sumnjaNa',       'naziv' => 'Sumnja na',        'placeholder' => 'npr. falsifikovanje potpisa, lažan pečat...'],
    ],
    'DRUGA' => [
        ['kljuc' => 'opisPredmeta', 'naziv' => 'Opis predmeta analize', 'placeholder' => 'Opišite šta je analizirano...'],
    ],
];
$tipAnalize  = $zahtev['tip_analize'];
$poljaZaTip  = $poljaPoTipu[$tipAnalize] ?? [];

// Sekcije po tipu analize — preslikano iz filip projekta
$sekcijePoTipu = [
    'BALISTICKA' => [
        ['kljuc' => 'identifikacijaOruzja',  'naziv' => 'Identifikacija oružja',        'placeholder' => 'Vrsta, marka, model, serijski broj, karakteristike...', 'obavezno' => true],
        ['kljuc' => 'balistickaPodudarnost', 'naziv' => 'Balistička podudarnost',       'placeholder' => 'Rezultati poređenja projektila / čaura sa poznatim uzorkom...'],
        ['kljuc' => 'tragoviBaruta',         'naziv' => 'Tragovi baruta / GSR',          'placeholder' => 'Prisustvo ili odsustvo gunshot residue, lokacija tragova...'],
        ['kljuc' => 'metodologija',          'naziv' => 'Primenjena metodologija',       'placeholder' => 'Instrumenti i metode korišćene u analizi...'],
        ['kljuc' => 'zakljucak',             'naziv' => 'Zaključak',                     'placeholder' => 'Forenzički zaključak analize...', 'obavezno' => true],
    ],
    'DNK' => [
        ['kljuc' => 'profilDNK',         'naziv' => 'DNK profil',                  'placeholder' => 'Dobijeni aleli po lokusima, tip profila (STR/Y-STR/mtDNK)...', 'obavezno' => true],
        ['kljuc' => 'podudarnost',       'naziv' => 'Podudarnost sa uzorcima',     'placeholder' => 'Rezultat poređenja sa referentnim uzorcima ili bazom podataka...'],
        ['kljuc' => 'statistickiIskaz',  'naziv' => 'Statistički iskaz',           'placeholder' => 'LR vrednost, RMP, ili procenat verovatnoće podudarnosti...'],
        ['kljuc' => 'zakljucak',         'naziv' => 'Zaključak',                   'placeholder' => 'Forenzički zaključak DNK analize...', 'obavezno' => true],
    ],
    'DIGITALNA' => [
        ['kljuc' => 'pronadjeniArtifakti',  'naziv' => 'Pronađeni artefakti',        'placeholder' => 'Fajlovi, komunikacije, fotografije, lozinke, nalozi...', 'obavezno' => true],
        ['kljuc' => 'rekuperisaniPodaci',   'naziv' => 'Rekuperisani podaci',         'placeholder' => 'Obrisani fajlovi ili podaci koji su uspešno oporavljeni...'],
        ['kljuc' => 'hronologija',          'naziv' => 'Digitalna hronologija',       'placeholder' => 'Vremenski sled aktivnosti relevantnih za predmet...'],
        ['kljuc' => 'metapodaci',           'naziv' => 'Analiza metapodataka',        'placeholder' => 'EXIF podaci, log fajlovi, sistemski događaji...'],
        ['kljuc' => 'zakljucak',            'naziv' => 'Zaključak',                   'placeholder' => 'Forenzički zaključak digitalne analize...', 'obavezno' => true],
    ],
    'HEMIJSKA' => [
        ['kljuc' => 'identifikovaneSupstance', 'naziv' => 'Identifikovane supstance',      'placeholder' => 'Hemijski sastav, identifikovana jedinjenja, čistoća...', 'obavezno' => true],
        ['kljuc' => 'rezultatiTestova',        'naziv' => 'Rezultati analitičkih testova', 'placeholder' => 'Numerički rezultati, hromatogrami, spektrometrijski podaci...'],
        ['kljuc' => 'metodologija',            'naziv' => 'Primenjena metodologija',       'placeholder' => 'GC-MS, HPLC, FTIR, XRF — opis korišćenih instrumenata...'],
        ['kljuc' => 'zakljucak',               'naziv' => 'Zaključak',                     'placeholder' => 'Forenzički zaključak hemijske analize...', 'obavezno' => true],
    ],
    'TOKSIKOLOSKA' => [
        ['kljuc' => 'identifikovaniToksini', 'naziv' => 'Identifikovane supstance',         'placeholder' => 'Naziv supstance, hemijska grupa, nomenklatura...', 'obavezno' => true],
        ['kljuc' => 'koncentracije',         'naziv' => 'Koncentracije',                    'placeholder' => 'Izmerene koncentracije u biološkim uzorcima (mg/L, ng/mL)...'],
        ['kljuc' => 'medicinskiUticaj',      'naziv' => 'Toksikološki / medicinski uticaj', 'placeholder' => 'Procena uticaja na organizam pri datim koncentracijama...'],
        ['kljuc' => 'zakljucak',             'naziv' => 'Zaključak',                        'placeholder' => 'Forenzički zaključak toksikološke analize...', 'obavezno' => true],
    ],
    'DOKUMENTOLOSKA' => [
        ['kljuc' => 'autenticnost',       'naziv' => 'Ocena autentičnosti',          'placeholder' => 'Da li je dokument autentičan, sa objašnjenjem osnova za ocenu...', 'obavezno' => true],
        ['kljuc' => 'znaciKrivotvorenja', 'naziv' => 'Znaci krivotvorenja / izmene', 'placeholder' => 'Identifikovane izmene teksta, falsifikovani potpisi, lažni pečati...'],
        ['kljuc' => 'materijalnaAnaliza', 'naziv' => 'Analiza materijala',           'placeholder' => 'Papir, mastilo, štampač, datum nastanka dokumenta...'],
        ['kljuc' => 'zakljucak',          'naziv' => 'Zaključak',                    'placeholder' => 'Forenzički zaključak dokumentološke analize...', 'obavezno' => true],
    ],
    'DRUGA' => [
        ['kljuc' => 'metodologija', 'naziv' => 'Primenjena metodologija', 'placeholder' => 'Opišite korišćene metode i tehnike analize...', 'obavezno' => true],
        ['kljuc' => 'nalaz',        'naziv' => 'Nalaz',                   'placeholder' => 'Detaljan opis svih nalaza i opažanja...', 'obavezno' => true],
        ['kljuc' => 'zakljucak',    'naziv' => 'Zaključak',               'placeholder' => 'Forenzički zaključak analize...', 'obavezno' => true],
    ],
];
$sekcije = $sekcijePoTipu[$tipAnalize] ?? $sekcijePoTipu['DRUGA'];
?>

<?php if ($mozeUnositi): ?>
<!-- Forma za unos rezultata (VESTAK, U_TOKU) — strukturisane sekcije po tipu -->
<div class="card">
    <div class="card-header">
        <h3>Izveštaj veštačenja — <?= e(tipAnalizeLabel($tipAnalize)) ?></h3>
    </div>
    <div class="card-body">
        <form method="POST" action="?page=analiza-rezultat&id=<?= $zahtevId ?>" id="forma-rezultat">
            <input type="hidden" name="sadrzaj" id="hidden-sadrzaj">

            <!-- Parametri analize (kratki inputi) -->
            <?php if (!empty($poljaZaTip)): ?>
            <div style="border:1px solid var(--border); background:var(--surface-2); padding:18px 20px; margin-bottom:20px;">
                <div style="font-family:var(--mono); font-size:9px; text-transform:uppercase; letter-spacing:2px; color:var(--yellow); margin-bottom:14px;">Podaci o predmetu analize</div>
                <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:14px;">
                    <?php foreach ($poljaZaTip as $polje): ?>
                    <div class="form-group" style="margin-bottom:0;">
                        <label><?= e($polje['naziv']) ?></label>
                        <input type="text"
                            data-kljuc="<?= e($polje['kljuc']) ?>"
                            data-naziv="<?= e($polje['naziv']) ?>"
                            data-tip="parametar"
                            placeholder="<?= e($polje['placeholder']) ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Sekcije nalaza (dugi textarei) -->
            <div style="display:flex; flex-direction:column; gap:18px;">
                <?php foreach ($sekcije as $sekcija): ?>
                <div class="form-group" style="margin-bottom:0;">
                    <label>
                        <?= e($sekcija['naziv']) ?>
                        <?php if (!empty($sekcija['obavezno'])): ?>
                        <span style="color:var(--red); margin-left:4px;">*</span>
                        <?php endif; ?>
                    </label>
                    <textarea
                        data-kljuc="<?= e($sekcija['kljuc']) ?>"
                        data-naziv="<?= e($sekcija['naziv']) ?>"
                        data-obavezno="<?= !empty($sekcija['obavezno']) ? '1' : '0' ?>"
                        rows="4"
                        placeholder="<?= e($sekcija['placeholder']) ?>"
                    ></textarea>
                </div>
                <?php endforeach; ?>
            </div>

            <p style="font-family:var(--mono); font-size:10px; color:var(--text-3); margin-top:14px;">
                Polja označena sa <span style="color:var(--red);">*</span> su obavezna. Rezultat se čuva trajno i ne može biti izmenjen.
            </p>

            <div class="action-bar">
                <button type="submit" class="btn btn-primary">Predaj nalaz</button>
                <a href="?page=analiza-detalji&id=<?= $zahtevId ?>" class="btn btn-ghost">Otkaži</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('forma-rezultat').addEventListener('submit', function(e) {
    const sekcije = document.querySelectorAll('[data-kljuc]:not([data-tip="parametar"])');
    const greske  = [];

    sekcije.forEach(function(el) {
        if (el.dataset.obavezno === '1' && !el.value.trim()) {
            greske.push('"' + el.dataset.naziv + '" je obavezno polje.');
        }
    });

    if (greske.length > 0) {
        e.preventDefault();
        alert(greske.join('\n'));
        return;
    }

    const delovi = [];

    // Parametri analize (kratki inputi) — grupišemo na vrhu
    const parametri = document.querySelectorAll('[data-tip="parametar"]');
    if (parametri.length > 0) {
        const linParam = [];
        parametri.forEach(function(el) {
            const val = el.value.trim();
            if (val) linParam.push(el.dataset.naziv + ': ' + val);
        });
        if (linParam.length > 0) {
            delovi.push('=== PARAMETRI ANALIZE ===\n' + linParam.join('\n'));
        }
    }

    // Sekcije nalaza (textarei)
    sekcije.forEach(function(el) {
        const val = el.value.trim();
        delovi.push('=== ' + el.dataset.naziv.toUpperCase() + ' ===\n' + (val || '(nije uneto)'));
    });

    document.getElementById('hidden-sadrzaj').value = delovi.join('\n\n');
});
</script>

<?php elseif ($rezultat): ?>
<!-- Prikaz rezultata -->
<div class="card">
    <div class="card-header">
        <h3>Rezultat analize</h3>
        <?php if ($rezultat['verifikovan']): ?>
        <span class="badge badge-green">Verifikovan</span>
        <?php else: ?>
        <span class="badge badge-yellow">Čeka verifikaciju</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div style="white-space:pre-wrap; font-family:var(--mono); font-size:12px; color:var(--text-1); line-height:1.6; margin-bottom:16px;"><?= e($rezultat['sadrzaj']) ?></div>
        <div style="font-family:var(--mono); font-size:10px; color:var(--text-3);">
            Uneo: <?= e($rezultat['ime'] . ' ' . $rezultat['prezime']) ?> · <?= formatDatumVreme($rezultat['datum_unosa']) ?>
        </div>

        <?php if ($mozeVerifikovati): ?>
        <div style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border);">
            <form method="POST" action="?page=analiza-detalji&id=<?= $zahtevId ?>&action=verifikuj" style="display:inline;">
                <button type="submit" class="btn btn-success" onclick="return confirm('Verifikovati rezultat analize?')">Verifikuj rezultat</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- Nema rezultata a korisnik nije veštak/ne može unositi -->
<div class="card">
    <div class="card-body">
        <div class="empty-state">Rezultat analize još nije unet</div>
    </div>
</div>
<?php endif; ?>

<!-- Istorija statusa -->
<?php if ($istorijaStatusa->num_rows > 0): ?>
<div class="card" style="margin-top:20px;">
    <div class="card-header"><h3>Istorija statusa</h3></div>
    <div class="card-body">
        <ul class="timeline">
            <?php while ($is = $istorijaStatusa->fetch_assoc()): ?>
            <li>
                <div class="tl-ts"><?= formatDatumVreme($is['datum_vreme']) ?></div>
                <div class="tl-dot"></div>
                <div class="tl-body">
                    <strong><?= $is['stari_status'] ? e(badgeLabel($is['stari_status'])) . ' → ' : '' ?><?= e(badgeLabel($is['novi_status'])) ?></strong>
                    <?php if ($is['napomena']): ?><br><?= e($is['napomena']) ?><?php endif; ?>
                    <br><span style="color:var(--text-3);"><?= e($is['ime'] . ' ' . $is['prezime']) ?></span>
                </div>
            </li>
            <?php endwhile; ?>
        </ul>
    </div>
</div>
<?php endif; ?>
