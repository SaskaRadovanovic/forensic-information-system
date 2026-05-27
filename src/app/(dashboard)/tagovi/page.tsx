import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/prisma";
import Link from "next/link";
import { Plus, Tag, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { TagBadge } from "@/components/ui/tag-badge";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { ObrisiTagDugme } from "@/components/tagovi/ObrisiTagDugme";

export const metadata = { title: "Tagovi — FIS" };

export default async function TagoviPage() {
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  // Samo administrator može upravljati tagovima (SCRUM-47)
  if (session.user.uloga !== "ADMINISTRATOR") {
    redirect("/dokumentacija");
  }

  const tagovi = await prisma.tag.findMany({
    orderBy: { naziv: "asc" },
    include: {
      _count: { select: { dokumenti: true } },
    },
  });

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-fis-text1">Tagovi</h1>
          <p className="text-sm text-fis-text2 mt-1">
            {tagovi.length === 0 ? "Nema tagova" : `${tagovi.length} tagova`}
          </p>
        </div>
        <Link href="/tagovi/novi">
          <Button>
            <Plus className="h-4 w-4 mr-2" />
            Novi tag
          </Button>
        </Link>
      </div>

      {/* Tabela tagova */}
      {tagovi.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-fis-surface3 bg-fis-surface py-20 text-center">
          <Tag className="h-12 w-12 text-fis-text3 mb-4" />
          <h3 className="text-lg font-medium text-fis-text2">Nema tagova</h3>
          <p className="text-sm text-fis-text3 mt-1 mb-4">
            Kreirajte prvi tag klikom na dugme iznad.
          </p>
        </div>
      ) : (
        <div className="rounded-xl border border-fis-surface3 bg-fis-surface overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow className="bg-fis-surface2 hover:bg-fis-surface2 border-b border-fis-surface3">
                <TableHead>Naziv taga</TableHead>
                <TableHead>Korišćenost</TableHead>
                <TableHead className="w-16" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {tagovi.map((tag) => (
                <TableRow key={tag.id} className="border-b border-fis-surface3 hover:bg-fis-surface2">
                  <TableCell>
                    <TagBadge naziv={tag.naziv} boja={tag.boja} className="text-sm" />
                  </TableCell>
                  <TableCell className="text-fis-text2 text-sm">
                    {tag._count.dokumenti === 0
                      ? "Nije dodeljen"
                      : `${tag._count.dokumenti} dokument${
                          tag._count.dokumenti === 1
                            ? ""
                            : tag._count.dokumenti < 5
                            ? "a"
                            : "a"
                        }`}
                  </TableCell>
                  <TableCell>
                    <ObrisiTagDugme tagId={tag.id} naziv={tag.naziv} />
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}
    </div>
  );
}
