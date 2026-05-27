import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { NextResponse } from "next/server";

// ─── GET /api/izvestaji/analize — agregirani izveštaji o analizama ────────────

export async function GET() {
  const session = await getServerSession(authOptions);
  if (!session) return NextResponse.json({ ok: false, greska: "Niste prijavljeni." }, { status: 401 });
  if (session.user.uloga !== "ISTRAZITELJ" && session.user.uloga !== "ADMINISTRATOR")
    return NextResponse.json({ ok: false, greska: "Nemate ovlašćenje za pristup izveštajima." }, { status: 403 });

  const sada = new Date();

  // ── 1. Analize po predmetu (agregirane) ──────────────────────────────────
  const predmeti = await prisma.predmet.findMany({
    select: { id: true, naziv: true },
    orderBy: { naziv: "asc" },
  });

  const analizePoPredmetu = await Promise.all(
    predmeti.map(async (p) => {
      const [ukupno, zavrsene, uToku, prekoracene] = await Promise.all([
        prisma.zahtevZaAnalizu.count({ where: { predmetId: p.id } }),
        prisma.zahtevZaAnalizu.count({ where: { predmetId: p.id, status: "ZAVRSEN" } }),
        prisma.zahtevZaAnalizu.count({ where: { predmetId: p.id, status: { in: ["DODELJEN", "U_TOKU"] } } }),
        prisma.zahtevZaAnalizu.count({ where: { predmetId: p.id, status: "PREKORACEN" } }),
      ]);
      return { predmetId: p.id, predmetNaziv: p.naziv, ukupno, zavrsene, uToku, prekoracene };
    })
  );

  // ── 2. Analize sa kašnjenjem (PREKORACEN status) ─────────────────────────
  const kasnjenja = await prisma.zahtevZaAnalizu.findMany({
    where: { status: "PREKORACEN" },
    select: {
      id: true,
      tipAnalize: true,
      rok: true,
      predmet: { select: { naziv: true } },
      vestak: { include: { korisnik: { select: { ime: true, prezime: true } } } },
    },
    orderBy: { rok: "asc" },
  });

  const analizeSaKasnjenjem = kasnjenja.map((a) => ({
    id: a.id,
    tipAnalize: a.tipAnalize,
    rok: a.rok ? a.rok.toISOString() : null,
    predmetNaziv: a.predmet.naziv,
    vestakIme: a.vestak ? `${a.vestak.korisnik.ime} ${a.vestak.korisnik.prezime}` : null,
  }));

  // ── 3. Statistika po tipu analize (prosečno trajanje) ────────────────────
  const zavrseneGrupe = await prisma.zahtevZaAnalizu.findMany({
    where: { status: "ZAVRSEN", datumPocetka: { not: null } },
    select: { tipAnalize: true, datumPocetka: true, datumKreiranja: true, rezultat: { select: { datumUnosa: true } } },
  });

  const grupePoTipu: Record<string, { sumaDana: number; ukupno: number }> = {};
  for (const a of zavrseneGrupe) {
    const pocetak = a.datumPocetka ?? a.datumKreiranja;
    const kraj = a.rezultat?.datumUnosa ?? sada;
    const dana = Math.max(0, Math.round((kraj.getTime() - pocetak.getTime()) / 86_400_000));
    if (!grupePoTipu[a.tipAnalize]) grupePoTipu[a.tipAnalize] = { sumaDana: 0, ukupno: 0 };
    grupePoTipu[a.tipAnalize].sumaDana += dana;
    grupePoTipu[a.tipAnalize].ukupno++;
  }

  const statistikaPoTipu = Object.entries(grupePoTipu).map(([tip, d]) => ({
    tipAnalize: tip,
    prosecnoTrajanjeDana: d.ukupno > 0 ? Math.round(d.sumaDana / d.ukupno) : null,
    ukupnoZavrsenih: d.ukupno,
  }));

  // ── 4. Opterećenje veštaka ────────────────────────────────────────────────
  const sviVestaci = await prisma.vestak.findMany({
    include: { korisnik: { select: { ime: true, prezime: true } } },
    orderBy: { korisnik: { prezime: "asc" } },
  });

  const opterecenjeVestaka = await Promise.all(
    sviVestaci.map(async (v) => {
      const [aktivnih, zavrsenih] = await Promise.all([
        prisma.zahtevZaAnalizu.count({ where: { vestakId: v.idKorisnik, status: { in: ["DODELJEN", "U_TOKU"] } } }),
        prisma.zahtevZaAnalizu.count({ where: { vestakId: v.idKorisnik, status: "ZAVRSEN" } }),
      ]);
      return {
        vestakId: v.idKorisnik,
        vestakIme: `${v.korisnik.ime} ${v.korisnik.prezime}`,
        aktivnih,
        zavrsenih,
      };
    })
  );

  return NextResponse.json({
    ok: true,
    podaci: { analizePoPredmetu, analizeSaKasnjenjem, statistikaPoTipu, opterecenjeVestaka },
  });
}
