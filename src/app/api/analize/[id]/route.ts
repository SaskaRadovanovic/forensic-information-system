import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { NextRequest, NextResponse } from "next/server";

type Params = { params: Promise<{ id: string }> };

// ─── GET /api/analize/[id] — detalji zahteva ─────────────────────────────────

export async function GET(_req: NextRequest, { params }: Params) {
  const session = await getServerSession(authOptions);
  if (!session) return NextResponse.json({ ok: false, greska: "Niste prijavljeni." }, { status: 401 });

  const { id } = await params;
  const zahtevId = parseInt(id, 10);
  if (isNaN(zahtevId)) return NextResponse.json({ ok: false, greska: "Nevažeći ID." }, { status: 400 });

  const zahtev = await prisma.zahtevZaAnalizu.findUnique({
    where: { id: zahtevId },
    include: {
      predmet: true,
      dokaz: { select: { id: true, naziv: true, sifraDokaza: true, tipDokaza: true } },
      istrazitelj: { include: { korisnik: { select: { ime: true, prezime: true, email: true } } } },
      vestak: { include: { korisnik: { select: { ime: true, prezime: true, email: true } } } },
      rezultat: { include: { uneao: { select: { ime: true, prezime: true } } } },
      istorijaDodela: {
        include: {
          vestak: { include: { korisnik: { select: { ime: true, prezime: true } } } },
          dodelio: { select: { ime: true, prezime: true } },
        },
        orderBy: { datumDodele: "desc" },
      },
      istorijaStatusa: {
        include: { inicirao: { select: { ime: true, prezime: true } } },
        orderBy: { datumVreme: "asc" },
      },
    },
  });

  if (!zahtev) return NextResponse.json({ ok: false, greska: "Zahtev nije pronađen." }, { status: 404 });
  return NextResponse.json({ ok: true, podaci: zahtev });
}

// ─── PATCH /api/analize/[id] — izmena zahteva ────────────────────────────────

export async function PATCH(req: NextRequest, { params }: Params) {
  const session = await getServerSession(authOptions);
  if (!session) return NextResponse.json({ ok: false, greska: "Niste prijavljeni." }, { status: 401 });
  if (session.user.uloga !== "ISTRAZITELJ" && session.user.uloga !== "ADMINISTRATOR")
    return NextResponse.json({ ok: false, greska: "Nemate ovlašćenje." }, { status: 403 });

  const { id } = await params;
  const zahtevId = parseInt(id, 10);
  if (isNaN(zahtevId)) return NextResponse.json({ ok: false, greska: "Nevažeći ID." }, { status: 400 });

  const zahtev = await prisma.zahtevZaAnalizu.findUnique({ where: { id: zahtevId } });
  if (!zahtev) return NextResponse.json({ ok: false, greska: "Zahtev nije pronađen." }, { status: 404 });
  if (zahtev.status !== "KREIRAN" && zahtev.status !== "DODELJEN")
    return NextResponse.json({ ok: false, greska: "Zahtev se može izmeniti samo dok analiza nije započeta." }, { status: 409 });

  const telo = await req.json();
  await prisma.zahtevZaAnalizu.update({
    where: { id: zahtevId },
    data: {
      tipAnalize: telo.tipAnalize,
      opis: telo.opis || null,
      datumPocetka: telo.datumPocetka ? new Date(telo.datumPocetka) : null,
      rok: telo.rok ? new Date(telo.rok) : undefined,
      pragUpozorenjaDana: telo.pragUpozorenjaDana,
    },
  });

  return NextResponse.json({ ok: true });
}

// ─── DELETE /api/analize/[id] — brisanje sa logovanjem razloga ───────────────

export async function DELETE(req: NextRequest, { params }: Params) {
  const session = await getServerSession(authOptions);
  if (!session) return NextResponse.json({ ok: false, greska: "Niste prijavljeni." }, { status: 401 });
  if (session.user.uloga !== "ADMINISTRATOR")
    return NextResponse.json({ ok: false, greska: "Samo administrator može brisati zahteve." }, { status: 403 });

  const { id } = await params;
  const zahtevId = parseInt(id, 10);
  if (isNaN(zahtevId)) return NextResponse.json({ ok: false, greska: "Nevažeći ID." }, { status: 400 });

  const telo = await req.json();
  if (!telo.razlog?.trim())
    return NextResponse.json({ ok: false, greska: "Razlog brisanja je obavezan." }, { status: 400 });

  const zahtev = await prisma.zahtevZaAnalizu.findUnique({ where: { id: zahtevId } });
  if (!zahtev) return NextResponse.json({ ok: false, greska: "Zahtev nije pronađen." }, { status: 404 });
  if (zahtev.status === "U_TOKU" || zahtev.status === "ZAVRSEN")
    return NextResponse.json({ ok: false, greska: "Ne može se obrisati zahtev koji je u toku ili završen." }, { status: 409 });

  const korisnikId = parseInt(session.user.id, 10);

  await prisma.$transaction(async (tx) => {
    await tx.logBrisanjaZahteva.create({
      data: { zahtevId, razlog: telo.razlog.trim(), obrisaoId: korisnikId },
    });
    await tx.zahtevZaAnalizu.delete({ where: { id: zahtevId } });
  });

  return NextResponse.json({ ok: true });
}
