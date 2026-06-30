<?php
/**
 * pages/analitika.php — Analitika forenzičkih istraga
 */
requireRole('ADMINISTRATOR', 'ISTRAZITELJ');

function formatSatiAn(float $sati): string {
    if ($sati <= 0) return '—';
    $dani = (int)floor($sati / 24);
    $h    = (int)round($sati - $dani * 24);
    return $dani > 0 ? "{$dani}D {$h}H" : "{$h}H";
}

// ─── 1. Broj predmeta po fazama ─────────────────────────────────────────────
$faze        = ['OTVOREN_SLUCAJ','PRIKUPLJANJE_DOKAZA','ANALIZA_DOKAZA','DONOSENJE_ZAKLJUCKA','ZATVOREN_SLUCAJ'];
$fazeBrojevi = array_fill_keys($faze, 0);

$res = $conn->query("SELECT faza, COUNT(*) AS cnt FROM predmet GROUP BY faza");
while ($row = $res->fetch_assoc()) {
    if (isset($fazeBrojevi[$row['faza']])) {
        $fazeBrojevi[$row['faza']] = (int)$row['cnt'];
    }
}
$ukupno = array_sum($fazeBrojevi);

// ─── 2. Prosečno vreme u svakoj fazi (na osnovu istorije) ───────────────────
$fazeVreme = array_fill_keys($faze, 0.0);
$stmt = $conn->prepare("
    SELECT h.faza,
           COALESCE(AVG(TIMESTAMPDIFF(HOUR, h.datum_vreme,
               (SELECT MIN(h2.datum_vreme) FROM istorija_faze_predmeta h2
                WHERE h2.predmet_id = h.predmet_id AND h2.id > h.id)
           )), 0) AS avg_sati
    FROM istorija_faze_predmeta h
    GROUP BY h.faza
");
$stmt->execute();
$tmpRes = $stmt->get_result();
while ($row = $tmpRes->fetch_assoc()) {
    if (isset($fazeVreme[$row['faza']])) {
        $fazeVreme[$row['faza']] = (float)$row['avg_sati'];
    }
}
$stmt->close();

// ─── 3. Ukupne statistike ────────────────────────────────────────────────────
$statRow = $conn->query("
    SELECT COUNT(*) AS ukupno,
           SUM(faza = 'ZATVOREN_SLUCAJ') AS zatvoreni,
           COALESCE(ROUND(AVG(TIMESTAMPDIFF(HOUR, p.datum_otvaranja,
               CASE
                   WHEN p.status = 'ZATVOREN'
                   THEN (SELECT MAX(h.datum_vreme) FROM istorija_faze_predmeta h WHERE h.predmet_id = p.id)
                   ELSE NOW()
               END
           ))), 0) AS avg_h
    FROM predmet p
")->fetch_assoc();

$zatvoreni  = (int)($statRow['zatvoreni'] ?? 0);
$avgH       = (float)($statRow['avg_h'] ?? 0);
$efikasnost = $ukupno > 0 ? round($zatvoreni / $ukupno * 100, 1) : 0;

// ─── 4. Predmeti po istražitelju (admin = svi, istrazitelj = samo sopstveni) ──
$istraziteljiLabele    = [];
$istraziteljiAktivni   = [];
$istraziteljiZatvoreni = [];
$ulogaKorisnika        = $_SESSION['uloga'];
$naslovGrafikona       = $ulogaKorisnika === 'ADMINISTRATOR'
    ? 'Predmeti po istražitelju'
    : 'Moji predmeti';

if ($ulogaKorisnika === 'ADMINISTRATOR') {
    $res = $conn->query("
        SELECT CONCAT(k.ime, ' ', k.prezime) AS naziv,
               SUM(p.status = 'AKTIVAN')  AS aktivni,
               SUM(p.status = 'ZATVOREN') AS zatvoreni
        FROM predmet p
        JOIN korisnik k ON k.id = p.istrazitelj_id
        GROUP BY p.istrazitelj_id, k.ime, k.prezime
        ORDER BY (SUM(p.status = 'AKTIVAN') + SUM(p.status = 'ZATVOREN')) DESC
    ");
} else {
    $stmt = $conn->prepare("
        SELECT CONCAT(k.ime, ' ', k.prezime) AS naziv,
               SUM(p.status = 'AKTIVAN')  AS aktivni,
               SUM(p.status = 'ZATVOREN') AS zatvoreni
        FROM predmet p
        JOIN korisnik k ON k.id = p.istrazitelj_id
        WHERE p.istrazitelj_id = ?
        GROUP BY p.istrazitelj_id, k.ime, k.prezime
    ");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
}
while ($row = $res->fetch_assoc()) {
    $istraziteljiLabele[]    = $row['naziv'];
    $istraziteljiAktivni[]   = (int)$row['aktivni'];
    $istraziteljiZatvoreni[] = (int)$row['zatvoreni'];
}

// ─── Labele i boje ───────────────────────────────────────────────────────────
$labeleFaza  = ['Otvoren slučaj','Prikupljanje dokaza','Analiza dokaza','Donošenje zaključka','Zatvoren slučaj'];
$kratkeLabel = ['OTVOREN SLUCAJ','PRIKUPLJANJE DOKAZA','ANALIZA DOKAZA','DON. ZAKLJ.','ZATVOREN SLUCAJ'];
$bojeFaza    = ['#3B82F6','#F97316','#FACC15','#22C55E','#EF4444'];
?>

<style>
.an-label   { font-family:var(--mono); font-size:9px; letter-spacing:.1em; color:var(--text-3); text-transform:uppercase; margin-bottom:10px; }
.an-grid5   { display:grid; grid-template-columns:repeat(5,1fr); gap:8px; }
.an-box     { background:var(--bg-3); border:1px solid var(--border); padding:12px 10px; }
.an-box-lbl { font-family:var(--mono); font-size:9px; letter-spacing:.06em; color:var(--text-3); text-transform:uppercase; line-height:1.35; min-height:24px; margin-bottom:8px; }
.an-big     { font-family:var(--mono); font-size:26px; font-weight:700; line-height:1; }
.an-mid     { font-family:var(--mono); font-size:18px; font-weight:700; line-height:1; }
.an-stat    { background:var(--bg-2); border:1px solid var(--border); padding:16px 14px; }
.an-stat-v  { font-family:var(--mono); font-size:30px; font-weight:700; color:var(--yellow); line-height:1; }
.an-stat-l  { font-family:var(--mono); font-size:9px; letter-spacing:.1em; color:var(--text-3); text-transform:uppercase; margin-top:7px; }
.an-leg     { display:flex; align-items:center; gap:9px; margin-bottom:9px; }
.an-leg-dot { width:9px; height:9px; flex-shrink:0; }
.an-leg-nm  { flex:1; font-family:var(--mono); font-size:10px; color:var(--text-2); }
.an-leg-pct { font-family:var(--mono); font-size:10px; font-weight:700; color:var(--yellow); }
.an-warn    { background:rgba(239,68,68,.07); border:1px solid rgba(239,68,68,.28); padding:9px 11px; margin-top:12px; font-family:var(--mono); font-size:10px; color:#f87171; line-height:1.55; }
</style>

<div class="page-eyebrow">Analitika</div>
<div class="page-title">Analitika forenzičkih istraga</div>

<!-- ── Gornji red ─────────────────────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 290px;gap:14px;margin-bottom:14px">

  <div>
    <div class="card" style="margin-bottom:12px">
      <div class="an-label">Trenutni broj predmeta u fazama &nbsp;·&nbsp; ukupno: <?= $ukupno ?></div>
      <div class="an-grid5">
        <?php foreach ($faze as $i => $f): ?>
        <div class="an-box">
          <div class="an-box-lbl"><?= e($kratkeLabel[$i]) ?></div>
          <div class="an-big" style="color:<?= $bojeFaza[$i] ?>"><?= $fazeBrojevi[$f] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <div class="an-label">Prosečno vreme u fazi</div>
      <div class="an-grid5">
        <?php foreach ($faze as $i => $f): ?>
        <div class="an-box">
          <div class="an-box-lbl"><?= e($kratkeLabel[$i]) ?></div>
          <div class="an-mid" style="color:<?= $bojeFaza[$i] ?>"><?= formatSatiAn($fazeVreme[$f]) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Donut grafikon -->
  <div class="card" style="display:flex;flex-direction:column">
    <div class="an-label">Raspodela po fazama</div>
    <div style="position:relative;flex:1;min-height:210px">
      <canvas id="donutChart"></canvas>
    </div>
  </div>

</div>

<!-- ── Donji red ──────────────────────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 300px 210px;gap:14px;align-items:start">

  <!-- Predmeti po istražitelju -->
  <div class="card">
    <div class="an-label"><?= e($naslovGrafikona) ?></div>
    <div style="position:relative;height:<?= count($istraziteljiLabele) > 2 ? 200 : 140 ?>px">
      <canvas id="istraziteljiChart"></canvas>
    </div>
  </div>

  <!-- Statistike -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
    <div class="an-stat">
      <div class="an-stat-v"><?= $ukupno ?></div>
      <div class="an-stat-l">Ukupno predmeta</div>
    </div>
    <div class="an-stat">
      <div class="an-stat-v"><?= formatSatiAn($avgH) ?></div>
      <div class="an-stat-l">Prosečno trajanje</div>
    </div>
    <div class="an-stat">
      <div class="an-stat-v"><?= $zatvoreni ?></div>
      <div class="an-stat-l">Zatvorenih slučajeva</div>
    </div>
    <div class="an-stat">
      <div class="an-stat-v"><?= $efikasnost ?>%</div>
      <div class="an-stat-l">Stopa rešenosti</div>
    </div>
  </div>

  <!-- Legenda i upozorenje -->
  <div class="card">
    <div class="an-label">Legenda faza</div>
    <?php foreach ($faze as $i => $f):
      $pct = $ukupno > 0 ? round($fazeBrojevi[$f] / $ukupno * 100) : 0;
    ?>
    <div class="an-leg">
      <div class="an-leg-dot" style="background:<?= $bojeFaza[$i] ?>"></div>
      <div class="an-leg-nm"><?= e($labeleFaza[$i]) ?></div>
      <div class="an-leg-pct"><?= $pct ?>%</div>
    </div>
    <?php endforeach; ?>

    <?php $uPrikupljanju = $fazeBrojevi['PRIKUPLJANJE_DOKAZA']; ?>
    <?php if ($uPrikupljanju > 0): ?>
    <div class="an-warn">
      <?= $uPrikupljanju ?> <?= $uPrikupljanju === 1 ? 'predmet' : 'predmeta' ?>
      u fazi prikupljanja dokaza <?= $uPrikupljanju === 1 ? 'čeka' : 'čekaju' ?> na aktivaciju analize.
    </div>
    <?php endif; ?>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const YEL  = '#f5c518';
    const BDR  = '#2a2a2a';
    const TXT  = '#6b7280';
    const BG   = '#0e0e0e';

    Chart.defaults.color       = TXT;
    Chart.defaults.borderColor = BDR;
    Chart.defaults.font.family = "'IBM Plex Mono', monospace";
    Chart.defaults.font.size   = 10;

    // ── Donut ────────────────────────────────────────────────────────────────
    new Chart(document.getElementById('donutChart'), {
        type: 'doughnut',
        data: {
            labels:   <?= json_encode($labeleFaza) ?>,
            datasets: [{
                data:            <?= json_encode(array_values($fazeBrojevi)) ?>,
                backgroundColor: <?= json_encode($bojeFaza) ?>,
                borderColor:     BG,
                borderWidth:     3,
                hoverOffset:     6
            }]
        },
        options: {
            responsive:          true,
            maintainAspectRatio: false,
            cutout:              '60%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct   = total > 0 ? Math.round(ctx.raw / total * 100) : 0;
                            return ` ${ctx.raw} predmeta (${pct}%)`;
                        }
                    }
                }
            }
        }
    });

    // ── Predmeti po istražitelju (horizontal bar) ────────────────────────────
    new Chart(document.getElementById('istraziteljiChart'), {
        type: 'bar',
        data: {
            labels:   <?= json_encode($istraziteljiLabele) ?>,
            datasets: [
                {
                    label:           'Aktivni',
                    data:            <?= json_encode($istraziteljiAktivni) ?>,
                    backgroundColor: 'rgba(245,197,24,0.75)',
                    borderColor:     YEL,
                    borderWidth:     1,
                    borderRadius:    2
                },
                {
                    label:           'Zatvoreni',
                    data:            <?= json_encode($istraziteljiZatvoreni) ?>,
                    backgroundColor: 'rgba(34,197,94,0.55)',
                    borderColor:     '#22C55E',
                    borderWidth:     1,
                    borderRadius:    2
                }
            ]
        },
        options: {
            indexAxis:           'y',
            responsive:          true,
            maintainAspectRatio: false,
            scales: {
                x: { grid: { color: BDR }, stacked: true, ticks: { precision: 0 } },
                y: { grid: { color: BDR }, stacked: true }
            },
            plugins: {
                legend: {
                    display:  true,
                    position: 'bottom',
                    labels:   { boxWidth: 10, padding: 12, color: TXT }
                }
            }
        }
    });
})();
</script>
