import { NextRequest, NextResponse } from "next/server";
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { absolutePath } from "@/lib/uploads";
import fs from "fs/promises";

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  // Auth provera
  const session = await getServerSession(authOptions);
  if (!session) {
    return NextResponse.json({ greska: "Nije autorizovano." }, { status: 401 });
  }

  const { id } = await params;
  const dokumentId = parseInt(id, 10);
  if (isNaN(dokumentId)) {
    return NextResponse.json({ greska: "Nevažeći ID." }, { status: 400 });
  }

  // Dohvati dokument iz baze
  const dok = await prisma.dokument.findUnique({
    where: { id: dokumentId },
    select: { putanja: true, naziv: true },
  });

  if (!dok) {
    return NextResponse.json({ greska: "Dokument nije pronađen." }, { status: 404 });
  }

  // Čitaj fajl sa diska
  try {
    const fullPath = absolutePath(dok.putanja);
    const buffer = await fs.readFile(fullPath);

    const safeNaziv = dok.naziv.replace(/[^a-zA-Z0-9_\- ]/g, "_");

    return new NextResponse(buffer, {
      headers: {
        "Content-Type": "application/pdf",
        "Content-Disposition": `attachment; filename="${safeNaziv}.pdf"`,
        "Content-Length": String(buffer.length),
      },
    });
  } catch {
    return NextResponse.json(
      { greska: "Fajl nije pronađen na serveru." },
      { status: 404 }
    );
  }
}
