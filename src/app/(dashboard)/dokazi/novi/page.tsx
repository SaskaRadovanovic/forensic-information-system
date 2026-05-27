// ─── Uvoz zavisnosti ─────────────────────────────────────────────────────────
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/prisma";
import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { DokazForma } from "@/components/dokazi/DokazForma";

// ─── Metadata stranice ───────────────────────────────────────────────────────

export const metadata = { title: "Novi dokaz — FIS" };

// ─── Stranica za kreiranje novog dokaza ─────────────────────────────────────

export default async function NoviDokazPage() {
  // Provera autentifikacije — preusmeravanje na login ako korisnik nije ulogovan
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  // Provera autorizacije — samo TEHNICAR i ADMINISTRATOR mogu kreirati dokaze
  const uloga = session.user.uloga;
  if (uloga !== "TEHNICAR" && uloga !== "ADMINISTRATOR") {
    redirect("/dokazi");
  }

  // Učitavanje svih predmeta za dropdown selekciju
  const predmeti = await prisma.predmet.findMany({
    select: { id: true, naziv: true },
    orderBy: { naziv: "asc" },
  });

  return (
    <div className="max-w-2xl mx-auto space-y-6">

      {/* Breadcrumb navigacija ka listi dokaza */}
      <div className="flex items-center gap-2 text-sm">
        <Link
          href="/dokazi"
          className="flex items-center gap-1 text-fis-text2 hover:text-fis-text1 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" />
          Dokazi
        </Link>
        <span className="text-fis-text3">/</span>
        <span className="text-fis-text1 font-medium">Novi dokaz</span>
      </div>

      {/* Forma za kreiranje novog dokaza */}
      <DokazForma mode="create" predmeti={predmeti} />
    </div>
  );
}
