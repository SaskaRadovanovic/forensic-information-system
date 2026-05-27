import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { NextRequest, NextResponse } from "next/server";

// ─── GET /api/dokazi/[id] — detalji dokaza ──────────────────────────────────

export async function GET(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  // Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) {
    return NextResponse.json({ ok: false, greska: "Niste prijavljeni." }, { status: 401 });
  }

  const { id } = await params;
  const dokazId = parseInt(id, 10);
  if (isNaN(dokazId)) {
    return NextResponse.json({ ok: false, greska: "Nevažeći ID." }, { status: 400 });
  }

  // Fetch dokaza sa svim relacijama
  const dokaz = await prisma.dokaz.findUnique({
    where: { id: dokazId },
    include: {
      predmet: true,
      tehnicar: {
        include: { korisnik: { select: { ime: true, prezime: true } } },
      },
      lanacCuvanja: {
        include: {
          tehnicar: { include: { korisnik: { select: { ime: true, prezime: true } } } },
        },
        orderBy: { datumVreme: "desc" },
      },
      bioloskiTrag: true,
      oruzje: true,
      dokumentDokaz: true,
      odeca: true,
      uzorak: true,
    },
  });

  if (!dokaz) {
    return NextResponse.json({ ok: false, greska: "Dokaz nije pronađen." }, { status: 404 });
  }

  return NextResponse.json({ ok: true, podaci: dokaz });
}

// ─── PATCH /api/dokazi/[id] — izmena dokaza ─────────────────────────────────

export async function PATCH(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
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

  const { id } = await params;
  const dokazId = parseInt(id, 10);
  if (isNaN(dokazId)) {
    return NextResponse.json({ ok: false, greska: "Nevažeći ID." }, { status: 400 });
  }

  const telo = await req.json();

  // Ažuriranje dokaza
  const dokaz = await prisma.dokaz.update({
    where: { id: dokazId },
    data: {
      ...(telo.naziv ? { naziv: telo.naziv } : {}),
      ...(telo.opis !== undefined ? { opis: telo.opis } : {}),
      ...(telo.predmetId ? { predmetId: telo.predmetId } : {}),
      ...(telo.lokacijaSkladistenja !== undefined ? { lokacijaSkladistenja: telo.lokacijaSkladistenja } : {}),
    },
  });

  return NextResponse.json({ ok: true, podaci: dokaz });
}
