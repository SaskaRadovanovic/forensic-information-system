<?php
/**
 * pages/izvestaji.php — Stranica izveštaja
 *
 * 4 sekcije sa agregatnim upitima:
 * 1. Analize po predmetu
 * 2. Analize sa kašnjenjem
 * 3. Statistika po tipu analize
 * 4. Opterećenje veštaka
 */
requireRole('ISTRAZITELJ', 'ADMINISTRATOR');
?>

<div class="page-eyebrow">Administracija</div>
<div class="page-title">Izveštaji</div>

<!-- 1. Analize po predmetu -->
<div class="card">
    <div class="card-header"><h3>Analize po predmetu</h3></div>
    <div class="card-body" style="padding:0;">
        <?php
        $res = $conn->query("
            SELECT p.naziv,
                   COUNT(z.id) AS ukupno,
                   SUM(CASE WHEN z.status = 'ZAVRSEN' THEN 1 ELSE 0 END) AS zavrsene,
                   SUM(CASE WHEN z.status IN ('DODELJEN','U_TOKU') THEN 1 ELSE 0 END) AS u_toku,
                   SUM(CASE WHEN z.status = 'PREKORACEN' THEN 1 ELSE 0 END) AS prekoracene
            FROM predmet p
            LEFT JOIN zahtev_za_analizu z ON z.predmet_id = p.id
            GROUP BY p.id, p.naziv
            HAVING ukupno > 0
            ORDER BY ukupno DESC
        ");
        ?>
        <?php if ($res->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Predmet</th>
                    <th>Ukupno</th>
                    <th>Završene</th>
                    <th>U toku</th>
                    <th>Prekoračene</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $res->fetch_assoc()): ?>
                <tr>
                    <td><?= e($row['naziv']) ?></td>
                    <td><?= $row['ukupno'] ?></td>
                    <td style="color:var(--green);"><?= $row['zavrsene'] ?></td>
                    <td style="color:var(--yellow);"><?= $row['u_toku'] ?></td>
                    <td style="color:var(--red);"><?= $row['prekoracene'] ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Nema podataka</div>
        <?php endif; ?>
    </div>
</div>

<!-- 2. Analize sa kašnjenjem -->
<div class="card">
    <div class="card-header"><h3>Analize sa kašnjenjem</h3></div>
    <div class="card-body" style="padding:0;">
        <?php
        $res = $conn->query("
            SELECT z.id, z.tip_analize, z.rok, z.status,
                   d.sifra_dokaza,
                   k.ime AS vestak_ime, k.prezime AS vestak_prezime
            FROM zahtev_za_analizu z
            JOIN dokaz d ON d.id = z.dokaz_id
            LEFT JOIN korisnik k ON k.id = z.vestak_id
            WHERE z.status = 'PREKORACEN'
            ORDER BY z.rok ASC
        ");
        ?>
        <?php if ($res->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Dokaz</th>
                    <th>Tip</th>
                    <th>Veštak</th>
                    <th>Rok (istekao)</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $res->fetch_assoc()): ?>
                <tr>
                    <td><a href="?page=analiza-detalji&id=<?= $row['id'] ?>">#<?= $row['id'] ?></a></td>
                    <td><?= e($row['sifra_dokaza']) ?></td>
                    <td><span class="badge badge-blue"><?= e(tipAnalizeLabel($row['tip_analize'])) ?></span></td>
                    <td><?= $row['vestak_ime'] ? e($row['vestak_ime'] . ' ' . $row['vestak_prezime']) : '—' ?></td>
                    <td style="color:var(--red);"><?= formatDatum($row['rok']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Nema prekoračenih analiza</div>
        <?php endif; ?>
    </div>
</div>

<!-- 3. Statistika po tipu analize -->
<div class="card">
    <div class="card-header"><h3>Statistika po tipu analize</h3></div>
    <div class="card-body" style="padding:0;">
        <?php
        $res = $conn->query("
            SELECT z.tip_analize,
                   COUNT(z.id) AS ukupno,
                   AVG(DATEDIFF(COALESCE(r.datum_unosa, NOW()), z.datum_pocetka)) AS prosecno_trajanje
            FROM zahtev_za_analizu z
            LEFT JOIN rezultat_analize r ON r.zahtev_id = z.id
            WHERE z.datum_pocetka IS NOT NULL
            GROUP BY z.tip_analize
            ORDER BY ukupno DESC
        ");
        ?>
        <?php if ($res->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Tip analize</th>
                    <th>Ukupno</th>
                    <th>Prosečno trajanje (dana)</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $res->fetch_assoc()): ?>
                <tr>
                    <td><span class="badge badge-blue"><?= e(tipAnalizeLabel($row['tip_analize'])) ?></span></td>
                    <td><?= $row['ukupno'] ?></td>
                    <td><?= $row['prosecno_trajanje'] !== null ? round($row['prosecno_trajanje'], 1) : '—' ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Nema podataka</div>
        <?php endif; ?>
    </div>
</div>

<!-- 4. Opterećenje veštaka -->
<div class="card">
    <div class="card-header"><h3>Opterećenje veštaka</h3></div>
    <div class="card-body" style="padding:0;">
        <?php
        $res = $conn->query("
            SELECT k.ime, k.prezime, v.specijalnost,
                   COUNT(z.id) AS ukupno,
                   SUM(CASE WHEN z.status IN ('DODELJEN','U_TOKU') THEN 1 ELSE 0 END) AS aktivne,
                   SUM(CASE WHEN z.status = 'ZAVRSEN' THEN 1 ELSE 0 END) AS zavrsene
            FROM vestak v
            JOIN korisnik k ON k.id = v.id_korisnik
            LEFT JOIN zahtev_za_analizu z ON z.vestak_id = v.id_korisnik
            GROUP BY v.id_korisnik, k.ime, k.prezime, v.specijalnost
            ORDER BY aktivne DESC
        ");
        ?>
        <?php if ($res->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Veštak</th>
                    <th>Specijalnost</th>
                    <th>Aktivne</th>
                    <th>Završene</th>
                    <th>Ukupno</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $res->fetch_assoc()): ?>
                <tr>
                    <td><?= e($row['ime'] . ' ' . $row['prezime']) ?></td>
                    <td><?= e($row['specijalnost'] ?: '—') ?></td>
                    <td style="color:var(--yellow);"><?= $row['aktivne'] ?></td>
                    <td style="color:var(--green);"><?= $row['zavrsene'] ?></td>
                    <td><?= $row['ukupno'] ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Nema podataka</div>
        <?php endif; ?>
    </div>
</div>
