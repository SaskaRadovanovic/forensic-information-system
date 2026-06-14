<?php
/**
 * layout/header.php — Zaglavlje stranice
 *
 * Sadrži HTML head, CSS (kopiran 1:1 iz index.html),
 * topbar, sidebar navigaciju i otvaranje main sekcije.
 */

// Brojač nepročitanih obaveštenja za badge u sidebaru
$neprocitanaQuery = $conn->prepare("SELECT COUNT(*) as cnt FROM obavestenje WHERE korisnik_id = ? AND procitano = 0");
$neprocitanaQuery->bind_param('i', $_SESSION['user_id']);
$neprocitanaQuery->execute();
$neprocitanaBroj = $neprocitanaQuery->get_result()->fetch_assoc()['cnt'];
$neprocitanaQuery->close();

$korisnik = currentUser();
?>
<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ForenzIS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════ DESIGN SYSTEM ══════════════════════════ */
:root {
  --bg:         #0a0a0a;
  --surface:    #111111;
  --surface-2:  #181818;
  --surface-3:  #202020;
  --border:     #2a2a2a;
  --border-hi:  #3a3a3a;

  --yellow:     #f5c518;
  --yellow-dim: #c9a015;
  --yellow-dark:#2a2200;
  --yellow-glow: rgba(245,197,24,.12);

  --text-1:  #f0ede8;
  --text-2:  #999;
  --text-3:  #555;

  --green:  #22c55e;
  --red:    #ef4444;
  --orange: #f97316;
  --blue:   #3b82f6;
  --purple: #a855f7;

  --mono: 'IBM Plex Mono', monospace;
  --sans: 'IBM Plex Sans', sans-serif;
  --display: 'Bebas Neue', sans-serif;

  --r: 0px;
  --transition: 140ms ease;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--sans);
  font-size: 13px;
  background: var(--bg);
  color: var(--text-1);
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
}

a { color: inherit; text-decoration: none; }

/* ══════════════════════════ BUTTONS ══════════════════════════ */
.btn {
  font-family: var(--mono);
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 1px;
  padding: 9px 18px;
  border: 1px solid transparent;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all var(--transition);
  outline: none;
}

.btn-primary {
  background: var(--yellow);
  color: #000;
  border-color: var(--yellow);
}
.btn-primary:hover { background: var(--yellow-dim); border-color: var(--yellow-dim); }

.btn-outline {
  background: transparent;
  color: var(--yellow);
  border-color: var(--yellow);
}
.btn-outline:hover { background: var(--yellow-dark); }

.btn-ghost {
  background: transparent;
  color: var(--text-2);
  border-color: var(--border-hi);
}
.btn-ghost:hover { color: var(--text-1); border-color: var(--text-2); }

