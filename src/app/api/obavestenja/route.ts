import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { NextRequest, NextResponse } from "next/server";

// ─── GET /api/obavestenja — lista obaveštenja za prijavljenog korisnika ───────

export async function GET(req: NextRequest) {
  const session = await getServerSession(authOptions);
  if (!session) return NextResponse.json({ ok: false, greska: "Niste prijavljeni." }, { status: 401 });

  const korisnikId = parseInt(session.user.id, 10);
  const { searchParams } = req.nextUrl;

  // Opcioni parametar: samo_broj=true vraća samo broj nepročitanih (za sidebar badge)
  const samoBroj = searchParams.get("samo_broj") === "true";

  if (samoBroj) {
    const neprocitano = await prisma.obavestenje.count({
      where: { korisnikId, procitano: false },
    });
    return NextResponse.json({ ok: true, neprocitano });
  }

  const obavestenja = await prisma.obavestenje.findMany({
    where: { korisnikId },
    include: { zahtev: { select: { id: true, tipAnalize: true, predmet: { select: { naziv: true } } } } },
    orderBy: { datumVreme: "desc" },
  });

  const neprocitano = obavestenja.filter((o) => !o.procitano).length;
  return NextResponse.json({ ok: true, podaci: obavestenja, neprocitano });
}
