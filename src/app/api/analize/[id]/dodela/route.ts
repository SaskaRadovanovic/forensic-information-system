import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { NextRequest, NextResponse } from "next/server";

type Params = { params: Promise<{ id: string }> };

// ─── POST /api/analize/[id]/dodela — inicijalna dodela veštaka ───────────────

export async function POST(req: NextRequest, { params }: Params) {
  const session = await getServerSession(authOptions);
  if (!session) return NextResponse.json({ ok: false, greska: "Niste prijavljeni." }, { status: 401 });
  if (session.user.uloga !== "ISTRAZITELJ" && session.user.uloga !== "ADMINISTRATOR")
    return NextResponse.json({ ok: false, greska: "Nemate ovlašćenje." }, { status: 403 });

  const { id } = await params;
  const zahtevId = parseInt(id, 10);
  const korisnikId = parseInt(session.user.id, 10);

  const zahtev = await prisma.zahtevZaAnalizu.findUnique({ where: { id: zahtevId } });
  if (!zahtev) return NextResponse.json({ ok: false, greska: "Zahtev nije pronađen." }, { status: 404 });
  if (zahtev.status === "U_TOKU" || zahtev.status === "ZAVRSEN" || zahtev.status === "ODBIJEN")
    return NextResponse.json({ ok: false, greska: "Dodela nije moguća u ovom statusu." }, { status: 409 });

  const telo = await req.json();
  if (!telo.vestakId) return NextResponse.json({ ok: false, greska: "vestakId je obavezan." }, { status: 400 });

  const vestakProfil = await prisma.vestak.findUnique({ where: { idKorisnik: telo.vestakId } });
  if (!vestakProfil) return NextResponse.json({ ok: false, greska: "Veštak nije pronađen." }, { status: 404 });

  await prisma.$transaction(async (tx) => {
    await tx.zahtevZaAnalizu.update({
      where: { id: zahtevId },
      data: { vestakId: telo.vestakId, status: "DODELJEN" },
    });
    await tx.istorijaDodele.create({
      data: { zahtevId, vestakId: telo.vestakId, dodeliooId: korisnikId, razlogPromene: null },
    });
    if (zahtev.status !== "DODELJEN") {
      await tx.istorijaStatusaAnalize.create({
        data: { zahtevId, stariStatus: zahtev.status, noviStatus: "DODELJEN", iniciraoId: korisnikId },
      });
    }
    await tx.obavestenje.create({
      data: { korisnikId: telo.vestakId, sadrzaj: `Dodeljeni ste kao veštak na zahtev #${zahtevId}.`, tip: "DODELA", zahtevId },
    });
  });

  return NextResponse.json({ ok: true });
}

// ─── PATCH /api/analize/[id]/dodela — preraspodela (promena) veštaka ─────────

export async function PATCH(req: NextRequest, { params }: Params) {
  const session = await getServerSession(authOptions);
  if (!session) return NextResponse.json({ ok: false, greska: "Niste prijavljeni." }, { status: 401 });
  if (session.user.uloga !== "ISTRAZITELJ" && session.user.uloga !== "ADMINISTRATOR")
    return NextResponse.json({ ok: false, greska: "Nemate ovlašćenje." }, { status: 403 });

  const { id } = await params;
  const zahtevId = parseInt(id, 10);
  const korisnikId = parseInt(session.user.id, 10);

  const zahtev = await prisma.zahtevZaAnalizu.findUnique({ where: { id: zahtevId } });
  if (!zahtev) return NextResponse.json({ ok: false, greska: "Zahtev nije pronađen." }, { status: 404 });
  if (!zahtev.vestakId)
    return NextResponse.json({ ok: false, greska: "Zahtev nema dodeljenog veštaka." }, { status: 409 });

  const telo = await req.json();
  if (!telo.vestakId || !telo.razlog?.trim())
    return NextResponse.json({ ok: false, greska: "vestakId i razlog su obavezni." }, { status: 400 });

  const vestakProfil = await prisma.vestak.findUnique({ where: { idKorisnik: telo.vestakId } });
  if (!vestakProfil) return NextResponse.json({ ok: false, greska: "Veštak nije pronađen." }, { status: 404 });

  const stariVestakId = zahtev.vestakId;

  await prisma.$transaction(async (tx) => {
    await tx.zahtevZaAnalizu.update({
      where: { id: zahtevId },
      data: { vestakId: telo.vestakId, status: "DODELJEN" },
    });
    await tx.istorijaDodele.create({
      data: { zahtevId, vestakId: telo.vestakId, dodeliooId: korisnikId, razlogPromene: telo.razlog.trim() },
    });
    if (zahtev.status !== "DODELJEN") {
      await tx.istorijaStatusaAnalize.create({
        data: { zahtevId, stariStatus: zahtev.status, noviStatus: "DODELJEN", iniciraoId: korisnikId },
      });
    }
    // Obaveštenje novom veštaku
    await tx.obavestenje.create({
      data: { korisnikId: telo.vestakId, sadrzaj: `Preraspoređeni ste na zahtev #${zahtevId}.`, tip: "PRERASPOREDBA", zahtevId },
    });
    // Obaveštenje starom veštaku
    if (stariVestakId && stariVestakId !== telo.vestakId) {
      await tx.obavestenje.create({
        data: {
          korisnikId: stariVestakId,
          sadrzaj: `Uklonjeni ste sa zahteva #${zahtevId}. Razlog: ${telo.razlog.trim()}.`,
          tip: "PRERASPOREDBA",
          zahtevId,
        },
      });
    }
  });

  return NextResponse.json({ ok: true });
}