.btn-danger  { background: var(--red);    color: #fff; border-color: var(--red); }
.btn-danger:hover { background: #dc2626; }
.btn-success { background: transparent;   color: var(--green); border-color: var(--green); }
.btn-success:hover { background: rgba(34,197,94,.1); }
.btn-warning { background: transparent;   color: var(--orange); border-color: var(--orange); }
.btn-warning:hover { background: rgba(249,115,22,.1); }

.btn-sm  { padding: 5px 12px; font-size: 10px; }
.btn-full { width: 100%; justify-content: center; }

/* ══════════════════════════ APP SHELL ══════════════════════════ */
#app { display: flex; height: 100vh; flex-direction: column; }

.topbar {
  height: 52px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  padding: 0 20px;
  gap: 20px;
  flex-shrink: 0;
  position: relative;
}

.topbar::after {
  content: '';
  position: absolute;
  bottom: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(90deg, var(--yellow) 0%, transparent 60%);
  opacity: .5;
}

.topbar-logo {
  font-family: var(--display);
  font-size: 26px;
  letter-spacing: 4px;
  color: var(--yellow);
  line-height: 1;
}

.topbar-divider {
  width: 1px;
  height: 20px;
  background: var(--border-hi);
}

.topbar-system {
  font-family: var(--mono);
  font-size: 10px;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: 1px;
}

.spacer { flex: 1; }

.topbar-user {
  font-family: var(--mono);
  font-size: 11px;
  color: var(--text-2);
}

.body-wrap { display: flex; flex: 1; overflow: hidden; }

/* ══════════════════════════ SIDEBAR ══════════════════════════ */
.sidebar {
  width: 210px;
  background: var(--surface);
  border-right: 1px solid var(--border);
  flex-shrink: 0;
  overflow-y: auto;
  padding: 16px 0;
}

.sidebar-section {
  font-family: var(--mono);
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: var(--text-3);
  padding: 16px 18px 6px;
}

.sidebar-section:first-child { padding-top: 4px; }

.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 18px;
  font-family: var(--mono);
  font-size: 12px;
  color: var(--text-2);
  cursor: pointer;
  border-left: 2px solid transparent;
  transition: all var(--transition);
}

.nav-item:hover {
  color: var(--text-1);
  background: var(--surface-2);
}

.nav-item.active {
  color: var(--yellow);
  border-left-color: var(--yellow);
  background: var(--yellow-dark);
}

.nav-badge {
  margin-left: auto;
  background: var(--yellow);
  color: #000;
  font-size: 9px;
  font-weight: 700;
  padding: 1px 5px;
  font-family: var(--mono);
}

/* ══════════════════════════ MAIN CONTENT ══════════════════════════ */
.main { flex: 1; overflow-y: auto; padding: 28px 32px; }

.page-eyebrow {
  font-family: var(--mono);
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: var(--yellow);
  margin-bottom: 6px;
}

.page-title {
  font-family: var(--display);
  font-size: 32px;
  letter-spacing: 2px;
  color: var(--text-1);
  line-height: 1;
  margin-bottom: 24px;
}

/* ══════════════════════════ STATS ══════════════════════════ */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 28px;
}

.stat-card {
  background: var(--surface);
  border: 1px solid var(--border);
  padding: 20px;
  position: relative;
  overflow: hidden;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
  background: var(--accent, var(--border));
}

.stat-card.yellow { --accent: var(--yellow); }
.stat-card.red    { --accent: var(--red);    }
.stat-card.green  { --accent: var(--green);  }
.stat-card.blue   { --accent: var(--blue);   }

.stat-label {
  font-family: var(--mono);
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: var(--text-3);
  margin-bottom: 10px;
}

.stat-value {
  font-family: var(--display);
  font-size: 44px;
  letter-spacing: 2px;
  line-height: 1;
  color: var(--text-1);
}

.stat-card.yellow .stat-value { color: var(--yellow); }
.stat-card.red    .stat-value { color: var(--red);    }
.stat-card.green  .stat-value { color: var(--green);  }

.stat-sub {
  font-family: var(--mono);
  font-size: 10px;
  color: var(--text-3);
  margin-top: 6px;
}

/* ══════════════════════════ CARDS ══════════════════════════ */
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  margin-bottom: 16px;
}

.card-header {
  padding: 14px 20px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card-header h3 {
  font-family: var(--mono);
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: var(--text-2);
}

.card-body { padding: 20px; }

/* ══════════════════════════ TABLES ══════════════════════════ */
table { width: 100%; border-collapse: collapse; }

thead th {
  font-family: var(--mono);
  font-size: 9px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: var(--text-3);
  padding: 10px 16px;
  text-align: left;
  border-bottom: 1px solid var(--border);
  background: var(--surface-2);
}

tbody td {
  font-family: var(--mono);
  font-size: 12px;
  color: var(--text-2);
  padding: 11px 16px;
  border-bottom: 1px solid var(--border);
}

tbody tr:last-child td { border-bottom: none; }

tbody tr {
  transition: background var(--transition);
}

tbody tr:hover td {
  background: var(--surface-2);
  color: var(--text-1);
}

tbody td:first-child {
  color: var(--yellow);
  font-weight: 600;
}

/* ══════════════════════════ BADGES ══════════════════════════ */
.badge {
  font-family: var(--mono);
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 1px;
  padding: 3px 8px;
  border: 1px solid;
  display: inline-block;
}

.badge-yellow  { color: var(--yellow);  border-color: var(--yellow-dim);  background: var(--yellow-dark); }
.badge-green   { color: var(--green);   border-color: rgba(34,197,94,.3);  background: rgba(34,197,94,.07); }
.badge-red     { color: var(--red);     border-color: rgba(239,68,68,.3);  background: rgba(239,68,68,.07); }
.badge-orange  { color: var(--orange);  border-color: rgba(249,115,22,.3); background: rgba(249,115,22,.07); }
.badge-blue    { color: var(--blue);    border-color: rgba(59,130,246,.3); background: rgba(59,130,246,.07); }
.badge-purple  { color: var(--purple);  border-color: rgba(168,85,247,.3); background: rgba(168,85,247,.07); }
.badge-gray    { color: var(--text-2);  border-color: var(--border-hi);    background: var(--surface-2); }

/* ══════════════════════════ PHASE STEPPER ══════════════════════════ */
.phase-stepper {
  display: flex;
  align-items: flex-start;
  margin-bottom: 28px;
  background: var(--surface);
  border: 1px solid var(--border);
  padding: 20px 24px;
  gap: 0;
}

.phase-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
  gap: 8px;
}

