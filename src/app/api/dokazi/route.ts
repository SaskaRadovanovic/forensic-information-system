import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { NextRequest, NextResponse } from "next/server";

// ─── GET /api/dokazi — lista dokaza sa filterima ────────────────────────────

export async function GET(req: NextRequest) {
  // Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) {
    return NextResponse.json({ ok: false, greska: "Niste prijavljeni." }, { status: 401 });
  }

  // Parsiranje filtera iz query parametara
  const { searchParams } = req.nextUrl;
  const tipDokaza = searchParams.get("tipDokaza");
  const status = searchParams.get("status");
  const predmetId = searchParams.get("predmetId");

  // Fetch dokaza
  const dokazi = await prisma.dokaz.findMany({
    where: {
      ...(tipDokaza ? { tipDokaza: tipDokaza as any } : {}),
      ...(status ? { status: status as any } : { status: { not: "ARHIVIRANO" } }),
      ...(predmetId ? { predmetId: parseInt(predmetId) } : {}),
    },
    include: {
      predmet: { select: { naziv: true } },
      tehnicar: {
        include: { korisnik: { select: { ime: true, prezime: true } } },
      },
    },
    orderBy: { datumPrijema: "desc" },
  });

  return NextResponse.json({ ok: true, podaci: dokazi });
}

// ─── POST /api/dokazi — kreiranje dokaza (za integraciju) ───────────────────

export async function POST(req: NextRequest) {
  // Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) {
    return NextResponse.json({ ok: false, greska: "Niste prijavljeni." }, { status: 401 });
  }

  // Provera autorizacije
  const uloga = session.user.uloga;
  if (uloga !== "TEHNICAR" && uloga !== "ADMINISTRATOR") {
    return NextResponse.json({ ok: false, greska: "Nemate dozvolu." }, { status: 403 });
  }

  // Parsiranje tela zahteva
  const telo = await req.json();

  // Minimalna validacija
  if (!telo.naziv || !telo.tipDokaza || !telo.predmetId || !telo.datumPrijema) {
    return NextResponse.json(
      { ok: false, greska: "Nedostaju obavezna polja: naziv, tipDokaza, predmetId, datumPrijema." },
      { status: 400 }
    );
  }

  // Pronalaženje tehničar profila
  const korisnikId = parseInt(session.user.id, 10);
  const tehnicarProfil = await prisma.tehnicarZaDokaze.findUnique({
    where: { idKorisnik: korisnikId },
  });
  if (!tehnicarProfil) {
    return NextResponse.json({ ok: false, greska: "Korisnik nema tehničar profil." }, { status: 403 });
  }

  // Generisanje šifre
  const godina = new Date().getFullYear();
  const brojDokaza = await prisma.dokaz.count({
    where: { sifraDokaza: { startsWith: `DOK-${godina}-` } },
  });
  const sifraDokaza = `DOK-${godina}-${(brojDokaza + 1).toString().padStart(4, "0")}`;

  // Kreiranje dokaza
  const dokaz = await prisma.dokaz.create({
    data: {
      sifraDokaza,
      naziv: telo.naziv,
      opis: telo.opis || null,
      tipDokaza: telo.tipDokaza,
      datumPrijema: new Date(telo.datumPrijema),
      lokacijaSkladistenja: telo.lokacijaSkladistenja || null,
      status: "U_SKLADISTU",
      predmetId: telo.predmetId,
      tehnicarId: korisnikId,
    },
  });

  return NextResponse.json({ ok: true, podaci: dokaz }, { status: 201 });
}
