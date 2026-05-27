import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Folder, Plus } from "lucide-react";

export const metadata = { title: "Predmeti — FIS" };

// TODO [TIM-PREDMETI → TIM-ANALIZE]: Integracija provere faze predmeta
// Kada implementujete prelaze između faza predmeta (npr. ISTRAGA → SUĐENJE),
// dodajte proveru da predmet nema aktivnih zahteva za analizu (status U_TOKU ili DODELJEN)
// pre nego što dozvolite prelaz. Primer provere u Prismi:
//
//   const aktivneAnalize = await prisma.zahtevZaAnalizu.count({
//     where: { predmetId, status: { in: ["KREIRAN", "DODELJEN", "U_TOKU"] } },
//   });
//   if (aktivneAnalize > 0) {
//     throw new Error("Predmet ima aktivne forenzičke analize. Završite ih pre promene faze.");
//   }
//
// Relevantne tabele: ZahtevZaAnalizu (polje predmetId, status)
// Kontakt za pitanja: tim zadužen za modul Analize (src/app/(dashboard)/analize/)

export default async function PredmetiPage() {
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  return (
    <div className="space-y-6">

      {/* ── Header ────────────────────────────────────────────────────────── */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-semibold text-fis-text1">Predmeti</h1>
          <p className="mt-0.5 text-sm text-fis-text2">
            Upravljanje forenzičkim predmetima
          </p>
        </div>
        <Button
          disabled
          className="gap-2 bg-fis-yellow text-black font-semibold opacity-50 cursor-not-allowed"
        >
          <Plus className="h-4 w-4" />
          Novi predmet
        </Button>
      </div>

      {/* ── Table ─────────────────────────────────────────────────────────── */}
      <div className="rounded-xl border border-fis-surface3 bg-fis-surface overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-fis-surface3 bg-fis-surface2">
              <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-fis-text3">
                ID
              </th>
              <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-fis-text3">
                Naziv
              </th>
              <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-fis-text3">
                Faza
              </th>
              <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-fis-text3">
                Datum otvaranja
              </th>
              <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-fis-text3">
                Akcije
              </th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colSpan={5} className="px-4 py-16 text-center">
                <div className="flex flex-col items-center gap-3">
                  <Folder className="h-10 w-10 text-fis-text3" />
                  <div>
                    <p className="font-medium text-fis-text2">
                      Modul za predmete je u razvoju
                    </p>
                    <p className="mt-1 text-xs text-fis-text3">
                      Ovaj modul implementuje tim zadužen za predmete.
                    </p>
                  </div>
                  <Badge className="bg-fis-surface3 text-fis-text2 border-0 text-xs">
                    Sprint 2
                  </Badge>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  );
}
