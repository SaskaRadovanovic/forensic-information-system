<?php
/**
 * pages/dashboard.php — Glavna stranica (dashboard)
 *
 * Prikaz zavisi od uloge korisnika:
 * - ISTRAZITELJ/ADMINISTRATOR: statistike predmeta i analiza
 * - TEHNICAR: preusmeravanje na dokaze
 * - VESTAK: statistike svojih analiza
 */

$uloga = $_SESSION['uloga'];
$userId = $_SESSION['user_id'];

// ─── TEHNICAR — redirect na dokaze ──────────────────────────────────────────
if ($uloga === 'TEHNICAR') {
    header('Location: ?page=dokazi');
    exit;
}
?>

<div class="page-eyebrow">Pregled</div>
<div class="page-title">Dashboard</div>

<?php if ($uloga === 'ISTRAZITELJ' || $uloga === 'ADMINISTRATOR'): ?>
<?php
// ─── Statistike za istražitelja/administratora ──────────────────────────────

// Aktivni predmeti
$res = $conn->query("SELECT COUNT(*) as cnt FROM predmet WHERE status = 'AKTIVAN'");
$aktivniPredmeti = $res->fetch_assoc()['cnt'];

// Na čekanju analize
$res = $conn->query("SELECT COUNT(*) as cnt FROM zahtev_za_analizu WHERE status IN ('KREIRAN','DODELJEN','U_TOKU')");
$naCekanjuAnalize = $res->fetch_assoc()['cnt'];

// Prekoračeni rokovi
$res = $conn->query("SELECT COUNT(*) as cnt FROM zahtev_za_analizu WHERE status = 'PREKORACEN'");
$prekoraceni = $res->fetch_assoc()['cnt'];

// Zatvoreni ovog meseca
$res = $conn->query("SELECT COUNT(*) as cnt FROM predmet WHERE status = 'ZATVOREN' AND MONTH(datum_otvaranja) = MONTH(NOW()) AND YEAR(datum_otvaranja) = YEAR(NOW())");
$zatvoreniMesec = $res->fetch_assoc()['cnt'];
?>

<!-- Stats kartice -->
<div class="stats-grid">
    <div class="stat-card yellow">
        <div class="stat-label">Aktivni predmeti</div>
        <div class="stat-value"><?= $aktivniPredmeti ?></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Analize u toku</div>
        <div class="stat-value"><?= $naCekanjuAnalize ?></div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">Prekoračeni rokovi</div>
        <div class="stat-value"><?= $prekoraceni ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Zatvoreni (mesec)</div>
        <div class="stat-value"><?= $zatvoreniMesec ?></div>
    </div>
</div>

<!-- Tabela aktivnih predmeta -->
<div class="card">
    <div class="card-header">
        <h3>Aktivni predmeti</h3>
    </div>
    <div class="card-body">
        <?php
        $res = $conn->query("SELECT id, naziv, faza, datum_otvaranja FROM predmet WHERE status = 'AKTIVAN' ORDER BY datum_otvaranja DESC LIMIT 10");
        ?>
        <?php if ($res->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Naziv</th>
                    <th>Faza</th>
                    <th>Datum otvaranja</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $res->fetch_assoc()): ?>
                <tr>
                    <td><a href="?page=predmet-detalji&id=<?= $row['id'] ?>">#<?= $row['id'] ?></a></td>
                    <td><?= e($row['naziv']) ?></td>
                    <td><span class="badge <?= fazaBadge($row['faza']) ?>"><?= e(fazaLabel($row['faza'])) ?></span></td>
                    <td><?= formatDatum($row['datum_otvaranja']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Nema aktivnih predmeta</div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($uloga === 'VESTAK'): ?>
<?php
// ─── Statistike za veštaka ──────────────────────────────────────────────────

// Aktivne analize (dodeljene ovom veštaku)
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM zahtev_za_analizu WHERE vestak_id = ? AND status IN ('DODELJEN','U_TOKU')");
$stmt->bind_param('i', $userId);
$stmt->execute();
$aktivneAnalize = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// Prekoračene
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM zahtev_za_analizu WHERE vestak_id = ? AND status = 'PREKORACEN'");
$stmt->bind_param('i', $userId);
$stmt->execute();
$kasne = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// Rok ovog meseca
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM zahtev_za_analizu WHERE vestak_id = ? AND status IN ('DODELJEN','U_TOKU') AND MONTH(rok) = MONTH(NOW()) AND YEAR(rok) = YEAR(NOW())");
$stmt->bind_param('i', $userId);
$stmt->execute();
$rokMesec = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// Završene YTD (od početka godine)
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM zahtev_za_analizu WHERE vestak_id = ? AND status = 'ZAVRSEN' AND YEAR(datum_kreiranja) = YEAR(NOW())");
$stmt->bind_param('i', $userId);
$stmt->execute();
$zavrseneGodina = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();
?>

<!-- Stats kartice -->
<div class="stats-grid">
    <div class="stat-card yellow">
        <div class="stat-label">Aktivne analize</div>
        <div class="stat-value"><?= $aktivneAnalize ?></div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">Prekoračene</div>
        <div class="stat-value"><?= $kasne ?></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Rok ovog meseca</div>
        <div class="stat-value"><?= $rokMesec ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Završene (godina)</div>
        <div class="stat-value"><?= $zavrseneGodina ?></div>
    </div>
</div>

<!-- Tabela dodeljenih analiza -->
<div class="card">
    <div class="card-header">
        <h3>Dodeljene analize</h3>
    </div>
    <div class="card-body">
        <?php
        $stmt = $conn->prepare("
            SELECT z.id, z.tip_analize, z.status, z.rok, d.sifra_dokaza, d.naziv as dokaz_naziv
            FROM zahtev_za_analizu z
            JOIN dokaz d ON d.id = z.dokaz_id
            WHERE z.vestak_id = ? AND z.status IN ('DODELJEN','U_TOKU')
            ORDER BY z.rok ASC
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $analize = $stmt->get_result();
        ?>
        <?php if ($analize->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Dokaz</th>
                    <th>Tip</th>
                    <th>Rok</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $analize->fetch_assoc()): ?>
                <tr>
                    <td><a href="?page=analiza-detalji&id=<?= $row['id'] ?>">#<?= $row['id'] ?></a></td>
                    <td><?= e($row['sifra_dokaza'] . ' — ' . $row['dokaz_naziv']) ?></td>
                    <td><span class="badge badge-blue"><?= e(tipAnalizeLabel($row['tip_analize'])) ?></span></td>
                    <td><?= formatDatum($row['rok']) ?></td>
                    <td><span class="badge <?= badgeClass($row['status']) ?>"><?= e(badgeLabel($row['status'])) ?></span></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Nemate dodeljenih analiza</div>
        <?php endif; ?>
        <?php $stmt->close(); ?>
    </div>
</div>

<?php endif; ?>
