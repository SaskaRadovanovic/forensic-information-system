import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { ListaZahteva } from "@/components/dokazi/ListaZahteva";

// ─── Metadata ───────────────────────────────────────────────────────────────

export const metadata = { title: "Zahtevi za dokaze — FIS" };

// ─── Stranica ───────────────────────────────────────────────────────────────

export default async function ZahteviPage() {
  // Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  // Provera autorizacije — samo TEHNICAR i ADMINISTRATOR
  const uloga = session.user.uloga;
  if (uloga !== "TEHNICAR" && uloga !== "ADMINISTRATOR") {
    redirect("/dokazi");
  }

  // ── Fetch zahteva na čekanju ──────────────────────────────────────────────
  const zahtevi = await prisma.zahtevZaDokaz.findMany({
    where: { status: "NA_CEKANJU" },
    include: {
      dokaz: { select: { sifraDokaza: true, naziv: true } },
      podnosilac: { select: { ime: true, prezime: true } },
    },
    orderBy: { datumKreiranja: "desc" },
  });

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-fis-text1">Zahtevi za dokaze</h1>
          <p className="text-sm text-fis-text2 mt-1">
            {zahtevi.length === 0 ? "Nema zahteva na čekanju" : `${zahtevi.length} zahtev${zahtevi.length === 1 ? "" : "a"} na čekanju`}
          </p>
        </div>
      </div>

      {/* Lista zahteva */}
      <ListaZahteva zahtevi={zahtevi} />
    </div>
  );
}
