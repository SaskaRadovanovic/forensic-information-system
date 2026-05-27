import { notFound, redirect } from "next/navigation";
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { DokumentForm } from "@/components/dokumenti/DokumentForm";

export const metadata = { title: "Izmena dokumenta — FIS" };

export default async function IzmeniDokumentPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");
  if (session.user.uloga === "TEHNICAR") redirect("/dokazi");

  const { id } = await params;
  const dokumentId = parseInt(id, 10);
  if (isNaN(dokumentId)) notFound();

  const [dok, predmeti] = await Promise.all([
    prisma.dokument.findUnique({
      where: { id: dokumentId },
      include: { metapodaci: true },
    }),
    prisma.predmet.findMany({
      select: { id: true, naziv: true },
      orderBy: { naziv: "asc" },
    }),
  ]);

  if (!dok) notFound();

  // Arhivirani dokumenti se ne mogu menjati
  if (dok.status === "ARHIVIRAN") {
    redirect(`/dokumentacija/${dokumentId}`);
  }

  const tipDokumenta =
    dok.metapodaci.find((m) => m.kljuc === "tipDokumenta")?.vrednost ?? "";
  const opis =
    dok.metapodaci.find((m) => m.kljuc === "opis")?.vrednost ?? "";

  return (
    <div className="max-w-2xl mx-auto">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 mb-6 text-sm">
        <Link
          href={`/dokumentacija/${dokumentId}`}
          className="flex items-center gap-1 text-fis-text2 hover:text-fis-text1 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" />
          {dok.naziv}
        </Link>
        <span className="text-fis-text3">/</span>
        <span className="text-fis-text1 font-medium">Izmena</span>
      </div>

      {/* SCRUM-41: DokumentForm u edit modu */}
      <DokumentForm
        mode="edit"
        predmeti={predmeti}
        initialValues={{
          id: dok.id,
          naziv: dok.naziv,
          predmetId: dok.predmetId,
          tipDokumenta,
          opis,
          putanja: dok.putanja,
        }}
      />
    </div>
  );
}
