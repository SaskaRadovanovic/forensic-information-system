<?php
/**
 * pages/dokument-detalji.php — Detalji dokumenta
 *
 * Prikazuje sve podatke o dokumentu, metapodatke, tagove,
 * istoriju verzija, i dugmad za akcije.
 */

$dokumentId = (int)($_GET['id'] ?? 0);

// ─── Učitavanje dokumenta sa relacijama ────────────────────────────────────
$stmt = $conn->prepare("
    SELECT dok.*, p.naziv AS predmet_naziv,
           k.ime AS autor_ime, k.prezime AS autor_prezime
    FROM dokument dok
    JOIN predmet p ON p.id = dok.predmet_id
    JOIN korisnik k ON k.id = dok.autor_id
    WHERE dok.id = ?
");
$stmt->bind_param('i', $dokumentId);
$stmt->execute();
$dokument = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$dokument) {
    flashError('Dokument nije pronađen.');
    header('Location: ?page=dokumentacija');
    exit;
}

// ─── Provera prava pristupa ───────────────────────────────────────────────
$currentUloga = $_SESSION['uloga'];
$currentUserId = $_SESSION['user_id'];
if ($currentUloga !== 'ADMINISTRATOR'
    && ($dokument['nivo_poverljivosti'] ?? 'INTERNO') !== 'JAVNO'
    && $dokument['autor_id'] !== $currentUserId
) {
    $stmtPristup = $conn->prepare("SELECT 1 FROM pravo_pristupa WHERE dokument_id = ? AND korisnik_id = ?");
    $stmtPristup->bind_param('ii', $dokumentId, $currentUserId);
    $stmtPristup->execute();
    $imaPristup = $stmtPristup->get_result()->num_rows > 0;
    $stmtPristup->close();

    if (!$imaPristup) {
        http_response_code(403);
        die('<h1>403 — Nemate pristup ovom dokumentu</h1><p><a href="?page=dokumentacija">Nazad na dokumentaciju</a></p>');
    }
}

// ─── Učitavanje metapodataka ───────────────────────────────────────────────
$stmt = $conn->prepare("SELECT kljuc, vrednost FROM metapodatak WHERE dokument_id = ?");
$stmt->bind_param('i', $dokumentId);
$stmt->execute();
$metapodaci = [];
$metaResult = $stmt->get_result();
while ($m = $metaResult->fetch_assoc()) {
    $metapodaci[$m['kljuc']] = $m['vrednost'];
}
$stmt->close();

// ─── Učitavanje tagova na dokumentu ────────────────────────────────────────
$stmt = $conn->prepare("SELECT t.id, t.naziv, t.boja FROM dokument_tag dt JOIN tag t ON t.id = dt.tag_id WHERE dt.dokument_id = ?");
$stmt->bind_param('i', $dokumentId);
$stmt->execute();
$tagovi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Svi tagovi (za upravljanje)
$sviTagovi = $conn->query("SELECT id, naziv, boja FROM tag ORDER BY naziv")->fetch_all(MYSQLI_ASSOC);
$tagIdsNaDokumentu = array_column($tagovi, 'id');

// ─── Istorija verzija ──────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT da.*, k.ime, k.prezime
    FROM dokument_arhiva da
    JOIN korisnik k ON k.id = da.sacuvao_id
    WHERE da.dokument_id = ?
    ORDER BY da.datum_arhiviranja DESC
");
$stmt->bind_param('i', $dokumentId);
$stmt->execute();
$istorija = $stmt->get_result();
?>

<!-- Breadcrumb -->
<div class="page-breadcrumb">
    <a href="?page=dokumentacija" class="btn btn-ghost btn-sm">&larr; Dokumentacija</a>
    <span style="color: var(--text-3); font-family: var(--mono); font-size: 12px;">/</span>
    <span style="color: var(--text-2); font-family: var(--mono); font-size: 12px;"><?= e($dokument['naziv']) ?></span>
</div>

<!-- Naslov + status -->
<div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px;">
    <div class="page-title" style="margin-bottom: 0;"><?= e($dokument['naziv']) ?></div>
    <span class="badge <?= badgeClass($dokument['status']) ?>"><?= e(badgeLabel($dokument['status'])) ?></span>
    <span class="badge badge-blue">v<?= $dokument['verzija'] ?></span>
</div>

