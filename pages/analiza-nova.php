<?php
/**
 * pages/analiza-nova.php — Forma za kreiranje novog zahteva za analizu
 *
 * Predmeti: samo ovog istražitelja u fazi ANALIZA_DOKAZA.
 * Dokazi: samo U_SKLADISTU, filtrirani JS-om po izabranom predmetu.
 * Pristup: samo ISTRAZITELJ.
 */
requireRole('ISTRAZITELJ');

$userId = $_SESSION['user_id'];

// Predmeti: samo ovog istražitelja, u fazi ANALIZA_DOKAZA
$stmtPred = $conn->prepare("
    SELECT id, naziv FROM predmet
    WHERE istrazitelj_id = ? AND faza = 'ANALIZA_DOKAZA' AND status = 'AKTIVAN'
    ORDER BY naziv
");
$stmtPred->bind_param('i', $userId);
$stmtPred->execute();
$predmetiRes = $stmtPred->get_result();
$stmtPred->close();

// Svi dokazi U_SKLADISTU — za JS filter po predmetu
$dokaziQuery = $conn->query("SELECT id, sifra_dokaza, naziv, predmet_id FROM dokaz WHERE status = 'U_SKLADISTU' ORDER BY sifra_dokaza");
$sviDokazi = [];
while ($d = $dokaziQuery->fetch_assoc()) {
    $sviDokazi[] = $d;
}

// Aktivni veštaci
$vestaciRes = $conn->query("SELECT k.id, k.ime, k.prezime, v.specijalnost FROM vestak v JOIN korisnik k ON k.id = v.id_korisnik WHERE k.aktivan = 1 ORDER BY k.prezime");
?>

<div class="page-breadcrumb">
    <a href="?page=analize" class="btn btn-ghost btn-sm">&larr; Analize</a>
</div>

<div class="page-eyebrow">Nova analiza</div>
<div class="page-title">Zahtev za analizu</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="?page=analiza-nova">
            <div class="form-grid">
                <div class="form-group">
                    <label>Predmet *</label>
                    <select name="predmet_id" id="sel-predmet" required>
                        <option value="">— Izaberi predmet (mora biti u fazi Analiza dokaza) —</option>
                        <?php while ($p = $predmetiRes->fetch_assoc()): ?>
                        <option value="<?= $p['id'] ?>"><?= e($p['naziv']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Dokaz *</label>
                    <select name="dokaz_id" id="sel-dokaz" required>
                        <option value="">— Prvo izaberi predmet —</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tip analize *</label>
                    <select name="tip_analize" id="sel-tip" required>
                        <option value="">— Izaberi tip —</option>
                        <?php foreach (['BALISTICKA','DNK','DIGITALNA','HEMIJSKA','TOKSIKOLOSKA','DOKUMENTOLOSKA','DRUGA'] as $t): ?>
                        <option value="<?= $t ?>"><?= tipAnalizeLabel($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Veštak</label>
                    <select name="vestak_id">
                        <option value="">— Dodeli veštaka kasnije —</option>
                        <?php while ($v = $vestaciRes->fetch_assoc()): ?>
                        <option value="<?= $v['id'] ?>">
                            <?= e($v['ime'] . ' ' . $v['prezime']) ?><?= $v['specijalnost'] ? ' (' . e($v['specijalnost']) . ')' : '' ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Rok *</label>
                    <input type="date" name="rok" required min="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label>Planirani početak</label>
                    <input type="date" name="datum_pocetka" min="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label>Prag upozorenja (dana)</label>
                    <input type="number" name="prag_upozorenja_dana" value="3" min="1" max="30">
                </div>

                <div class="form-group full">
                    <label>Opis zahteva</label>
                    <textarea name="opis" rows="3" placeholder="Opišite šta treba analizirati, posebne napomene za veštaka..."></textarea>
                </div>
            </div>

            <div class="action-bar">
                <button type="submit" class="btn btn-primary" id="btn-submit">Kreiraj zahtev</button>
                <a href="?page=analize" class="btn btn-ghost">Otkaži</a>
            </div>
        </form>
    </div>
</div>

<script>
const sviDokazi = <?= json_encode($sviDokazi) ?>;

// Filtriranje dokaza po predmetu
function filtrirajDokaze() {
    const predmetId = parseInt(document.getElementById('sel-predmet').value) || 0;
    const sel = document.getElementById('sel-dokaz');
    sel.innerHTML = '';

    const placeholder = document.createElement('option');
    placeholder.value = '';

    if (predmetId === 0) {
        placeholder.textContent = '— Prvo izaberi predmet —';
        sel.appendChild(placeholder);
        return;
    }

    placeholder.textContent = '— Izaberi dokaz —';
    sel.appendChild(placeholder);

    const filtered = sviDokazi.filter(d => parseInt(d.predmet_id) === predmetId);
    if (filtered.length === 0) {
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = 'Nema dokaza sa statusom U_SKLADISTU za ovaj predmet';
        empty.disabled = true;
        sel.appendChild(empty);
        return;
    }

    filtered.forEach(d => {
        const opt = document.createElement('option');
        opt.value = d.id;
        opt.textContent = d.sifra_dokaza + ' — ' + d.naziv;
        sel.appendChild(opt);
    });
}

document.getElementById('sel-predmet').addEventListener('change', filtrirajDokaze);
</script>
