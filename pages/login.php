<?php
/**
 * pages/login.php — Stranica za prijavu
 *
 * Fullscreen login forma, bez sidebara/headera.
 * Dizajn preuzet iz index.html (login-screen sekcija).
 */
?>
<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ForenzIIS — Prijava</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
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
  --text-1:  #f0ede8;
  --text-2:  #999;
  --text-3:  #555;
  --red:     #ef4444;
  --green:   #22c55e;
  --mono: 'IBM Plex Mono', monospace;
  --sans: 'IBM Plex Sans', sans-serif;
  --display: 'Bebas Neue', sans-serif;
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

/* Login ekran */
.login-screen {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  background: var(--bg);
  background-image:
    repeating-linear-gradient(
      45deg,
      transparent,
      transparent 40px,
      rgba(245,197,24,.018) 40px,
      rgba(245,197,24,.018) 41px
    );
}

.login-box {
  width: 380px;
  border: 1px solid var(--border-hi);
  background: var(--surface);
}

.login-header {
  padding: 28px 32px 22px;
  border-bottom: 1px solid var(--border);
  position: relative;
  overflow: hidden;
}

.login-header::before {
  content: '';
  position: absolute;
  bottom: 0; left: 0; right: 0;
  height: 2px;
  background: var(--yellow);
}

.login-logo {
  font-family: var(--display);
  font-size: 36px;
  letter-spacing: 4px;
  color: var(--yellow);
  line-height: 1;
}

.login-tagline {
  font-family: var(--mono);
  font-size: 10px;
  color: var(--text-3);
  margin-top: 6px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.login-body { padding: 28px 32px; }

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

.form-group input {
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

.form-group input:focus {
  border-color: var(--yellow);
  background: var(--surface-3);
}

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
.btn-primary:hover { background: var(--yellow-dim); }
.btn-full { width: 100%; justify-content: center; }

/* Flash poruke */
.alert {
  padding: 14px 18px;
  border-left: 2px solid;
  margin-bottom: 16px;
  font-family: var(--mono);
  font-size: 12px;
}
.alert-red { border-color: var(--red); background: rgba(239,68,68,.07); color: var(--red); }
</style>
</head>
<body>

<div class="login-screen">
    <div class="login-box">
        <div class="login-header">
            <div class="login-logo">ForenzIIS</div>
            <div class="login-tagline">Sistem za upravljanje forenzičkim dokazima i istragama</div>
        </div>
        <div class="login-body">
            <?php showFlash(); ?>
            <form method="POST" action="?page=login">
                <div class="form-group">
                    <label>Email adresa</label>
                    <input type="email" name="email" placeholder="email@fis.rs" required>
                </div>
                <div class="form-group">
                    <label>Lozinka</label>
                    <input type="password" name="lozinka" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary btn-full" style="margin-top: 8px;">Prijavi se</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