<!-- Info grid -->
<div class="info-grid">
    <div class="info-item">
        <div class="info-label">Tip dokumenta</div>
        <div class="info-value"><?= e($metapodaci['tipDokumenta'] ?? '—') ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Predmet</div>
        <div class="info-value"><?= e($dokument['predmet_naziv']) ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Autor</div>
        <div class="info-value"><?= e($dokument['autor_ime'] . ' ' . $dokument['autor_prezime']) ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Verzija</div>
        <div class="info-value">v<?= $dokument['verzija'] ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Status</div>
        <div class="info-value"><span class="badge <?= badgeClass($dokument['status']) ?>"><?= e(badgeLabel($dokument['status'])) ?></span></div>
    </div>
    <div class="info-item">
        <div class="info-label">Nivo poverljivosti</div>
        <div class="info-value"><span class="badge <?= nivoPoverljivostiBadge($dokument['nivo_poverljivosti'] ?? 'INTERNO') ?>"><?= e(nivoPoverljivostiLabel($dokument['nivo_poverljivosti'] ?? 'INTERNO')) ?></span></div>
    </div>
    <div class="info-item">
        <div class="info-label">Datum kreiranja</div>
        <div class="info-value"><?= formatDatumVreme($dokument['datum_kreiranja']) ?></div>
    </div>
</div>

<!-- Pregled fajla -->
<div class="card">
    <div class="card-header"><h3>Priloženi fajl</h3></div>
    <div class="card-body">
        <?php
        $putanjaFajla = $dokument['putanja'];
        $fizickaPutanja = UPLOAD_DIR . $putanjaFajla;
        $fajlPostoji = !in_array($putanjaFajla, ['simulirano', 'nema-fajla'], true) && file_exists($fizickaPutanja);

        if ($fajlPostoji):
            $ext = strtolower(pathinfo($putanjaFajla, PATHINFO_EXTENSION));
            if ($ext === 'pdf'): ?>
                <embed src="uploads/<?= e($putanjaFajla) ?>" type="application/pdf" width="100%" height="600px" style="border:1px solid var(--border); border-radius:8px;" />
            <?php elseif (in_array($ext, ['jpg', 'jpeg', 'png'], true)): ?>
                <img src="uploads/<?= e($putanjaFajla) ?>" alt="<?= e($dokument['naziv']) ?>" style="max-width:100%; border-radius:8px; border:1px solid var(--border);" />
            <?php else: ?>
                <div style="color:var(--text-2); font-family:var(--mono); font-size:12px;">
                    Fajl: <?= e($putanjaFajla) ?> — pregled nije dostupan za ovaj tip fajla.
                </div>
            <?php endif; ?>
            <div style="margin-top:12px;">
                <a href="?page=dokument-detalji&id=<?= $dokumentId ?>&action=download" class="btn btn-outline btn-sm">Preuzmi fajl</a>
            </div>
        <?php else: ?>
            <div style="color:var(--text-3); font-family:var(--mono); font-size:11px;">Fajl nije uploadovan.</div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($metapodaci['opis'])): ?>
<div class="card">
    <div class="card-header"><h3>Opis</h3></div>
    <div class="card-body"><?= e($metapodaci['opis']) ?></div>
</div>
<?php endif; ?>

