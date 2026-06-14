<?php
/**
 * pages/istorija-predmeta.php — Timeline promene faza predmeta
 */

$predmetId = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT id, naziv, faza, status FROM predmet WHERE id = ?");
$stmt->bind_param('i', $predmetId);
$stmt->execute();
$predmet = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$predmet) {
    flashError('Predmet nije pronađen.');
    header('Location: ?page=predmeti');
    exit;
}

$stmt = $conn->prepare("
    SELECT h.faza, h.datum_vreme,
           k.ime, k.prezime
    FROM istorija_faze_predmeta h
    JOIN korisnik k ON k.id = h.korisnik_id
    WHERE h.predmet_id = ?
    ORDER BY h.datum_vreme ASC
");
$stmt->bind_param('i', $predmetId);
$stmt->execute();
$istorija = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function fazaBoja(string $faza): string {
    $mapa = [
        'OTVOREN_SLUCAJ'      => 'var(--blue)',
        'PRIKUPLJANJE_DOKAZA' => 'var(--yellow)',
        'ANALIZA_DOKAZA'      => 'var(--orange)',
        'DONOSENJE_ZAKLJUCKA' => 'var(--purple)',
        'ZATVOREN_SLUCAJ'     => 'var(--green)',
    ];
    return $mapa[$faza] ?? 'var(--text-3)';
}
?>

<style>
.istorija-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
}
.istorija-lista {
    display: flex;
    flex-direction: column;
}
.istorija-red {
    display: flex;
    align-items: center;
    gap: 0;
    padding: 16px 24px;
    border-bottom: 1px solid var(--border);
    transition: background .15s;
}
.istorija-red:last-child {
    border-bottom: none;
}
.istorija-red:hover {
    background: var(--surface-2);
}
.istorija-meta {
    width: 160px;
    flex-shrink: 0;
    font-family: var(--mono);
    font-size: 11px;
}
.istorija-datum {
    color: var(--text-2);
    letter-spacing: .3px;
}
.istorija-korisnik {
    color: var(--text-3);
    margin-top: 3px;
    letter-spacing: 1px;
    font-size: 10px;
}
.istorija-separator {
    width: 1px;
    height: 36px;
    background: var(--border);
    margin: 0 24px;
    flex-shrink: 0;
}
.istorija-dogadjaj {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}
.istorija-indikator {
    width: 10px;
    height: 10px;
    flex-shrink: 0;
    display: inline-block;
}
.istorija-faza-tekst {
    font-family: var(--mono);
    font-size: 13px;
    color: var(--text-1);
    letter-spacing: .5px;
    text-transform: uppercase;
}
.istorija-empty {
    padding: 40px 24px;
    color: var(--text-3);
    font-family: var(--mono);
    font-size: 12px;
    text-align: center;
}
</style>

<div class="page-eyebrow">Istraga</div>
<div class="page-title">Istorija slučaja</div>

<div class="istorija-breadcrumb">
    <a href="?page=predmet-detalji&id=<?= $predmetId ?>" class="btn btn-outline btn-sm">
        &larr; Predmet
    </a>
    <span style="color:var(--text-3); font-family:var(--mono); font-size:11px; margin-left:4px;">
        <?= e($predmet['naziv']) ?>
    </span>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($istorija)): ?>
        <div class="istorija-empty">Nema zabeleženih promena faze za ovaj predmet.</div>
        <?php else: ?>
        <div class="istorija-lista">
            <?php foreach ($istorija as $zapis): ?>
            <div class="istorija-red">
                <div class="istorija-meta">
                    <div class="istorija-datum">
                        <?= e(date('d.m.Y, H:i', strtotime($zapis['datum_vreme']))) ?>
                    </div>
                    <div class="istorija-korisnik">
                        <?= e(mb_strtoupper(mb_substr($zapis['ime'], 0, 1)) . '. ' . mb_strtoupper($zapis['prezime'])) ?>
                    </div>
                </div>
                <div class="istorija-separator"></div>
                <div class="istorija-dogadjaj">
                    <span class="istorija-indikator"
                          style="background: <?= fazaBoja($zapis['faza']) ?>;"></span>
                    <span class="istorija-faza-tekst">
                        <?= e(fazaLabel($zapis['faza'])) ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
