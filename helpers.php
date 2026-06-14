<?php
/**
 * helpers.php — Pomoćne funkcije za prikaz i formatiranje
 *
 * Escaping, flash poruke, badge klase, formatiranje datuma,
 * i labele za sve enum vrednosti u sistemu.
 */

// ─── Escaping ───────────────────────────────────────────────────────────────

/** Skraćenica za htmlspecialchars — zaštita od XSS napada */
function e(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// ─── Flash poruke ───────────────────────────────────────────────────────────

/** Postavi uspešnu flash poruku u sesiju */
function flashSuccess(string $msg): void
{
    $_SESSION['flash_success'] = $msg;
}

/** Postavi grešku kao flash poruku u sesiju */
function flashError(string $msg): void
{
    $_SESSION['flash_error'] = $msg;
}

/** Renderuj flash poruke (success = alert-green, error = alert-red) i obriši iz sesije */
function showFlash(): void
{
    if (!empty($_SESSION['flash_success'])) {
        echo '<div class="alert alert-green">' . e($_SESSION['flash_success']) . '</div>';
        unset($_SESSION['flash_success']);
    }
    if (!empty($_SESSION['flash_error'])) {
        echo '<div class="alert alert-red">' . e($_SESSION['flash_error']) . '</div>';
        unset($_SESSION['flash_error']);
    }
}

// ─── Badge klase po statusu ─────────────────────────────────────────────────

/** Mapira status na odgovarajuću CSS klasu za badge */
function badgeClass(string $status): string
{
    $mapa = [
        // Status predmeta
        'AKTIVAN'            => 'badge-green',
        'ZATVOREN'           => 'badge-gray',
        // Status dokaza
        'PRIJEM'             => 'badge-blue',
        'U_SKLADISTU'        => 'badge-green',
        'IZDATO_ZA_ANALIZU'  => 'badge-yellow',
        'VRACENO'            => 'badge-purple',
        'KOMPROMITOVAN'       => 'badge-orange',
        'ARHIVIRANO'         => 'badge-gray',
        // Status analize
        'KREIRAN'            => 'badge-blue',
        'DODELJEN'           => 'badge-blue',
        'U_TOKU'             => 'badge-yellow',
        'ZAVRSEN'            => 'badge-green',
        'PREKORACEN'         => 'badge-red',
        'ODBIJEN'            => 'badge-red',
        // Status zahteva za dokaz
        'NA_CEKANJU'         => 'badge-yellow',
        'ODOBREN'            => 'badge-green',
        // Status dokumenta
        'ARHIVIRAN'          => 'badge-gray',
    ];
    return $mapa[$status] ?? 'badge-gray';
}

/** Mapira enum vrednost statusa na čitljiv srpski tekst */
function badgeLabel(string $status): string
{
    $mapa = [
        // Status predmeta
        'AKTIVAN'            => 'Aktivan',
        'ZATVOREN'           => 'Zatvoren',
        // Status dokaza
        'PRIJEM'             => 'Prijem',
        'U_SKLADISTU'        => 'U skladištu',
        'IZDATO_ZA_ANALIZU'  => 'Izdato za analizu',
        'VRACENO'            => 'Vraćeno',
        'KOMPROMITOVAN'       => 'Kompromitovan',
        'ARHIVIRANO'         => 'Arhivirano',
        // Status analize
        'KREIRAN'            => 'Kreiran',
        'DODELJEN'           => 'Dodeljen',
        'U_TOKU'             => 'U toku',
        'ZAVRSEN'            => 'Završen',
        'PREKORACEN'         => 'Prekoračen',
        'ODBIJEN'            => 'Odbijen',
        // Status zahteva za dokaz
        'NA_CEKANJU'         => 'Na čekanju',
        'ODOBREN'            => 'Odobren',
        // Status dokumenta
        'ARHIVIRAN'          => 'Arhiviran',
    ];
    return $mapa[$status] ?? $status;
}

// ─── Formatiranje datuma ────────────────────────────────────────────────────

/** Formatira datum u srpski format dd.mm.YYYY */
function formatDatum(?string $datetime): string
{
    if (!$datetime) return '—';
    return date('d.m.Y', strtotime($datetime));
}

/** Formatira datum i vreme u srpski format dd.mm.YYYY HH:MM */
function formatDatumVreme(?string $datetime): string
{
    if (!$datetime) return '—';
    return date('d.m.Y H:i', strtotime($datetime));
}

// ─── Labele za tip dokaza ───────────────────────────────────────────────────

/** Mapira enum tip dokaza na čitljiv srpski tekst */
function tipDokazaLabel(string $tip): string
{
    $mapa = [
        'BIOLOSKI_TRAG' => 'Biološki trag',
        'ORUZJE'        => 'Oružje',
        'DOKUMENT'      => 'Dokument',
        'ODECA'         => 'Odeća',
        'UZORAK'        => 'Uzorak',
    ];
    return $mapa[$tip] ?? $tip;
}

/** Mapira tip dokaza na CSS klasu za badge */
function tipDokazaBadge(string $tip): string
{
    $mapa = [
        'BIOLOSKI_TRAG' => 'badge-red',
        'ORUZJE'        => 'badge-orange',
        'DOKUMENT'      => 'badge-blue',
        'ODECA'         => 'badge-purple',
        'UZORAK'        => 'badge-yellow',
    ];
    return $mapa[$tip] ?? 'badge-gray';
}

// ─── Labele za fazu predmeta ────────────────────────────────────────────────

/** Mapira enum faze predmeta na čitljiv srpski tekst */
function fazaLabel(string $faza): string
{
    $mapa = [
        'OTVOREN_SLUCAJ'       => 'Otvoren slučaj',
        'PRIKUPLJANJE_DOKAZA'  => 'Prikupljanje dokaza',
        'ANALIZA_DOKAZA'       => 'Analiza dokaza',
        'DONOSENJE_ZAKLJUCKA'  => 'Donošenje zaključka',
        'ZATVOREN_SLUCAJ'      => 'Zatvoren slučaj',
    ];
    return $mapa[$faza] ?? $faza;
}

/** Mapira fazu predmeta na CSS klasu za badge */
function fazaBadge(string $faza): string
{
    $mapa = [
        'OTVOREN_SLUCAJ'       => 'badge-blue',
        'PRIKUPLJANJE_DOKAZA'  => 'badge-yellow',
        'ANALIZA_DOKAZA'       => 'badge-orange',
        'DONOSENJE_ZAKLJUCKA'  => 'badge-purple',
        'ZATVOREN_SLUCAJ'      => 'badge-green',
    ];
    return $mapa[$faza] ?? 'badge-gray';
}

// ─── Labele za tip analize ──────────────────────────────────────────────────

/** Mapira enum tip analize na čitljiv srpski tekst */
function tipAnalizeLabel(string $tip): string
{
    $mapa = [
        'BALISTICKA'     => 'Balistička',
        'DNK'            => 'DNK',
        'DIGITALNA'      => 'Digitalna',
        'HEMIJSKA'       => 'Hemijska',
        'TOKSIKOLOSKA'   => 'Toksikološka',
        'DOKUMENTOLOSKA' => 'Dokumentološka',
        'DRUGA'          => 'Druga',
    ];
    return $mapa[$tip] ?? $tip;
}

// ─── Labele za uloge korisnika ──────────────────────────────────────────────

/** Mapira enum uloge na čitljiv srpski tekst */
function ulogaLabel(string $uloga): string
{
    $mapa = [
        'ADMINISTRATOR' => 'Administrator',
        'ISTRAZITELJ'   => 'Istražitelj',
        'TEHNICAR'      => 'Tehničar',
        'VESTAK'        => 'Veštak',
    ];
    return $mapa[$uloga] ?? $uloga;
}

/** Mapira ulogu korisnika na CSS klasu za badge */
function ulogaBadge(string $uloga): string
{
    $mapa = [
        'ADMINISTRATOR' => 'badge-red',
        'ISTRAZITELJ'   => 'badge-blue',
        'TEHNICAR'      => 'badge-green',
        'VESTAK'        => 'badge-purple',
    ];
    return $mapa[$uloga] ?? 'badge-gray';
}