.phase-circle {
  width: 32px; height: 32px;
  border: 1px solid var(--border-hi);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--mono);
  font-size: 11px;
  font-weight: 600;
  color: var(--text-3);
  background: var(--surface-2);
  transition: all var(--transition);
}

.phase-step.done .phase-circle {
  background: rgba(34,197,94,.1);
  border-color: var(--green);
  color: var(--green);
}

.phase-step.active .phase-circle {
  background: var(--yellow-dark);
  border-color: var(--yellow);
  color: var(--yellow);
}

.phase-step-label {
  font-family: var(--mono);
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--text-3);
  text-align: center;
  max-width: 80px;
}

.phase-step.done .phase-step-label   { color: var(--green); }
.phase-step.active .phase-step-label { color: var(--yellow); }

.phase-connector {
  flex: 1;
  height: 1px;
  background: var(--border);
  margin-top: 15px;
}

.phase-connector.done { background: var(--green); }

/* ══════════════════════════ FILTERS ══════════════════════════ */
.filter-bar {
  display: flex;
  gap: 8px;
  margin-bottom: 16px;
  flex-wrap: wrap;
  align-items: flex-end;
}

.filter-bar input { flex: 1; min-width: 180px; }

/* ══════════════════════════ FORMS ══════════════════════════ */
.form-group { margin-bottom: 14px; }
.form-group label {
  display: block;
  font-family: var(--mono);
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  color: var(--text-3);
  margin-bottom: 6px;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  background: var(--surface-2);
  border: 1px solid var(--border);
  color: var(--text-1);
  font-family: var(--mono);
  font-size: 13px;
  padding: 9px 12px;
  outline: none;
  transition: border-color var(--transition);
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  border-color: var(--yellow);
  background: var(--surface-3);
}

.form-group textarea { resize: vertical; min-height: 70px; }

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-grid .full { grid-column: 1 / -1; }

.form-section-title {
  grid-column: 1 / -1;
  font-family: var(--mono);
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: var(--yellow);
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border);
  margin-top: 8px;
}

/* ══════════════════════════ TABS ══════════════════════════ */
.tabs {
  display: flex;
  border-bottom: 1px solid var(--border);
  margin-bottom: 20px;
}

.tab {
  font-family: var(--mono);
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--text-3);
  padding: 10px 18px;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  transition: all var(--transition);
}

.tab:hover { color: var(--text-1); }

.tab.active {
  color: var(--yellow);
  border-bottom-color: var(--yellow);
}

/* ══════════════════════════ INFO GRID ══════════════════════════ */
.info-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1px;
  background: var(--border);
  border: 1px solid var(--border);
  margin-bottom: 20px;
}

.info-item {
  background: var(--surface);
  padding: 14px 18px;
}

.info-label {
  font-family: var(--mono);
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  color: var(--text-3);
  margin-bottom: 5px;
}

.info-value {
  font-family: var(--mono);
  font-size: 13px;
  color: var(--text-1);
  font-weight: 500;
}

/* ══════════════════════════ TIMELINE ══════════════════════════ */
.timeline { list-style: none; }

.timeline li {
  display: flex;
  gap: 16px;
  padding: 12px 0;
  border-bottom: 1px solid var(--border);
  position: relative;
}

