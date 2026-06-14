<?php
/**
 * pages/dokazi-zahtevi.php — Lista zahteva za dokaze
 *
 * Prikazuje zahteve za predaju/povraćaj dokaza.
 * Tehničar ili administrator mogu odobriti ili odbiti zahtev.
 */
requireRole('TEHNICAR', 'ADMINISTRATOR');

// ─── Filter po statusu ────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';

// ─── Gradnja SQL upita ────────────────────────────────────────────────────
$sql = "
    SELECT z.id, z.tip, z.razlog, z.status, z.datum_kreiranja, z.datum_obrade, z.napomena,
           d.sifra_dokaza, d.naziv AS dokaz_naziv,
           k.ime AS podnosilac_ime, k.prezime AS podnosilac_prezime,
           kt.ime AS tehnicar_ime, kt.prezime AS tehnicar_prezime
    FROM zahtev_za_dokaz z
    JOIN dokaz d ON d.id = z.dokaz_id
    JOIN korisnik k ON k.id = z.podnosilac_id
    LEFT JOIN korisnik kt ON kt.id = z.tehnicar_id
    WHERE 1=1
";

$params = [];
$types  = '';

if ($filterStatus !== '') {
    $sql .= " AND z.status = ?";
    $params[] = $filterStatus;
    $types .= 's';
}

$sql .= " ORDER BY z.datum_kreiranja DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$zahtevi = $stmt->get_result();
?>

<div class="page-eyebrow">Dokazi</div>
<div class="page-title">Zahtevi za dokaze</div>

<!-- Filter bar -->
<div class="filter-bar">
    <form method="GET" style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; width:100%;">
        <input type="hidden" name="page" value="dokazi-zahtevi">

        <div class="form-group" style="margin-bottom:0;">
            <label>Status</label>
            <select name="status" onchange="this.form.submit()">
                <option value="">Svi statusi</option>
                <?php foreach (['NA_CEKANJU','ODOBREN','ODBIJEN'] as $s): ?>
                <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= badgeLabel($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<!-- Lista zahteva -->
<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if ($zahtevi->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tip</th>
                    <th>Dokaz</th>
                    <th>Podnosilac</th>
                    <th>Status</th>
                    <th>Datum</th>
                    <th>Akcije</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $zahtevi->fetch_assoc()): ?>
                <tr>
                    <td>#<?= $row['id'] ?></td>
                    <td><span class="badge <?= $row['tip'] === 'PREDAJA' ? 'badge-orange' : 'badge-blue' ?>"><?= e($row['tip']) ?></span></td>
                    <td><?= e($row['sifra_dokaza'] . ' — ' . $row['dokaz_naziv']) ?></td>
                    <td><?= e($row['podnosilac_ime'] . ' ' . $row['podnosilac_prezime']) ?></td>
                    <td><span class="badge <?= badgeClass($row['status']) ?>"><?= e(badgeLabel($row['status'])) ?></span></td>
                    <td><?= formatDatumVreme($row['datum_kreiranja']) ?></td>
                    <td>
                        <?php if ($row['status'] === 'NA_CEKANJU'): ?>
                        <!-- Forma za odobrenje -->
                        <form method="POST" action="?page=dokazi-zahtevi" style="display:inline;">
                            <input type="hidden" name="zahtev_id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="odluka" value="ODOBREN">
                            <button type="submit" class="btn btn-success btn-sm">Odobri</button>
                        </form>
                        <!-- Forma za odbijanje -->
                        <form method="POST" action="?page=dokazi-zahtevi" style="display:inline;">
                            <input type="hidden" name="zahtev_id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="odluka" value="ODBIJEN">
                            <button type="submit" class="btn btn-danger btn-sm">Odbij</button>
                        </form>
                        <?php else: ?>
                            <?php if ($row['tehnicar_ime']): ?>
                            <span style="color:var(--text-3); font-family:var(--mono); font-size:11px;">
                                <?= e($row['tehnicar_ime'] . ' ' . $row['tehnicar_prezime']) ?>
                            </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($row['razlog']): ?>
                <tr>
                    <td></td>
                    <td colspan="6" style="color:var(--text-3); font-size:11px;">Razlog: <?= e($row['razlog']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($row['napomena']): ?>
                <tr>
                    <td></td>
                    <td colspan="6" style="color:var(--text-3); font-size:11px;">Napomena: <?= e($row['napomena']) ?></td>
                </tr>
                <?php endif; ?>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Nema zahteva za dokaze</div>
        <?php endif; ?>
    </div>
</div>
<?php $stmt->close(); ?>
