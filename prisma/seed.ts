import { PrismaClient, Uloga, StatusAnalize, TipAnalize } from "@prisma/client";
import bcrypt from "bcryptjs";

const prisma = new PrismaClient();

async function main() {
  console.log("🌱 Pokretanje seed skripte...");

  // Kreiranje test korisnika
  const lozinka = await bcrypt.hash("password123", 12);

  const admin = await prisma.korisnik.upsert({
    where: { email: "admin@fis.rs" },
    update: {},
    create: {
      ime: "Admin",
      prezime: "Sistem",
      email: "admin@fis.rs",
      korIme: "admin",
      lozinkaHash: lozinka,
      uloga: Uloga.ADMINISTRATOR,
    },
  });

  const istrazitelj = await prisma.korisnik.upsert({
    where: { email: "istrazitelj@fis.rs" },
    update: {},
    create: {
      ime: "Marko",
      prezime: "Marković",
      email: "istrazitelj@fis.rs",
      korIme: "marko.markovic",
      lozinkaHash: lozinka,
      uloga: Uloga.ISTRAZITELJ,
    },
  });

  const tehnicar = await prisma.korisnik.upsert({
    where: { email: "tehnicar@fis.rs" },
    update: {},
    create: {
      ime: "Ana",
      prezime: "Anić",
      email: "tehnicar@fis.rs",
      korIme: "ana.anic",
      lozinkaHash: lozinka,
      uloga: Uloga.TEHNICAR,
    },
  });

  const vestak = await prisma.korisnik.upsert({
    where: { email: "vestak@fis.rs" },
    update: {},
    create: {
      ime: "Petar",
      prezime: "Petrović",
      email: "vestak@fis.rs",
      korIme: "petar.petrovic",
      lozinkaHash: lozinka,
      uloga: Uloga.VESTAK,
    },
  });

  // Kreiranje TehnicarZaDokaze profila za tehničara
  await prisma.tehnicarZaDokaze.upsert({
    where: { idKorisnik: tehnicar.id },
    update: {},
    create: {
      idKorisnik: tehnicar.id,
      idTd: "TD-001",
      odeljenje: "Odeljenje za fizičke dokaze",
    },
  });

  // Kreiranje test predmeta
  const predmet1 = await prisma.predmet.upsert({
    where: { id: 1 },
    update: {},
    create: {
      naziv: "Predmet 2024-001",
      opis: "Test predmet za razvoj",
    },
  });

  const predmet2 = await prisma.predmet.upsert({
    where: { id: 2 },
    update: {},
    create: {
      naziv: "Predmet 2024-002",
      opis: "Drugi test predmet",
    },
  });

  // Kreiranje test tagova
  const tagovi = ["HITNO", "Vatreno oružje", "Hladno oružje", "Izuzetno relevantno", "Biološki trag"];
  for (const naziv of tagovi) {
    await prisma.tag.upsert({
      where: { naziv },
      update: {},
      create: { naziv },
    });
  }

  // ── Kreiranje test dokaza ─────────────────────────────────────────────────

  const dokaz1 = await prisma.dokaz.upsert({
    where: { sifraDokaza: "DOK-2026-0001" },
    update: {},
    create: {
      sifraDokaza: "DOK-2026-0001",
      naziv: "Nož pronađen na licu mesta",
      opis: "Kuhinjski nož, dužine sečiva 15cm, pronađen u blizini žrtve",
      tipDokaza: "ORUZJE",
      datumPrijema: new Date("2026-01-15"),
      datumPronalaska: new Date("2026-01-14"),
      lokacijaPronalaska: "Ul. Knez Mihailova 15, Beograd",
      lokacijaSkladistenja: "Skladište A, polica 3",
      status: "U_SKLADISTU",
      predmetId: predmet1.id,
      tehnicarId: tehnicar.id,
    },
  });

  await prisma.oruzje.upsert({
    where: { idDokaz: dokaz1.id },
    update: {},
    create: {
      idDokaz: dokaz1.id,
      vrstaOruzja: "Hladno",
      marka: "Victorinox",
      model: "Chef's Knife",
      kalibar: null,
      serijskiBr: null,
    },
  });

  const dokaz2 = await prisma.dokaz.upsert({
    where: { sifraDokaza: "DOK-2026-0002" },
    update: {},
    create: {
      sifraDokaza: "DOK-2026-0002",
      naziv: "Uzorak krvi sa poda",
      opis: "Biološki trag prikupljen brisom sa poda u dnevnom boravku",
      tipDokaza: "BIOLOSKI_TRAG",
      datumPrijema: new Date("2026-01-16"),
      lokacijaSkladistenja: "Hladna komora, sekcija B",
      status: "IZDATO_ZA_ANALIZU",
      predmetId: predmet1.id,
      tehnicarId: tehnicar.id,
    },
  });

  await prisma.bioloskiTrag.upsert({
    where: { idDokaz: dokaz2.id },
    update: {},
    create: {
      idDokaz: dokaz2.id,
      vrstaTraga: "Krv",
      nacinUzorkovanja: "Bris",
      usloviCuvanja: "-20°C",
      kolicina: "5 ml",
    },
  });

  const dokaz3 = await prisma.dokaz.upsert({
    where: { sifraDokaza: "DOK-2026-0003" },
    update: {},
    create: {
      sifraDokaza: "DOK-2026-0003",
      naziv: "Lična karta osumnjičenog",
      tipDokaza: "DOKUMENT",
      datumPrijema: new Date("2026-02-01"),
      lokacijaSkladistenja: "Skladište A, fioka 12",
      status: "U_SKLADISTU",
      predmetId: predmet2.id,
      tehnicarId: tehnicar.id,
    },
  });

  await prisma.dokumentDokaz.upsert({
    where: { idDokaz: dokaz3.id },
    update: {},
    create: {
      idDokaz: dokaz3.id,
      vrstaDokumenta: "Lična karta",
      jezik: "Srpski",
      brojStranica: 2,
    },
  });

  // ── Lanac čuvanja zapisi ──────────────────────────────────────────────────

  await prisma.lanacCuvanja.createMany({
    data: [
      {
        dokazId: dokaz1.id,
        tehnicarId: tehnicar.id,
        akcija: "Prijem dokaza",
        datumVreme: new Date("2026-01-15T10:00:00"),
        napomena: "Dokaz DOK-2026-0001 evidentiran u sistemu",
      },
      {
        dokazId: dokaz2.id,
        tehnicarId: tehnicar.id,
        akcija: "Prijem dokaza",
        datumVreme: new Date("2026-01-16T09:30:00"),
        napomena: "Dokaz DOK-2026-0002 evidentiran u sistemu",
      },
      {
        dokazId: dokaz2.id,
        tehnicarId: tehnicar.id,
        akcija: "Izdavanje dokaza",
        datumVreme: new Date("2026-01-20T14:00:00"),
        napomena: "Izdato za DNK analizu",
      },
      {
        dokazId: dokaz3.id,
        tehnicarId: tehnicar.id,
        akcija: "Prijem dokaza",
        datumVreme: new Date("2026-02-01T11:00:00"),
        napomena: "Dokaz DOK-2026-0003 evidentiran u sistemu",
      },
    ],
    skipDuplicates: true,
  });

  // ── Kreiranje Istrazitelj profila ─────────────────────────────────────────
  await prisma.istrazitelj.upsert({
    where: { idKorisnik: istrazitelj.id },
    update: {},
    create: {
      idKorisnik: istrazitelj.id,
      brojZnacke: "IST-001",
      odeljenje: "Odeljenje za kriminalitet",
    },
  });

  // ── Kreiranje Vestak profila ──────────────────────────────────────────────
  await prisma.vestak.upsert({
    where: { idKorisnik: vestak.id },
    update: {},
    create: {
      idKorisnik: vestak.id,
      idVestak: "VES-001",
      specijalnost: "Biometrija i DNK analiza",
      sertifikatBr: "CERT-2024-VES-001",
    },
  });

  // ── Test zahtevi za dokaze ────────────────────────────────────────────────

  await prisma.zahtevZaDokaz.create({
    data: {
      dokazId: dokaz1.id,
      tip: "PREDAJA",
      razlog: "Potreban za balistička veštačenja",
      podnosilacId: vestak.id,
    },
  });

  await prisma.zahtevZaDokaz.create({
    data: {
      dokazId: dokaz3.id,
      tip: "PREDAJA",
      razlog: "Provera autentičnosti dokumenta",
      podnosilacId: istrazitelj.id,
    },
  });

  // ── Drugi veštak (za testiranje preraspodele) ────────────────────────────

  const vestak2 = await prisma.korisnik.upsert({
    where: { email: "vestak2@fis.rs" },
    update: {},
    create: {
      ime: "Jovana",
      prezime: "Jovanović",
      email: "vestak2@fis.rs",
      korIme: "jovana.jovanovic",
      lozinkaHash: lozinka,
      uloga: Uloga.VESTAK,
    },
  });

  await prisma.vestak.upsert({
    where: { idKorisnik: vestak2.id },
    update: {},
    create: {
      idKorisnik: vestak2.id,
      idVestak: "VES-002",
      specijalnost: "Digitalna forenzika i dokumentologija",
      sertifikatBr: "CERT-2024-VES-002",
    },
  });

  // ── Zahtevi za analizu — po jedan za svaki JIRA scenario ─────────────────

  // ZAHTEV #1 — KREIRAN, bez veštaka → testira JIRA #4 (brisanje) i #5 (dodela)
  const z1 = await prisma.zahtevZaAnalizu.create({
    data: {
      predmetId: predmet1.id,
      dokazId: dokaz1.id,
      tipAnalize: TipAnalize.BALISTICKA,
      opis: "Potrebno utvrditi da li je oružje korišćeno na mestu zločina. Analiza otisaka prstiju i tragova krvi.",
      datumPocetka: null,
      rok: new Date("2026-06-30"),
      pragUpozorenjaDana: 5,
      status: StatusAnalize.KREIRAN,
      istraziteljId: istrazitelj.id,
    },
  });
  await prisma.istorijaStatusaAnalize.create({
    data: { zahtevId: z1.id, stariStatus: null, noviStatus: StatusAnalize.KREIRAN, iniciraoId: istrazitelj.id },
  });

  // ZAHTEV #2 — DODELJEN, sa veštakom → testira JIRA #3 (izmena+audit log) i #6 (preraspodela)
  const z2 = await prisma.zahtevZaAnalizu.create({
    data: {
      predmetId: predmet1.id,
      dokazId: dokaz2.id,
      tipAnalize: TipAnalize.DNK,
      opis: "DNK analiza biološkog traga radi identifikacije osumnjičenog.",
      datumPocetka: new Date("2026-05-20"),
      rok: new Date("2026-07-15"),
      pragUpozorenjaDana: 7,
      status: StatusAnalize.DODELJEN,
      istraziteljId: istrazitelj.id,
      vestakId: vestak.id,
    },
  });
  await prisma.istorijaStatusaAnalize.createMany({
    data: [
      { zahtevId: z2.id, stariStatus: null, noviStatus: StatusAnalize.KREIRAN, iniciraoId: istrazitelj.id, datumVreme: new Date("2026-05-10T09:00:00") },
      { zahtevId: z2.id, stariStatus: StatusAnalize.KREIRAN, noviStatus: StatusAnalize.DODELJEN, iniciraoId: istrazitelj.id, datumVreme: new Date("2026-05-12T11:00:00") },
    ],
  });
  await prisma.istorijaDodele.create({
    data: { zahtevId: z2.id, vestakId: vestak.id, dodeliooId: istrazitelj.id, razlogPromene: null },
  });
  // Unapred ubačen audit log izmena (da se odmah vidi sekcija)
  await prisma.istorijaIzmeneZahteva.createMany({
    data: [
      { zahtevId: z2.id, korisnikId: istrazitelj.id, polje: "rok", staraVrednost: "2026-06-30T00:00:00.000Z", novaVrednost: "2026-07-15T00:00:00.000Z", datumVreme: new Date("2026-05-13T10:30:00") },
      { zahtevId: z2.id, korisnikId: istrazitelj.id, polje: "pragUpozorenjaDana", staraVrednost: "3", novaVrednost: "7", datumVreme: new Date("2026-05-13T10:30:00") },
    ],
  });

  // ZAHTEV #3 — U_TOKU → testira prikaz (ne može se menjati ni brisati)
  const z3 = await prisma.zahtevZaAnalizu.create({
    data: {
      predmetId: predmet2.id,
      dokazId: dokaz3.id,
      tipAnalize: TipAnalize.DOKUMENTOLOSKA,
      opis: "Veštačenje autentičnosti lične karte osumnjičenog.",
      datumPocetka: new Date("2026-05-15"),
      rok: new Date("2026-06-20"),
      pragUpozorenjaDana: 3,
      status: StatusAnalize.U_TOKU,
      istraziteljId: istrazitelj.id,
      vestakId: vestak2.id,
    },
  });
  await prisma.istorijaStatusaAnalize.createMany({
    data: [
      { zahtevId: z3.id, stariStatus: null, noviStatus: StatusAnalize.KREIRAN, iniciraoId: istrazitelj.id, datumVreme: new Date("2026-05-01T08:00:00") },
      { zahtevId: z3.id, stariStatus: StatusAnalize.KREIRAN, noviStatus: StatusAnalize.DODELJEN, iniciraoId: istrazitelj.id, datumVreme: new Date("2026-05-05T09:00:00") },
      { zahtevId: z3.id, stariStatus: StatusAnalize.DODELJEN, noviStatus: StatusAnalize.U_TOKU, iniciraoId: vestak2.id, datumVreme: new Date("2026-05-15T08:00:00") },
    ],
  });
  await prisma.istorijaDodele.create({
    data: { zahtevId: z3.id, vestakId: vestak2.id, dodeliooId: istrazitelj.id, razlogPromene: null },
  });

  // ZAHTEV #4 — ZAVRSEN sa verifikovanim rezultatom → prikazuje kompletan tok
  const z4 = await prisma.zahtevZaAnalizu.create({
    data: {
      predmetId: predmet1.id,
      dokazId: dokaz1.id,
      tipAnalize: TipAnalize.HEMIJSKA,
      opis: "Hemijska analiza supstance pronađene na oštrici noža.",
      datumPocetka: new Date("2026-04-01"),
      rok: new Date("2026-05-01"),
      pragUpozorenjaDana: 5,
      status: StatusAnalize.ZAVRSEN,
      istraziteljId: istrazitelj.id,
      vestakId: vestak.id,
    },
  });
  await prisma.istorijaStatusaAnalize.createMany({
    data: [
      { zahtevId: z4.id, stariStatus: null, noviStatus: StatusAnalize.KREIRAN, iniciraoId: istrazitelj.id, datumVreme: new Date("2026-03-20T10:00:00") },
      { zahtevId: z4.id, stariStatus: StatusAnalize.KREIRAN, noviStatus: StatusAnalize.DODELJEN, iniciraoId: istrazitelj.id, datumVreme: new Date("2026-03-22T11:00:00") },
      { zahtevId: z4.id, stariStatus: StatusAnalize.DODELJEN, noviStatus: StatusAnalize.U_TOKU, iniciraoId: vestak.id, datumVreme: new Date("2026-04-01T08:00:00") },
      { zahtevId: z4.id, stariStatus: StatusAnalize.U_TOKU, noviStatus: StatusAnalize.ZAVRSEN, iniciraoId: istrazitelj.id, napomena: "Rezultat verifikovan od strane istražitelja.", datumVreme: new Date("2026-04-28T14:00:00") },
    ],
  });
  await prisma.istorijaDodele.create({
    data: { zahtevId: z4.id, vestakId: vestak.id, dodeliooId: istrazitelj.id, razlogPromene: null },
  });
  await prisma.rezultatAnalize.create({
    data: {
      zahtevId: z4.id,
      sadrzaj: "Analiza je pokazala prisustvo humane krvi grupe B+ na oštrici noža. Utvrđena je podudarnost sa uzorkom žrtve. Nije pronađena krv osumnjičenog. Zaključak: oružje je korišćeno u incidentu.",
      verifikovan: true,
      uneaoId: vestak.id,
      datumUnosa: new Date("2026-04-25T16:00:00"),
    },
  });

  // ZAHTEV #5 — PREKORACEN (rok je prošao, nije završeno)
  const z5 = await prisma.zahtevZaAnalizu.create({
    data: {
      predmetId: predmet2.id,
      dokazId: dokaz3.id,
      tipAnalize: TipAnalize.DIGITALNA,
      opis: "Ekstrakcija podataka sa mobilnog uređaja osumnjičenog.",
      datumPocetka: new Date("2026-03-01"),
      rok: new Date("2026-04-01"),
      pragUpozorenjaDana: 3,
      status: StatusAnalize.PREKORACEN,
      istraziteljId: istrazitelj.id,
      vestakId: vestak2.id,
    },
  });
  await prisma.istorijaStatusaAnalize.createMany({
    data: [
      { zahtevId: z5.id, stariStatus: null, noviStatus: StatusAnalize.KREIRAN, iniciraoId: istrazitelj.id, datumVreme: new Date("2026-02-20T09:00:00") },
      { zahtevId: z5.id, stariStatus: StatusAnalize.KREIRAN, noviStatus: StatusAnalize.DODELJEN, iniciraoId: istrazitelj.id, datumVreme: new Date("2026-02-25T10:00:00") },
      { zahtevId: z5.id, stariStatus: StatusAnalize.DODELJEN, noviStatus: StatusAnalize.PREKORACEN, iniciraoId: admin.id, napomena: "Automatsko ažuriranje sistema — rok je istekao.", datumVreme: new Date("2026-04-02T00:01:00") },
    ],
  });
  await prisma.istorijaDodele.create({
    data: { zahtevId: z5.id, vestakId: vestak2.id, dodeliooId: istrazitelj.id, razlogPromene: null },
  });

  // ZAHTEV #6 — KREIRAN, drugi predmet → još jedan za testiranje filtera
  const z6 = await prisma.zahtevZaAnalizu.create({
    data: {
      predmetId: predmet2.id,
      dokazId: dokaz3.id,
      tipAnalize: TipAnalize.TOKSIKOLOSKA,
      opis: "Toksikološka analiza uzorka radi utvrđivanja prisustva opojnih supstanci.",
      datumPocetka: null,
      rok: new Date("2026-08-01"),
      pragUpozorenjaDana: 10,
      status: StatusAnalize.KREIRAN,
      istraziteljId: istrazitelj.id,
    },
  });
  await prisma.istorijaStatusaAnalize.create({
    data: { zahtevId: z6.id, stariStatus: null, noviStatus: StatusAnalize.KREIRAN, iniciraoId: istrazitelj.id },
  });

  console.log("✅ Seed završen!");
  console.log("─────────────────────────────────");
  console.log("Korisnici (lozinka: password123):");
  console.log(`  admin@fis.rs        → ADMINISTRATOR`);
  console.log(`  istrazitelj@fis.rs  → ISTRAZITELJ`);
  console.log(`  tehnicar@fis.rs     → TEHNICAR`);
  console.log(`  vestak@fis.rs       → VESTAK (Petar Petrović)`);
  console.log(`  vestak2@fis.rs      → VESTAK (Jovana Jovanović)`);
  console.log("─────────────────────────────────");
  console.log("Zahtevi za analizu:");
  console.log(`  #${z1.id} KREIRAN       — Balistička, bez veštaka  → testiraj dodelu i brisanje`);
  console.log(`  #${z2.id} DODELJEN      — DNK, Petar Petrović       → testiraj izmenu (audit log) i preraspodelu`);
  console.log(`  #${z3.id} U_TOKU        — Dokumentološka, Jovana    → testiraj unos rezultata (prijavi se kao vestak2)`);
  console.log(`  #${z4.id} ZAVRSEN       — Hemijska, verifikovan      → kompletan tok`);
  console.log(`  #${z5.id} PREKORACEN    — Digitalna, rok istekao`);
  console.log(`  #${z6.id} KREIRAN       — Toksikološka, drugi predmet → testiraj filtriranje`);
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
