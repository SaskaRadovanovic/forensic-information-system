import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { NextRequest, NextResponse } from "next/server";
import { StatusAnalize } from "@prisma/client";

type Params = { params: Promise<{ id: string }> };

// ─── PATCH /api/analize/[id]/status — promena statusa sa logovanjem ───────────

export async function PATCH(req: NextRequest, { params }: Params) {
  const session = await getServerSession(authOptions);
  if (!session) return NextResponse.json({ ok: false, greska: "Niste prijavljeni." }, { status: 401 });

  const { id } = await params;
  const zahtevId = parseInt(id, 10);
  const korisnikId = parseInt(session.user.id, 10);
  const uloga = session.user.uloga;

  const telo = await req.json();
  const noviStatus: StatusAnalize = telo.status;
  const napomena: string | undefined = telo.napomena;

  if (!noviStatus) return NextResponse.json({ ok: false, greska: "Novi status je obavezan." }, { status: 400 });

  const zahtev = await prisma.zahtevZaAnalizu.findUnique({ where: { id: zahtevId } });
  if (!zahtev) return NextResponse.json({ ok: false, greska: "Zahtev nije pronađen." }, { status: 404 });

  // Proveravamo ovlašćenja za svaki tip prelaza
  if (noviStatus === "U_TOKU") {
    if (uloga !== "VESTAK") return NextResponse.json({ ok: false, greska: "Samo veštak može pokrenuti analizu." }, { status: 403 });
    if (zahtev.vestakId !== korisnikId) return NextResponse.json({ ok: false, greska: "Niste dodeljeni veštak." }, { status: 403 });
    if (zahtev.status !== "DODELJEN") return NextResponse.json({ ok: false, greska: "Analiza mora biti u statusu 'Dodeljen'." }, { status: 409 });
  } else if (noviStatus === "ODBIJEN") {
    if (uloga !== "ADMINISTRATOR" && uloga !== "ISTRAZITELJ")
      return NextResponse.json({ ok: false, greska: "Nemate ovlašćenje." }, { status: 403 });
    if (zahtev.status === "ZAVRSEN" || zahtev.status === "ODBIJEN")
      return NextResponse.json({ ok: false, greska: "Ovaj zahtev ne može biti odbijen." }, { status: 409 });
  } else {
    return NextResponse.json({ ok: false, greska: "Ovaj prelaz statusa nije dozvoljen putem ovog endpointa." }, { status: 400 });
  }

  await prisma.$transaction(async (tx) => {
    await tx.zahtevZaAnalizu.update({
      where: { id: zahtevId },
      data: { status: noviStatus, ...(noviStatus === "U_TOKU" ? { datumPocetka: new Date() } : {}) },
    });
    await tx.istorijaStatusaAnalize.create({
      data: { zahtevId, stariStatus: zahtev.status, noviStatus, iniciraoId: korisnikId, napomena: napomena ?? null },
    });
    // Obaveštenje zainteresovanim stranama
    if (noviStatus === "U_TOKU") {
      await tx.obavestenje.create({
        data: { korisnikId: zahtev.istraziteljId, sadrzaj: `Veštak je pokrenuo analizu #${zahtevId}.`, tip: "PROMENA_STATUSA", zahtevId },
      });
    } else if (noviStatus === "ODBIJEN" && zahtev.vestakId) {
      await tx.obavestenje.create({
        data: { korisnikId: zahtev.vestakId, sadrzaj: `Zahtev #${zahtevId} je odbijen.`, tip: "PROMENA_STATUSA", zahtevId },
      });
    }
  });

  return NextResponse.json({ ok: true });
}
