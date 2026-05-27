// ─── Dodela / preraspodela veštaka za analizu ─────────────────────────────────
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect, notFound } from "next/navigation";
import { prisma } from "@/lib/prisma";
import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { DodelaForm } from "@/components/analize/DodelaForm";

export const metadata = { title: "Dodela veštaka — FIS" };

type Params = { params: Promise<{ id: string }> };

export default async function DodelaPage({ params }: Params) {
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  if (session.user.uloga !== "ISTRAZITELJ" && session.user.uloga !== "ADMINISTRATOR")
    redirect("/analize");

  const { id } = await params;
  const zahtevId = parseInt(id, 10);
  if (isNaN(zahtevId)) notFound();

  const zahtev = await prisma.zahtevZaAnalizu.findUnique({
    where: { id: zahtevId },
    select: { id: true, status: true, vestakId: true },
  });
  if (!zahtev) notFound();

  if (zahtev.status === "U_TOKU" || zahtev.status === "ZAVRSEN" || zahtev.status === "ODBIJEN")
    redirect(`/analize/${zahtevId}`);

  // Učitavamo sve veštake za odabir
  const vestaci = await prisma.vestak.findMany({
    include: { korisnik: { select: { ime: true, prezime: true } } },
    orderBy: { korisnik: { prezime: "asc" } },
  });

  return (
    <div className="max-w-xl mx-auto">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 mb-6 text-sm">
        <Link href={`/analize/${zahtevId}`} className="flex items-center gap-1 text-fis-text2 hover:text-fis-text1 transition-colors">
          <ArrowLeft className="h-4 w-4" />
          Zahtev #{zahtevId}
        </Link>
        <span className="text-fis-text3">/</span>
        <span className="text-fis-text1 font-medium">
          {zahtev.vestakId ? "Preraspodela" : "Dodela veštaka"}
        </span>
      </div>

      <DodelaForm
        zahtevId={zahtev.id}
        trenutniVestakId={zahtev.vestakId}
        vestaci={vestaci}
      />
    </div>
  );
}