<!-- Tagovi -->
<div class="card">
    <div class="card-header"><h3>Tagovi</h3></div>
    <div class="card-body">
        <?php if (!empty($tagovi)): ?>
        <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:12px;">
            <?php foreach ($tagovi as $tag): ?>
            <span class="badge" style="background:<?= e($tag['boja']) ?>22; color:<?= e($tag['boja']) ?>; border-color:<?= e($tag['boja']) ?>44;"><?= e($tag['naziv']) ?></span>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="color:var(--text-3); font-family:var(--mono); font-size:11px; margin-bottom:12px;">Nema tagova</div>
        <?php endif; ?>

        <?php
        // Poluautomatsko predlaganje tagova na osnovu tipa i opisa
        if ($dokument['status'] !== 'ARHIVIRAN'):
            $tipDokumenta = $metapodaci['tipDokumenta'] ?? '';
            $opisDokumenta = $metapodaci['opis'] ?? '';
            $predlozeniNazivi = sviPredlozeniTagovi($tipDokumenta, $opisDokumenta);

            // Filtriraj: prikaži samo predloge koji postoje u bazi i NISU već na dokumentu
            $predlozeniZaPrikaz = [];
            foreach ($sviTagovi as $tag) {
                if (in_array($tag['naziv'], $predlozeniNazivi, true) && !in_array($tag['id'], $tagIdsNaDokumentu)) {
                    $predlozeniZaPrikaz[] = $tag;
                }
            }

            if (!empty($predlozeniZaPrikaz)):
        ?>
        <div style="margin-bottom:12px; padding:10px; background:var(--surface-1); border:1px dashed var(--border); border-radius:8px;">
            <div style="font-size:12px; color:var(--text-2); margin-bottom:6px;">Predloženi tagovi (na osnovu tipa i opisa):</div>
            <div style="display:flex; gap:4px; flex-wrap:wrap;">
                <?php foreach ($predlozeniZaPrikaz as $tag): ?>
                <form method="POST" action="?page=dokument-detalji&id=<?= $dokumentId ?>&action=toggle-tag" style="display:inline;">
                    <input type="hidden" name="tag_id" value="<?= $tag['id'] ?>">
                    <button type="submit" class="btn btn-sm" style="border:1px dashed <?= e($tag['boja']) ?>88; color:<?= e($tag['boja']) ?>; background:<?= e($tag['boja']) ?>08;">
                        + <?= e($tag['naziv']) ?>
                    </button>
                </form>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
            endif;
        ?>

        <div style="display:flex; gap:4px; flex-wrap:wrap;">
            <?php foreach ($sviTagovi as $tag): ?>
            <form method="POST" action="?page=dokument-detalji&id=<?= $dokumentId ?>&action=toggle-tag" style="display:inline;">
                <input type="hidden" name="tag_id" value="<?= $tag['id'] ?>">
                <button type="submit" class="btn btn-sm" style="<?= in_array($tag['id'], $tagIdsNaDokumentu) ? 'background:' . e($tag['boja']) . '22; color:' . e($tag['boja']) . '; border-color:' . e($tag['boja']) . '44;' : '' ?>">
                    <?= in_array($tag['id'], $tagIdsNaDokumentu) ? '✕' : '+' ?> <?= e($tag['naziv']) ?>
                </button>
            </form>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Prava pristupa -->
<?php
$korisnikId = $_SESSION['user_id'];
$uloga = $_SESSION['uloga'];
$jeAutor = ($dokument['autor_id'] === $korisnikId);
$mozeUpravljatiPristupom = ($jeAutor || $uloga === 'ADMINISTRATOR');

// Učitaj korisnike sa pristupom
$stmtPrava = $conn->prepare("
    SELECT pp.id, pp.nivo_pristupa, pp.datum_dodele,
           k.id AS kid, k.ime, k.prezime, k.uloga
    FROM pravo_pristupa pp
    JOIN korisnik k ON k.id = pp.korisnik_id
    WHERE pp.dokument_id = ?
    ORDER BY pp.datum_dodele DESC
");
$stmtPrava->bind_param('i', $dokumentId);
$stmtPrava->execute();
$pravaPristupa = $stmtPrava->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtPrava->close();

$korisnikIdsSaPristupom = array_column($pravaPristupa, 'kid');
$korisnikIdsSaPristupom[] = $dokument['autor_id'];
?>

<div class="card">
    <div class="card-header"><h3>Prava pristupa</h3></div>
    <div class="card-body" style="padding:0;">
        <?php if (!empty($pravaPristupa)): ?>
        <table>
            <thead>
                <tr>
                    <th>Korisnik</th>
                    <th>Uloga</th>
                    <th>Nivo pristupa</th>
                    <th>Datum dodele</th>
                    <?php if ($mozeUpravljatiPristupom): ?><th>Akcija</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pravaPristupa as $pp): ?>
                <tr>
                    <td><?= e($pp['ime'] . ' ' . $pp['prezime']) ?></td>
                    <td><span class="badge <?= ulogaBadge($pp['uloga']) ?>"><?= e(ulogaLabel($pp['uloga'])) ?></span></td>
                    <td><?= $pp['nivo_pristupa'] === 'IZMENA' ? 'Izmena' : 'Čitanje' ?></td>
                    <td><?= formatDatumVreme($pp['datum_dodele']) ?></td>
                    <?php if ($mozeUpravljatiPristupom): ?>
                    <td>
                        <form method="POST" action="?page=dokument-detalji&id=<?= $dokumentId ?>&action=ukloni-pristup" style="display:inline;">
                            <input type="hidden" name="pravo_id" value="<?= $pp['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Ukloniti pristup?')">Ukloni</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Nema dodeljenih prava pristupa</div>
        <?php endif; ?>
    </div>
</div>

