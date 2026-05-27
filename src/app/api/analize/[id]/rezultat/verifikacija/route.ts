import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { NextRequest, NextResponse } from "next/server";

type Params = { params: Promise<{ id: string }> };

// ─── PATCH /api/analize/[id]/rezultat/verifikacija — verifikacija rezultata ──

export async function PATCH(_req: NextRequest, { params }: Params) {
  const session = await getServerSession(authOptions);
  if (!session) return NextResponse.json({ ok: false, greska: "Niste prijavljeni." }, { status: 401 });
  if (session.user.uloga !== "ISTRAZITELJ" && session.user.uloga !== "ADMINISTRATOR")
    return NextResponse.json({ ok: false, greska: "Samo istražitelji mogu verifikovati rezultate." }, { status: 403 });

  const { id } = await params;
  const zahtevId = parseInt(id, 10);
  const korisnikId = parseInt(session.user.id, 10);

  const zahtev = await prisma.zahtevZaAnalizu.findUnique({
    where: { id: zahtevId },
    include: { rezultat: true },
  });
  if (!zahtev) return NextResponse.json({ ok: false, greska: "Zahtev nije pronađen." }, { status: 404 });
  if (!zahtev.rezultat) return NextResponse.json({ ok: false, greska: "Rezultat još nije unet." }, { status: 404 });
  if (zahtev.rezultat.verifikovan)
    return NextResponse.json({ ok: false, greska: "Rezultat je već verifikovan." }, { status: 409 });

  await prisma.$transaction(async (tx) => {
    await tx.rezultatAnalize.update({ where: { zahtevId }, data: { verifikovan: true } });
    await tx.zahtevZaAnalizu.update({ where: { id: zahtevId }, data: { status: "ZAVRSEN" } });
    await tx.istorijaStatusaAnalize.create({
      data: {
        zahtevId,
        stariStatus: zahtev.status,
        noviStatus: "ZAVRSEN",
        iniciraoId: korisnikId,
        napomena: "Rezultat verifikovan.",
      },
    });
    if (zahtev.vestakId) {
      await tx.obavestenje.create({
        data: {
          korisnikId: zahtev.vestakId,
          sadrzaj: `Vaši rezultati za analizu #${zahtevId} su verifikovani. Analiza je završena.`,
          tip: "VERIFIKACIJA",
          zahtevId,
        },
      });
    }
  });

  return NextResponse.json({ ok: true });
}
