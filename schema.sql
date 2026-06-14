-- ============================================================================
-- ForenzIS — Šema baze podataka (MySQL)
-- Prevedeno iz Prisma šeme. Redosled poštuje FK zavisnosti.
-- ============================================================================

-- Brisanje postojećih tabela (obrnut redosled od kreiranja)
DROP TABLE IF EXISTS obavestenje;
DROP TABLE IF EXISTS istorija_izmene_zahteva;
DROP TABLE IF EXISTS log_brisanja_zahteva;
DROP TABLE IF EXISTS istorija_statusa_analize;
DROP TABLE IF EXISTS istorija_dodele;
DROP TABLE IF EXISTS rezultat_analize;
DROP TABLE IF EXISTS zahtev_za_analizu;
DROP TABLE IF EXISTS zahtev_za_dokaz;
DROP TABLE IF EXISTS uzorak;
DROP TABLE IF EXISTS odeca;
DROP TABLE IF EXISTS dokument_dokaz;
DROP TABLE IF EXISTS oruzje;
DROP TABLE IF EXISTS bioloski_trag;
DROP TABLE IF EXISTS lanac_cuvanja;
DROP TABLE IF EXISTS dokaz;
DROP TABLE IF EXISTS dokument_arhiva;
DROP TABLE IF EXISTS dokument_tag;
DROP TABLE IF EXISTS tag;
DROP TABLE IF EXISTS pravo_pristupa;
DROP TABLE IF EXISTS metapodatak;
DROP TABLE IF EXISTS dokument;
DROP TABLE IF EXISTS predmet;
DROP TABLE IF EXISTS vestak;
DROP TABLE IF EXISTS istrazitelj;
DROP TABLE IF EXISTS tehnicar_za_dokaze;
DROP TABLE IF EXISTS korisnik;