<?php if ($mozeUpravljatiPristupom && $dokument['status'] !== 'ARHIVIRAN'): ?>
<?php
// Učitaj korisnike koji nemaju pristup
$placeholders = implode(',', array_fill(0, count($korisnikIdsSaPristupom), '?'));
$sqlKorisnici = "SELECT id, ime, prezime, uloga FROM korisnik WHERE aktivan = 1 AND id NOT IN ({$placeholders}) ORDER BY prezime, ime";
$stmtKor = $conn->prepare($sqlKorisnici);
$types = str_repeat('i', count($korisnikIdsSaPristupom));
$stmtKor->bind_param($types, ...$korisnikIdsSaPristupom);
$stmtKor->execute();
$dostupniKorisnici = $stmtKor->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtKor->close();
?>
<?php if (!empty($dostupniKorisnici)): ?>
<div class="card">
    <div class="card-header"><h3>Dodeli pristup</h3></div>
    <div class="card-body">
        <form method="POST" action="?page=dokument-detalji&id=<?= $dokumentId ?>&action=dodaj-pristup">
            <div class="form-grid">
                <div class="form-group">
                    <label>Korisnik</label>
                    <select name="korisnik_id" required>
                        <option value="">— Izaberi korisnika —</option>
                        <?php foreach ($dostupniKorisnici as $kor): ?>
                        <option value="<?= $kor['id'] ?>"><?= e($kor['ime'] . ' ' . $kor['prezime']) ?> (<?= e(ulogaLabel($kor['uloga'])) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nivo pristupa</label>
                    <select name="nivo_pristupa" required>
                        <option value="CITANJE">Čitanje</option>
                        <option value="IZMENA">Izmena</option>
                    </select>
                </div>
            </div>
            <div class="action-bar">
                <button type="submit" class="btn btn-primary btn-sm">Dodeli pristup</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Istorija deljenja pristupa -->
<?php
$stmtLog = $conn->prepare("
    SELECT lp.akcija, lp.datum_vreme, lp.napomena,
           k.ime AS kor_ime, k.prezime AS kor_prezime,
           i.ime AS izv_ime, i.prezime AS izv_prezime
    FROM log_pristupa lp
    JOIN korisnik k ON k.id = lp.korisnik_id
    JOIN korisnik i ON i.id = lp.izvrsio_id
    WHERE lp.dokument_id = ?
    ORDER BY lp.datum_vreme DESC
");
$stmtLog->bind_param('i', $dokumentId);
$stmtLog->execute();
$logPristupa = $stmtLog->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtLog->close();
?>

<?php if (!empty($logPristupa)): ?>
<div class="card">
    <div class="card-header"><h3>Istorija deljenja pristupa</h3></div>
    <div class="card-body" style="padding:0;">
        <table>
            <thead>
                <tr>
                    <th>Akcija</th>
                    <th>Korisnik</th>
                    <th>Izvršio</th>
                    <th>Napomena</th>
                    <th>Datum</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logPristupa as $log): ?>
                <tr>
                    <td><span class="badge <?= $log['akcija'] === 'DODELA' ? 'badge-green' : 'badge-red' ?>"><?= $log['akcija'] === 'DODELA' ? 'Dodela' : 'Uklanjanje' ?></span></td>
                    <td><?= e($log['kor_ime'] . ' ' . $log['kor_prezime']) ?></td>
                    <td><?= e($log['izv_ime'] . ' ' . $log['izv_prezime']) ?></td>
                    <td><?= e($log['napomena'] ?? '—') ?></td>
                    <td><?= formatDatumVreme($log['datum_vreme']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Istorija verzija -->
<?php if ($istorija->num_rows > 0): ?>
<div class="card">
    <div class="card-header"><h3>Istorija verzija</h3></div>
    <div class="card-body" style="padding:0;">
        <table>
            <thead>
                <tr>
                    <th>Verzija</th>
                    <th>Razlog izmene</th>
                    <th>Sačuvao</th>
                    <th>Datum</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($v = $istorija->fetch_assoc()): ?>
                <tr>
                    <td>v<?= $v['verzija'] ?></td>
                    <td><?= e($v['razlog_izmene'] ?: '—') ?></td>
                    <td><?= e($v['ime'] . ' ' . $v['prezime']) ?></td>
                    <td><?= formatDatumVreme($v['datum_arhiviranja']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Dugmad -->
<div class="action-bar">
    <?php if ($dokument['status'] !== 'ARHIVIRAN'): ?>
    <a href="?page=dokument-izmeni&id=<?= $dokumentId ?>" class="btn btn-outline">Izmeni</a>
    <?php if ($_SESSION['uloga'] === 'ADMINISTRATOR'): ?>
    <form method="POST" action="?page=dokument-detalji&id=<?= $dokumentId ?>&action=arhiviraj" style="display:inline;">
        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Da li ste sigurni da želite da arhivirate ovaj dokument?')">Arhiviraj</button>
    </form>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php $stmt->close(); ?>
