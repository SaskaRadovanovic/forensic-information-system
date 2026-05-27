import { notFound, redirect } from "next/navigation";
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import Link from "next/link";
import { ArrowLeft, Tag } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { TagSelector } from "@/components/dokumenti/TagSelector";

export const metadata = { title: "Upravljanje tagovima — FIS" };

export default async function DokumentTagoviPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  const { id } = await params;
  const dokumentId = parseInt(id, 10);
  if (isNaN(dokumentId)) notFound();

  const [dokument, sviTagovi] = await Promise.all([
    prisma.dokument.findUnique({
      where: { id: dokumentId },
      select: {
        naziv: true,
        tagovi: { select: { tagId: true } },
      },
    }),
    prisma.tag.findMany({ orderBy: { naziv: "asc" }, select: { id: true, naziv: true, boja: true } }),
  ]);

  if (!dokument) notFound();

  const odabraniTagIds = dokument.tagovi.map((t) => t.tagId);

  return (
    <div className="max-w-2xl mx-auto">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 mb-6 text-sm">
        <Link
          href="/dokumentacija"
          className="text-fis-text2 hover:text-fis-text1 transition-colors"
        >
          Dokumentacija
        </Link>
        <span className="text-fis-text3">/</span>
        <Link
          href={`/dokumentacija/${dokumentId}`}
          className="flex items-center gap-1 text-fis-text2 hover:text-fis-text1 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" />
          {dokument.naziv}
        </Link>
        <span className="text-fis-text3">/</span>
        <span className="text-fis-text1 font-medium">Tagovi</span>
      </div>

      {/* TagSelector (SCRUM-34) */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Tag className="h-4 w-4" />
            Dodeli tagove — {dokument.naziv}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <TagSelector
            dokumentId={dokumentId}
            sviTagovi={sviTagovi}
            odabraniTagIds={odabraniTagIds}
          />
        </CardContent>
      </Card>
    </div>
  );
}
