// ─── Stranica za kreiranje novog zahteva za analizu ──────────────────────────
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/prisma";
import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { ZahtevForm } from "@/components/analize/ZahtevForm";

export const metadata = { title: "Novi zahtev za analizu — FIS" };

export default async function NoviZahtevPage() {
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  // Samo istražitelji mogu kreirati zahteve
  if (session.user.uloga !== "ISTRAZITELJ") redirect("/analize");

  // Učitavamo predmete i dokaze za forme
  const predmeti = await prisma.predmet.findMany({
    select: { id: true, naziv: true },
    orderBy: { naziv: "asc" },
  });

  const dokazi = await prisma.dokaz.findMany({
    select: { id: true, naziv: true, sifraDokaza: true, predmetId: true },
    where: { status: { not: "ARHIVIRANO" } },
    orderBy: { naziv: "asc" },
  });

  return (
    <div className="max-w-2xl mx-auto">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 mb-6 text-sm">
        <Link href="/analize" className="flex items-center gap-1 text-fis-text2 hover:text-fis-text1 transition-colors">
          <ArrowLeft className="h-4 w-4" />
          Analize
        </Link>
        <span className="text-fis-text3">/</span>
        <span className="text-fis-text1 font-medium">Novi zahtev</span>
      </div>

      <ZahtevForm mode="create" predmeti={predmeti} dokazi={dokazi} />
    </div>
  );
}