-- ─── Korisnik ───────────────────────────────────────────────────────────────
CREATE TABLE korisnik (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    ime             VARCHAR(255) NOT NULL,
    prezime         VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    kor_ime         VARCHAR(255) NULL UNIQUE,
    lozinka_hash    VARCHAR(255) NOT NULL,
    uloga           ENUM('ADMINISTRATOR','ISTRAZITELJ','TEHNICAR','VESTAK') NOT NULL,
    aktivan         BOOLEAN NOT NULL DEFAULT TRUE,
    datum_kreiranja DATETIME NOT NULL DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Tehničar za dokaze (ISA podtip Korisnika) ─────────────────────────────
CREATE TABLE tehnicar_za_dokaze (
    id_korisnik INT PRIMARY KEY,
    id_td       VARCHAR(255) NOT NULL UNIQUE,
    odeljenje   VARCHAR(255) NULL,
    FOREIGN KEY (id_korisnik) REFERENCES korisnik(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Istražitelj (ISA podtip Korisnika) ─────────────────────────────────────
CREATE TABLE istrazitelj (
    id_korisnik  INT PRIMARY KEY,
    broj_znacke  VARCHAR(255) NOT NULL UNIQUE,
    odeljenje    VARCHAR(255) NULL,
    FOREIGN KEY (id_korisnik) REFERENCES korisnik(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Veštak (ISA podtip Korisnika) ──────────────────────────────────────────
CREATE TABLE vestak (
    id_korisnik   INT PRIMARY KEY,
    id_vestak     VARCHAR(255) NOT NULL UNIQUE,
    specijalnost  VARCHAR(255) NULL,
    sertifikat_br VARCHAR(255) NULL,
    FOREIGN KEY (id_korisnik) REFERENCES korisnik(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Predmet ────────────────────────────────────────────────────────────────
CREATE TABLE predmet (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    naziv            VARCHAR(255) NOT NULL,
    opis             TEXT NULL,
    status           ENUM('AKTIVAN','ZATVOREN') NOT NULL DEFAULT 'AKTIVAN',
    faza             ENUM('OTVOREN_SLUCAJ','PRIKUPLJANJE_DOKAZA','ANALIZA_DOKAZA','DONOSENJE_ZAKLJUCKA','ZATVOREN_SLUCAJ') NOT NULL DEFAULT 'OTVOREN_SLUCAJ',
    datum_otvaranja  DATETIME NOT NULL DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Dokument ───────────────────────────────────────────────────────────────
CREATE TABLE dokument (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    naziv            VARCHAR(255) NOT NULL,
    putanja          VARCHAR(500) NOT NULL,
    verzija          INT NOT NULL DEFAULT 1,
    status           ENUM('AKTIVAN','ARHIVIRAN') NOT NULL DEFAULT 'AKTIVAN',
    datum_kreiranja  DATETIME NOT NULL DEFAULT NOW(),
    predmet_id       INT NOT NULL,
    autor_id         INT NOT NULL,
    FOREIGN KEY (predmet_id) REFERENCES predmet(id),
    FOREIGN KEY (autor_id) REFERENCES korisnik(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Metapodatak ────────────────────────────────────────────────────────────
CREATE TABLE metapodatak (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    kljuc       VARCHAR(255) NOT NULL,
    vrednost    TEXT NOT NULL,
    dokument_id INT NOT NULL,
    FOREIGN KEY (dokument_id) REFERENCES dokument(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Pravo pristupa ─────────────────────────────────────────────────────────
CREATE TABLE pravo_pristupa (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    nivo_pristupa  VARCHAR(50) NOT NULL,
    datum_dodele   DATETIME NOT NULL DEFAULT NOW(),
    korisnik_id    INT NOT NULL,
    dokument_id    INT NOT NULL,
    FOREIGN KEY (korisnik_id) REFERENCES korisnik(id),
    FOREIGN KEY (dokument_id) REFERENCES dokument(id) ON DELETE CASCADE,
    UNIQUE KEY uq_korisnik_dokument (korisnik_id, dokument_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Tag ────────────────────────────────────────────────────────────────────
CREATE TABLE tag (
    id    INT AUTO_INCREMENT PRIMARY KEY,
    naziv VARCHAR(255) NOT NULL UNIQUE,
    boja  VARCHAR(20) NOT NULL DEFAULT '#FACC15'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Dokument-Tag (M:N veza) ────────────────────────────────────────────────
CREATE TABLE dokument_tag (
    dokument_id INT NOT NULL,
    tag_id      INT NOT NULL,
    PRIMARY KEY (dokument_id, tag_id),
    FOREIGN KEY (dokument_id) REFERENCES dokument(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tag(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Dokument arhiva (istorija verzija) ─────────────────────────────────────
CREATE TABLE dokument_arhiva (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    putanja_stara_verzija VARCHAR(500) NOT NULL,
    verzija               INT NOT NULL,
    razlog_izmene         TEXT NULL,
    datum_arhiviranja     DATETIME NOT NULL DEFAULT NOW(),
    dokument_id           INT NOT NULL,
    sacuvao_id            INT NOT NULL,
    FOREIGN KEY (dokument_id) REFERENCES dokument(id) ON DELETE CASCADE,
    FOREIGN KEY (sacuvao_id) REFERENCES korisnik(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Dokaz ──────────────────────────────────────────────────────────────────
CREATE TABLE dokaz (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    sifra_dokaza           VARCHAR(50) NOT NULL UNIQUE,
    naziv                  VARCHAR(255) NOT NULL,
    opis                   TEXT NULL,
    tip_dokaza             ENUM('BIOLOSKI_TRAG','ORUZJE','DOKUMENT','ODECA','UZORAK') NOT NULL,
    datum_prijema          DATETIME NOT NULL,
    datum_pronalaska       DATETIME NULL,
    lokacija_pronalaska    VARCHAR(255) NULL,
    lokacija_skladistenja  VARCHAR(255) NULL,
    status                 ENUM('PRIJEM','U_SKLADISTU','IZDATO_ZA_ANALIZU','VRACENO','KOMPROMITOVAN','ARHIVIRANO') NOT NULL DEFAULT 'PRIJEM',
    predmet_id             INT NOT NULL,
    tehnicar_id            INT NOT NULL,
    FOREIGN KEY (predmet_id) REFERENCES predmet(id),
    FOREIGN KEY (tehnicar_id) REFERENCES tehnicar_za_dokaze(id_korisnik)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Lanac čuvanja ──────────────────────────────────────────────────────────
CREATE TABLE lanac_cuvanja (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    akcija       VARCHAR(255) NOT NULL,
    datum_vreme  DATETIME NOT NULL,
    napomena     TEXT NULL,
    dokaz_id     INT NOT NULL,
    tehnicar_id  INT NOT NULL,
    FOREIGN KEY (dokaz_id) REFERENCES dokaz(id) ON DELETE CASCADE,
    FOREIGN KEY (tehnicar_id) REFERENCES tehnicar_za_dokaze(id_korisnik)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── ISA podtipovi dokaza ───────────────────────────────────────────────────

-- Biološki trag
CREATE TABLE bioloski_trag (
    id_dokaz          INT PRIMARY KEY,
    vrsta_traga       VARCHAR(255) NULL,
    nacin_uzorkovanja VARCHAR(255) NULL,
    uslovi_cuvanja    VARCHAR(255) NULL,
    kolicina          VARCHAR(255) NULL,
    FOREIGN KEY (id_dokaz) REFERENCES dokaz(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Oružje
CREATE TABLE oruzje (
    id_dokaz     INT PRIMARY KEY,
    vrsta_oruzja VARCHAR(255) NULL,
    marka        VARCHAR(255) NULL,
    model        VARCHAR(255) NULL,
    kalibar      VARCHAR(255) NULL,
    serijski_br  VARCHAR(255) NULL UNIQUE,
    FOREIGN KEY (id_dokaz) REFERENCES dokaz(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dokument kao dokaz (ne brkati sa tabelom dokument)
CREATE TABLE dokument_dokaz (
    id_dokaz        INT PRIMARY KEY,
    vrsta_dokumenta VARCHAR(255) NULL,
    jezik           VARCHAR(255) NULL,
    broj_stranica   INT NULL,
    FOREIGN KEY (id_dokaz) REFERENCES dokaz(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Odeća
CREATE TABLE odeca (
    id_dokaz                INT PRIMARY KEY,
    velicina                VARCHAR(255) NULL,
    vrsta_odevnog_predmeta  VARCHAR(255) NULL,
    boja                    VARCHAR(255) NULL,
    stanje                  VARCHAR(255) NULL,
    FOREIGN KEY (id_dokaz) REFERENCES dokaz(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Uzorak
CREATE TABLE uzorak (
    id_dokaz          INT PRIMARY KEY,
    vrsta_uzorka      VARCHAR(255) NULL,
    kolicina          VARCHAR(255) NULL,
    jedinica_mere     VARCHAR(255) NULL,
    nacin_uzorkovanja VARCHAR(255) NULL,
    uslovi_cuvanja    VARCHAR(255) NULL,
    FOREIGN KEY (id_dokaz) REFERENCES dokaz(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Zahtev za dokaz ────────────────────────────────────────────────────────
CREATE TABLE zahtev_za_dokaz (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    tip              VARCHAR(50) NOT NULL,
    razlog           TEXT NULL,
    status           ENUM('NA_CEKANJU','ODOBREN','ODBIJEN') NOT NULL DEFAULT 'NA_CEKANJU',
    datum_kreiranja  DATETIME NOT NULL DEFAULT NOW(),
    datum_obrade     DATETIME NULL,
    napomena         TEXT NULL,
    dokaz_id         INT NOT NULL,
    podnosilac_id    INT NOT NULL,
    tehnicar_id      INT NULL,
    FOREIGN KEY (dokaz_id) REFERENCES dokaz(id),
    FOREIGN KEY (podnosilac_id) REFERENCES korisnik(id),
    FOREIGN KEY (tehnicar_id) REFERENCES tehnicar_za_dokaze(id_korisnik)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Zahtev za analizu ──────────────────────────────────────────────────────
CREATE TABLE zahtev_za_analizu (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    opis                  TEXT NULL,
    tip_analize           ENUM('BALISTICKA','DNK','DIGITALNA','HEMIJSKA','TOKSIKOLOSKA','DOKUMENTOLOSKA','DRUGA') NOT NULL,
    datum_kreiranja       DATETIME NOT NULL DEFAULT NOW(),
    datum_pocetka         DATETIME NULL,
    rok                   DATETIME NULL,
    status                ENUM('KREIRAN','DODELJEN','U_TOKU','ZAVRSEN','PREKORACEN','ODBIJEN') NOT NULL DEFAULT 'KREIRAN',
    prag_upozorenja_dana  INT NOT NULL DEFAULT 3,
    istrazitelj_id        INT NOT NULL,
    dokaz_id              INT NOT NULL,
    predmet_id            INT NOT NULL,
    vestak_id             INT NULL,
    FOREIGN KEY (istrazitelj_id) REFERENCES istrazitelj(id_korisnik),
    FOREIGN KEY (dokaz_id) REFERENCES dokaz(id),
    FOREIGN KEY (predmet_id) REFERENCES predmet(id),
    FOREIGN KEY (vestak_id) REFERENCES vestak(id_korisnik)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Rezultat analize ───────────────────────────────────────────────────────
CREATE TABLE rezultat_analize (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    sadrzaj      TEXT NOT NULL,
    datum_unosa  DATETIME NOT NULL DEFAULT NOW(),
    verifikovan  BOOLEAN NOT NULL DEFAULT FALSE,
    zahtev_id    INT NOT NULL UNIQUE,
    uneao_id     INT NOT NULL,
    FOREIGN KEY (zahtev_id) REFERENCES zahtev_za_analizu(id),
    FOREIGN KEY (uneao_id) REFERENCES korisnik(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Istorija dodele veštaka ────────────────────────────────────────────────
CREATE TABLE istorija_dodele (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    datum_dodele    DATETIME NOT NULL DEFAULT NOW(),
    razlog_promene  TEXT NULL,
    zahtev_id       INT NOT NULL,
    vestak_id       INT NOT NULL,
    dodelio_id      INT NOT NULL,
    FOREIGN KEY (zahtev_id) REFERENCES zahtev_za_analizu(id) ON DELETE CASCADE,
    FOREIGN KEY (vestak_id) REFERENCES vestak(id_korisnik),
    FOREIGN KEY (dodelio_id) REFERENCES korisnik(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Istorija promena statusa analize ───────────────────────────────────────
CREATE TABLE istorija_statusa_analize (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    stari_status ENUM('KREIRAN','DODELJEN','U_TOKU','ZAVRSEN','PREKORACEN','ODBIJEN') NULL,
    novi_status  ENUM('KREIRAN','DODELJEN','U_TOKU','ZAVRSEN','PREKORACEN','ODBIJEN') NOT NULL,
    datum_vreme  DATETIME NOT NULL DEFAULT NOW(),
    napomena     TEXT NULL,
    zahtev_id    INT NOT NULL,
    inicirao_id  INT NOT NULL,
    FOREIGN KEY (zahtev_id) REFERENCES zahtev_za_analizu(id) ON DELETE CASCADE,
    FOREIGN KEY (inicirao_id) REFERENCES korisnik(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Log brisanja zahteva ───────────────────────────────────────────────────
CREATE TABLE log_brisanja_zahteva (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    zahtev_id       INT NOT NULL,
    datum_brisanja  DATETIME NOT NULL DEFAULT NOW(),
    razlog          TEXT NOT NULL,
    obrisao_id      INT NOT NULL,
    FOREIGN KEY (obrisao_id) REFERENCES korisnik(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Istorija izmena zahteva ────────────────────────────────────────────────
CREATE TABLE istorija_izmene_zahteva (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    polje          VARCHAR(255) NOT NULL,
    stara_vrednost TEXT NULL,
    nova_vrednost  TEXT NOT NULL,
    datum_vreme    DATETIME NOT NULL DEFAULT NOW(),
    zahtev_id      INT NOT NULL,
    korisnik_id    INT NOT NULL,
    FOREIGN KEY (zahtev_id) REFERENCES zahtev_za_analizu(id) ON DELETE CASCADE,
    FOREIGN KEY (korisnik_id) REFERENCES korisnik(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Obaveštenje ────────────────────────────────────────────────────────────
CREATE TABLE obavestenje (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    sadrzaj      TEXT NOT NULL,
    procitano    BOOLEAN NOT NULL DEFAULT FALSE,
    tip          VARCHAR(50) NOT NULL,
    datum_vreme  DATETIME NOT NULL DEFAULT NOW(),
    korisnik_id  INT NOT NULL,
    zahtev_id    INT NULL,
    FOREIGN KEY (korisnik_id) REFERENCES korisnik(id),
    FOREIGN KEY (zahtev_id) REFERENCES zahtev_za_analizu(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
