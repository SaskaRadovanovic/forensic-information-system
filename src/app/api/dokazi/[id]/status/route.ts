import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { NextRequest, NextResponse } from "next/server";

// ─── PATCH /api/dokazi/[id]/status — promena statusa dokaza ─────────────────

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
  if (!telo.status) {
    return NextResponse.json({ ok: false, greska: "Status je obavezan." }, { status: 400 });
  }

  // Validacija dozvoljenih statusa
  const dozvoljeniStatusi = ["PRIJEM", "U_SKLADISTU", "IZDATO_ZA_ANALIZU", "VRACENO", "ARHIVIRANO", "KOMPROMITOVAN"];
  if (!dozvoljeniStatusi.includes(telo.status)) {
    return NextResponse.json({ ok: false, greska: "Nevažeći status." }, { status: 400 });
  }

  // Ažuriranje statusa
  const dokaz = await prisma.dokaz.update({
    where: { id: dokazId },
    data: { status: telo.status },
  });

  return NextResponse.json({ ok: true, podaci: dokaz });
}
