// ─── Izmena zahteva za analizu ────────────────────────────────────────────────
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect, notFound } from "next/navigation";
import { prisma } from "@/lib/prisma";
import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { ZahtevForm } from "@/components/analize/ZahtevForm";

export const metadata = { title: "Izmena zahteva — FIS" };

type Params = { params: Promise<{ id: string }> };

export default async function IzmeniZahtevPage({ params }: Params) {
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  if (session.user.uloga !== "ISTRAZITELJ" && session.user.uloga !== "ADMINISTRATOR")
    redirect("/analize");

  const { id } = await params;
  const zahtevId = parseInt(id, 10);
  if (isNaN(zahtevId)) notFound();

  const zahtev = await prisma.zahtevZaAnalizu.findUnique({ where: { id: zahtevId } });
  if (!zahtev) notFound();

  // Izmena je moguća samo ako analiza nije još počela
  if (zahtev.status !== "KREIRAN" && zahtev.status !== "DODELJEN") redirect(`/analize/${zahtevId}`);

  const predmeti = await prisma.predmet.findMany({ select: { id: true, naziv: true }, orderBy: { naziv: "asc" } });
  const dokazi   = await prisma.dokaz.findMany({
    select: { id: true, naziv: true, sifraDokaza: true, predmetId: true },
    where: { status: { not: "ARHIVIRANO" } },
    orderBy: { naziv: "asc" },
  });

  return (
    <div className="max-w-2xl mx-auto">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 mb-6 text-sm">
        <Link href={`/analize/${zahtevId}`} className="flex items-center gap-1 text-fis-text2 hover:text-fis-text1 transition-colors">
          <ArrowLeft className="h-4 w-4" />
          Zahtev #{zahtevId}
        </Link>
        <span className="text-fis-text3">/</span>
        <span className="text-fis-text1 font-medium">Izmena</span>
      </div>

      <ZahtevForm
        mode="edit"
        predmeti={predmeti}
        dokazi={dokazi}
        pocetnePodaci={{
          zahtevId: zahtev.id,
          predmetId: zahtev.predmetId,
          dokazId: zahtev.dokazId,
          tipAnalize: zahtev.tipAnalize,
          opis: zahtev.opis,
          datumPocetka: zahtev.datumPocetka,
          rok: zahtev.rok,
          pragUpozorenjaDana: zahtev.pragUpozorenjaDana,
        }}
      />
    </div>
  );
}
