import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { NextRequest, NextResponse } from "next/server";

type Params = { params: Promise<{ id: string }> };

// ─── PATCH /api/obavestenja/[id]/procitano — označavanje obaveštenja pročitanim

export async function PATCH(_req: NextRequest, { params }: Params) {
  const session = await getServerSession(authOptions);
  if (!session) return NextResponse.json({ ok: false, greska: "Niste prijavljeni." }, { status: 401 });

  const { id } = await params;
  const obavestenjeId = parseInt(id, 10);
  const korisnikId = parseInt(session.user.id, 10);

  const ob = await prisma.obavestenje.findUnique({ where: { id: obavestenjeId } });
  if (!ob) return NextResponse.json({ ok: false, greska: "Obaveštenje nije pronađeno." }, { status: 404 });
  if (ob.korisnikId !== korisnikId)
    return NextResponse.json({ ok: false, greska: "Niste vlasnik ovog obaveštenja." }, { status: 403 });

  await prisma.obavestenje.update({ where: { id: obavestenjeId }, data: { procitano: true } });
  return NextResponse.json({ ok: true });
}
