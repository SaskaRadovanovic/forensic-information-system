import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { NextRequest, NextResponse } from "next/server";

// ─── POST /api/dokazi/zahtevi — podnošenje zahteva za dokaz ─────────────────
// Ovaj endpoint koriste kolege iz drugih modula

export async function POST(req: NextRequest) {
  // Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) {
    return NextResponse.json({ ok: false, greska: "Niste prijavljeni." }, { status: 401 });
  }

  const telo = await req.json();

  // Validacija obaveznih polja
  if (!telo.dokazId || !telo.tip) {
    return NextResponse.json(
      { ok: false, greska: "Nedostaju obavezna polja: dokazId, tip (PREDAJA ili POVRACAJ)." },
      { status: 400 }
    );
  }

  // Validacija tipa zahteva
  if (!["PREDAJA", "POVRACAJ"].includes(telo.tip)) {
    return NextResponse.json(
      { ok: false, greska: "Tip mora biti PREDAJA ili POVRACAJ." },
      { status: 400 }
    );
  }

  // Provera da dokaz postoji
  const dokaz = await prisma.dokaz.findUnique({ where: { id: telo.dokazId } });
  if (!dokaz) {
    return NextResponse.json({ ok: false, greska: "Dokaz nije pronađen." }, { status: 404 });
  }

  // Kreiranje zahteva
  const korisnikId = parseInt(session.user.id, 10);
  const zahtev = await prisma.zahtevZaDokaz.create({
    data: {
      dokazId: telo.dokazId,
      tip: telo.tip,
      razlog: telo.razlog || null,
      podnosilacId: korisnikId,
    },
  });

  return NextResponse.json({ ok: true, podaci: zahtev }, { status: 201 });
}
