import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { DokumentForm } from "@/components/dokumenti/DokumentForm";
import { ArrowLeft } from "lucide-react";
import Link from "next/link";

export const metadata = {
  title: "Novi dokument — FIS",
};

export default async function NoviDokumentPage() {
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");
  if (session.user.uloga === "TEHNICAR") redirect("/dokazi");

  // Učitavamo sve predmete (SCRUM-36: mapiranje sa predmetom)
  const predmeti = await prisma.predmet.findMany({
    select: { id: true, naziv: true },
    orderBy: { naziv: "asc" },
  });

  return (
    <div className="max-w-2xl mx-auto">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 mb-6 text-sm">
        <Link
          href="/dokumentacija"
          className="flex items-center gap-1 text-fis-text2 hover:text-fis-text1 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" />
          Dokumentacija
        </Link>
        <span className="text-fis-text3">/</span>
        <span className="text-fis-text1 font-medium">Novi dokument</span>
      </div>

      {/* Forma (SCRUM-33, 35, 36, 37, 38) */}
      <DokumentForm mode="create" predmeti={predmeti} />
    </div>
  );
}
