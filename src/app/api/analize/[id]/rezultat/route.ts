import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { NextRequest, NextResponse } from "next/server";

type Params = { params: Promise<{ id: string }> };

// ─── GET /api/analize/[id]/rezultat — pregled rezultata ──────────────────────

export async function GET(_req: NextRequest, { params }: Params) {
  const session = await getServerSession(authOptions);
  if (!session) return NextResponse.json({ ok: false, greska: "Niste prijavljeni." }, { status: 401 });

  const { id } = await params;
  const zahtevId = parseInt(id, 10);

  const rezultat = await prisma.rezultatAnalize.findUnique({
    where: { zahtevId },
    include: { uneao: { select: { ime: true, prezime: true } } },
  });

  if (!rezultat) return NextResponse.json({ ok: false, greska: "Rezultat nije pronađen." }, { status: 404 });
  return NextResponse.json({ ok: true, podaci: rezultat });
}

// ─── POST /api/analize/[id]/rezultat — unos rezultata ────────────────────────

export async function POST(req: NextRequest, { params }: Params) {
  const session = await getServerSession(authOptions);
  if (!session) return NextResponse.json({ ok: false, greska: "Niste prijavljeni." }, { status: 401 });
  if (session.user.uloga !== "VESTAK")
    return NextResponse.json({ ok: false, greska: "Samo veštak može unositi rezultate." }, { status: 403 });

  const { id } = await params;
  const zahtevId = parseInt(id, 10);
  const korisnikId = parseInt(session.user.id, 10);

  const zahtev = await prisma.zahtevZaAnalizu.findUnique({
    where: { id: zahtevId },
    include: { rezultat: true },
  });
  if (!zahtev) return NextResponse.json({ ok: false, greska: "Zahtev nije pronađen." }, { status: 404 });
  if (zahtev.vestakId !== korisnikId)
    return NextResponse.json({ ok: false, greska: "Niste dodeljeni veštak za ovu analizu." }, { status: 403 });
  if (zahtev.status !== "U_TOKU")
    return NextResponse.json({ ok: false, greska: "Analiza mora biti u toku da bi se uneo rezultat." }, { status: 409 });
  if (zahtev.rezultat)
    return NextResponse.json({ ok: false, greska: "Rezultat je već unet." }, { status: 409 });

  const telo = await req.json();
  if (!telo.sadrzaj?.trim())
    return NextResponse.json({ ok: false, greska: "Sadržaj rezultata je obavezan." }, { status: 400 });

  await prisma.$transaction(async (tx) => {
    await tx.rezultatAnalize.create({
      data: { zahtevId, sadrzaj: telo.sadrzaj.trim(), uneaoId: korisnikId },
    });
    await tx.obavestenje.create({
      data: {
        korisnikId: zahtev.istraziteljId,
        sadrzaj: `Veštak je uneo rezultate za analizu #${zahtevId}. Čeka se verifikacija.`,
        tip: "REZULTAT_UNET",
        zahtevId,
      },
    });
  });

  return NextResponse.json({ ok: true }, { status: 201 });
}
