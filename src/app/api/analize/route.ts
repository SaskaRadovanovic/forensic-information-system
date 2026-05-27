import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { NextRequest, NextResponse } from "next/server";

// ─── Helper: automatsko ažuriranje statusa po rokovima ───────────────────────
// Poziva se pri svakom GET zahtevu liste kako bi rokovi bili ažurni

async function azurirajRokove() {
  const sada = new Date();

  // Nalazimo sve zahteve koji nisu završeni/odbijeni i imaju definisan rok
  const zahtevi = await prisma.zahtevZaAnalizu.findMany({
    where: {
      rok: { not: null },
      status: { notIn: ["ZAVRSEN", "ODBIJEN"] },
    },
    select: { id: true, rok: true, status: true, pragUpozorenjaDana: true, istraziteljId: true, vestakId: true },
  });

  // Nalazimo sistemskog korisnika (admin) za logovanje automatskih promena
  const admin = await prisma.korisnik.findFirst({ where: { uloga: "ADMINISTRATOR" } });
  if (!admin) return;

  for (const z of zahtevi) {
    if (!z.rok) continue;

    const prekoracen = z.rok < sada && z.status !== "PREKORACEN";
    if (prekoracen) {
      await prisma.$transaction(async (tx) => {
        await tx.zahtevZaAnalizu.update({ where: { id: z.id }, data: { status: "PREKORACEN" } });
        await tx.istorijaStatusaAnalize.create({
          data: {
            zahtevId: z.id,
            stariStatus: z.status,
            noviStatus: "PREKORACEN",
            iniciraoId: admin.id,
            napomena: "Automatsko ažuriranje sistema — rok je istekao.",
          },
        });
        // Obaveštenje za istražitelja
        await tx.obavestenje.create({
          data: {
            korisnikId: z.istraziteljId,
            sadrzaj: `Rok za analizu #${z.id} je istekao!`,
            tip: "ROK_PREKORACEN",
            zahtevId: z.id,
          },
        });
      });
    }
  }
}

// ─── GET /api/analize — lista zahteva sa filterima ───────────────────────────

export async function GET(req: NextRequest) {
  const session = await getServerSession(authOptions);
  if (!session) return NextResponse.json({ ok: false, greska: "Niste prijavljeni." }, { status: 401 });

  // Automatsko ažuriranje rokova
  await azurirajRokove();

  const { searchParams } = req.nextUrl;
  const predmetId = searchParams.get("predmetId");
  const vestakId = searchParams.get("vestakId");
  const tipAnalize = searchParams.get("tipAnalize");
  const status = searchParams.get("status");
  const rokOd = searchParams.get("rokOd");
  const rokDo = searchParams.get("rokDo");

  const zahtevi = await prisma.zahtevZaAnalizu.findMany({
    where: {
      ...(predmetId ? { predmetId: parseInt(predmetId) } : {}),
      ...(vestakId ? { vestakId: parseInt(vestakId) } : {}),
      ...(tipAnalize ? { tipAnalize: tipAnalize as any } : {}),
      ...(status ? { status: status as any } : {}),
      ...(rokOd || rokDo ? {
        rok: {
          ...(rokOd ? { gte: new Date(rokOd) } : {}),
          ...(rokDo ? { lte: new Date(rokDo) } : {}),
        },
      } : {}),
    },
    include: {
      predmet: { select: { naziv: true } },
      dokaz: { select: { naziv: true, sifraDokaza: true } },
      istrazitelj: { include: { korisnik: { select: { ime: true, prezime: true } } } },
      vestak: { include: { korisnik: { select: { ime: true, prezime: true } } } },
    },
    orderBy: { datumKreiranja: "desc" },
  });

  return NextResponse.json({ ok: true, podaci: zahtevi });
}

// ─── POST /api/analize — kreiranje zahteva (za interoperabilnost) ─────────────

export async function POST(req: NextRequest) {
  const session = await getServerSession(authOptions);
  if (!session) return NextResponse.json({ ok: false, greska: "Niste prijavljeni." }, { status: 401 });
  if (session.user.uloga !== "ISTRAZITELJ")
    return NextResponse.json({ ok: false, greska: "Samo istražitelji mogu kreirati zahteve." }, { status: 403 });

  const korisnikId = parseInt(session.user.id, 10);
  const istraziteljProfil = await prisma.istrazitelj.findUnique({ where: { idKorisnik: korisnikId } });
  if (!istraziteljProfil)
    return NextResponse.json({ ok: false, greska: "Profil istražitelja nije pronađen." }, { status: 403 });

  const telo = await req.json();
  const { predmetId, dokazId, tipAnalize, opis, datumPocetka, rok, pragUpozorenjaDana } = telo;

  if (!predmetId || !dokazId || !tipAnalize || !rok)
    return NextResponse.json({ ok: false, greska: "Obavezna polja: predmetId, dokazId, tipAnalize, rok." }, { status: 400 });

  const dokaz = await prisma.dokaz.findUnique({ where: { id: dokazId } });
  if (!dokaz) return NextResponse.json({ ok: false, greska: "Dokaz nije pronađen." }, { status: 404 });
  if (dokaz.predmetId !== predmetId)
    return NextResponse.json({ ok: false, greska: "Dokaz ne pripada ovom predmetu." }, { status: 400 });

  const zahtev = await prisma.$transaction(async (tx) => {
    const z = await tx.zahtevZaAnalizu.create({
      data: {
        predmetId,
        dokazId,
        tipAnalize,
        opis: opis || null,
        datumPocetka: datumPocetka ? new Date(datumPocetka) : null,
        rok: new Date(rok),
        pragUpozorenjaDana: pragUpozorenjaDana ?? 3,
        istraziteljId: korisnikId,
        status: "KREIRAN",
      },
    });
    await tx.istorijaStatusaAnalize.create({
      data: { zahtevId: z.id, stariStatus: null, noviStatus: "KREIRAN", iniciraoId: korisnikId },
    });
    return z;
  });

  return NextResponse.json({ ok: true, podaci: zahtev }, { status: 201 });
}