.timeline li:last-child { border-bottom: none; }

.tl-ts {
  font-family: var(--mono);
  font-size: 10px;
  color: var(--text-3);
  min-width: 130px;
  line-height: 1.6;
}

.tl-dot {
  width: 8px; height: 8px;
  background: var(--yellow);
  border: none;
  margin-top: 4px;
  flex-shrink: 0;
}

.tl-body {
  font-family: var(--mono);
  font-size: 12px;
  color: var(--text-2);
}

.tl-body strong { color: var(--text-1); }

/* ══════════════════════════ ALERT BOX ══════════════════════════ */
.alert {
  padding: 14px 18px;
  border-left: 2px solid;
  margin-bottom: 16px;
  font-family: var(--mono);
  font-size: 12px;
}

.alert-yellow { border-color: var(--yellow); background: var(--yellow-dark); color: var(--yellow); }
.alert-green  { border-color: var(--green);  background: rgba(34,197,94,.07); color: var(--green); }
.alert-red    { border-color: var(--red);    background: rgba(239,68,68,.07);  color: var(--red); }

/* ══════════════════════════ NOTIFICATION ITEMS ══════════════════════════ */
.notif-item {
  padding: 14px 18px;
  border-bottom: 1px solid var(--border);
  transition: background var(--transition);
}

.notif-item:hover { background: var(--surface-2); }
.notif-item.unread { border-left: 2px solid var(--yellow); }
.notif-item:not(.unread) { border-left: 2px solid transparent; }

.notif-title {
  font-family: var(--mono);
  font-size: 12px;
  color: var(--text-1);
  margin-bottom: 4px;
}

.notif-body {
  font-family: var(--sans);
  font-size: 12px;
  color: var(--text-2);
  line-height: 1.5;
}

.notif-time {
  font-family: var(--mono);
  font-size: 10px;
  color: var(--text-3);
  margin-top: 5px;
}

/* ══════════════════════════ BREADCRUMB ══════════════════════════ */
.page-breadcrumb {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
}

/* ══════════════════════════ ACTION BAR ══════════════════════════ */
.action-bar {
  display: flex;
  gap: 8px;
  align-items: center;
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid var(--border);
}

/* ══════════════════════════ EMPTY STATE ══════════════════════════ */
.empty-state {
  text-align: center;
  padding: 40px;
  font-family: var(--mono);
  font-size: 11px;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: 2px;
}

/* ══════════════════════════ TAB CONTENT ══════════════════════════ */
.tab-content { display: none; }
.tab-content.active { display: block; }
</style>
</head>
<body>

<!-- ══════════════════════════ APP SHELL ══════════════════════════ -->
<div id="app">

<!-- Topbar -->
<div class="topbar">
    <div class="topbar-logo">ForenzIS</div>
    <div class="topbar-divider"></div>
    <div class="topbar-system">Forenzički informacioni sistem</div>
    <div class="spacer"></div>
    <div class="topbar-user">
        <?= e($korisnik['ime'] . ' ' . $korisnik['prezime']) ?> · <span class="badge <?= ulogaBadge($korisnik['uloga']) ?>"><?= e(ulogaLabel($korisnik['uloga'])) ?></span>
    </div>
    <a href="?page=logout" class="btn btn-ghost btn-sm">Odjavi se</a>
</div>

<!-- Body wrap: sidebar + main -->
<div class="body-wrap">

<!-- Sidebar navigacija — prikazuje se na osnovu uloge korisnika -->
<div class="sidebar">
<?php
// ─── Sidebar stavke po ulozi ────────────────────────────────────────────────
$uloga = $_SESSION['uloga'];

if ($uloga === 'ADMINISTRATOR'):
?>
    <div class="sidebar-section">Administracija</div>
    <a href="?page=dashboard" class="nav-item <?= $page === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
    <a href="?page=izvestaji" class="nav-item <?= $page === 'izvestaji' ? 'active' : '' ?>">Izveštaji</a>

    <div class="sidebar-section">Predmeti</div>
    <a href="?page=predmeti" class="nav-item <?= $page === 'predmeti' || strpos($page, 'predmet-') === 0 ? 'active' : '' ?>">Predmeti</a>

    <div class="sidebar-section">Forenzika</div>
    <a href="?page=dokazi" class="nav-item <?= $page === 'dokazi' || strpos($page, 'dokaz-') === 0 ? 'active' : '' ?>">Dokazi</a>
    <a href="?page=analize" class="nav-item <?= $page === 'analize' || strpos($page, 'analiza-') === 0 ? 'active' : '' ?>">Analize</a>
    <a href="?page=dokumentacija" class="nav-item <?= $page === 'dokumentacija' || strpos($page, 'dokument-') === 0 ? 'active' : '' ?>">Dokumentacija</a>
    <a href="?page=dokazi-zahtevi" class="nav-item <?= $page === 'dokazi-zahtevi' ? 'active' : '' ?>">Zahtevi za dokaze</a>

    <div class="sidebar-section">Sistem</div>
    <a href="?page=tagovi" class="nav-item <?= $page === 'tagovi' || $page === 'tag-novi' ? 'active' : '' ?>">Tagovi</a>
    <a href="?page=obavestenja" class="nav-item <?= $page === 'obavestenja' ? 'active' : '' ?>">Obaveštenja<?php if ($neprocitanaBroj > 0): ?> <span class="nav-badge"><?= $neprocitanaBroj ?></span><?php endif; ?></a>

<?php elseif ($uloga === 'ISTRAZITELJ'): ?>
    <div class="sidebar-section">Istraga</div>
    <a href="?page=dashboard" class="nav-item <?= $page === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
    <a href="?page=predmeti" class="nav-item <?= $page === 'predmeti' || strpos($page, 'predmet-') === 0 ? 'active' : '' ?>">Predmeti</a>

    <div class="sidebar-section">Forenzika</div>
    <a href="?page=analize" class="nav-item <?= $page === 'analize' || strpos($page, 'analiza-') === 0 ? 'active' : '' ?>">Analize</a>
    <a href="?page=dokumentacija" class="nav-item <?= $page === 'dokumentacija' || strpos($page, 'dokument-') === 0 ? 'active' : '' ?>">Dokumentacija</a>
    <a href="?page=dokazi" class="nav-item <?= $page === 'dokazi' || strpos($page, 'dokaz-') === 0 ? 'active' : '' ?>">Dokazi</a>

    <div class="sidebar-section">Sistem</div>
    <a href="?page=obavestenja" class="nav-item <?= $page === 'obavestenja' ? 'active' : '' ?>">Obaveštenja<?php if ($neprocitanaBroj > 0): ?> <span class="nav-badge"><?= $neprocitanaBroj ?></span><?php endif; ?></a>

<?php elseif ($uloga === 'TEHNICAR'): ?>
    <div class="sidebar-section">Dokazi</div>
    <a href="?page=dokazi" class="nav-item <?= $page === 'dokazi' || strpos($page, 'dokaz-') === 0 ? 'active' : '' ?>">Evidencija dokaza</a>
    <a href="?page=dokazi-zahtevi" class="nav-item <?= $page === 'dokazi-zahtevi' ? 'active' : '' ?>">Zahtevi za dokaze</a>

    <div class="sidebar-section">Dokumenti</div>
    <a href="?page=dokumentacija" class="nav-item <?= $page === 'dokumentacija' || strpos($page, 'dokument-') === 0 ? 'active' : '' ?>">Dokumentacija</a>

<?php elseif ($uloga === 'VESTAK'): ?>
    <div class="sidebar-section">Analize</div>
    <a href="?page=analize" class="nav-item <?= $page === 'analize' || strpos($page, 'analiza-') === 0 ? 'active' : '' ?>">Moje analize</a>

    <div class="sidebar-section">Sistem</div>
    <a href="?page=obavestenja" class="nav-item <?= $page === 'obavestenja' ? 'active' : '' ?>">Obaveštenja<?php if ($neprocitanaBroj > 0): ?> <span class="nav-badge"><?= $neprocitanaBroj ?></span><?php endif; ?></a>

<?php endif; ?>
</div>

<!-- Glavni sadržaj -->
<div class="main">
<?php showFlash(); ?>
