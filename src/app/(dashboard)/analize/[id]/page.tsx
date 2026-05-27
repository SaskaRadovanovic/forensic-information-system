// ─── Detalji zahteva za analizu ───────────────────────────────────────────────
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect, notFound } from "next/navigation";
import { prisma } from "@/lib/prisma";
import Link from "next/link";
import { ArrowLeft, Edit, UserPlus, ClipboardCheck } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { StatusBadge, tipAnalizeNaziv } from "@/components/analize/StatusBadge";
import { AkcioniDugmici } from "@/components/analize/AkcioniDugmici";

export const metadata = { title: "Detalji analize — FIS" };

type Params = { params: Promise<{ id: string }> };

export default async function AnalizaDetaljiPage({ params }: Params) {
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  const { id } = await params;
  const zahtevId = parseInt(id, 10);
  if (isNaN(zahtevId)) notFound();

  // Učitavamo zahtev sa svim relacijama
  const zahtev = await prisma.zahtevZaAnalizu.findUnique({
    where: { id: zahtevId },
    include: {
      predmet:  true,
      dokaz:    { select: { id: true, naziv: true, sifraDokaza: true, tipDokaza: true, status: true } },
      istrazitelj: { include: { korisnik: { select: { ime: true, prezime: true, email: true } } } },
      vestak:      { include: { korisnik: { select: { ime: true, prezime: true, email: true } } } },
      rezultat:    { include: { uneao: { select: { ime: true, prezime: true } } } },
      istorijaDodela: {
        include: {
          vestak:  { include: { korisnik: { select: { ime: true, prezime: true } } } },
          dodelio: { select: { ime: true, prezime: true } },
        },
        orderBy: { datumDodele: "desc" },
      },
      istorijaStatusa: {
        include: { inicirao: { select: { ime: true, prezime: true } } },
        orderBy: { datumVreme: "asc" },
      },
      istorijaIzmena: {
        include: { korisnik: { select: { ime: true, prezime: true } } },
        orderBy: { datumVreme: "desc" },
      },
    },
  });

  if (!zahtev) notFound();

  const korisnikId = parseInt(session.user.id, 10);
  const uloga = session.user.uloga;

  // Odredjujemo koja dugmad da prikazemo
  const mozeIzmeniti = (uloga === "ISTRAZITELJ" || uloga === "ADMINISTRATOR")
    && (zahtev.status === "KREIRAN" || zahtev.status === "DODELJEN");
  const mozeDodatiVestaka = (uloga === "ISTRAZITELJ" || uloga === "ADMINISTRATOR")
    && zahtev.status !== "U_TOKU" && zahtev.status !== "ZAVRSEN" && zahtev.status !== "ODBIJEN";
  const mozeUnestiRezultat = uloga === "VESTAK" && zahtev.vestakId === korisnikId
    && zahtev.status === "U_TOKU" && !zahtev.rezultat;

  // Helper: formatovanje datuma
  function fmt(datum?: Date | null) {
    if (!datum) return "—";
    return new Date(datum).toLocaleDateString("sr-RS");
  }
  function fmtVreme(datum?: Date | null) {
    if (!datum) return "—";
    return new Date(datum).toLocaleString("sr-RS");
  }

  // Helper: human-readable naziv polja za audit log
  const poljeNaziv: Record<string, string> = {
    tipAnalize:         "Tip analize",
    opis:               "Opis",
    datumPocetka:       "Datum početka",
    rok:                "Rok",
    pragUpozorenjaDana: "Prag upozorenja (dana)",
  };

  // Helper: formatuje vrednost iz audit loga (ISO datum → srpski format, ostalo direktno)
  function fmtAuditVrednost(vrednost: string | null): string {
    if (!vrednost || vrednost === "") return "—";
    const d = new Date(vrednost);
    if (!isNaN(d.getTime()) && vrednost.includes("T")) {
      return d.toLocaleDateString("sr-RS");
    }
    return vrednost;
  }

  return (
    <div className="max-w-4xl mx-auto space-y-6">

      {/* ── Breadcrumb ────────────────────────────────────────────────────── */}
      <div className="flex items-center gap-2 text-sm">
        <Link href="/analize" className="flex items-center gap-1 text-fis-text2 hover:text-fis-text1 transition-colors">
          <ArrowLeft className="h-4 w-4" />
          Analize
        </Link>
        <span className="text-fis-text3">/</span>
        <span className="text-fis-text1 font-medium">Zahtev #{zahtev.id}</span>
      </div>

      {/* ── Header sa statusom i akcijama ─────────────────────────────────── */}
      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <div className="flex items-center gap-3 mb-1">
            <h1 className="text-2xl font-bold text-fis-text1">
              {tipAnalizeNaziv(zahtev.tipAnalize)}
            </h1>
            <StatusBadge status={zahtev.status} />
          </div>
          <p className="text-sm text-fis-text2">
            Predmet: <span className="text-fis-text1">{zahtev.predmet.naziv}</span>
          </p>
        </div>

        {/* Dugmad za navigaciju ka akcijama */}
        <div className="flex flex-wrap gap-2">
          {mozeIzmeniti && (
            <Link href={`/analize/${zahtev.id}/izmeni`}>
              <Button size="sm" variant="outline" className="gap-1.5">
                <Edit className="h-3.5 w-3.5" />
                Izmeni
              </Button>
            </Link>
          )}
          {mozeDodatiVestaka && (
            <Link href={`/analize/${zahtev.id}/dodela`}>
              <Button size="sm" variant="outline" className="gap-1.5">
                <UserPlus className="h-3.5 w-3.5" />
                {zahtev.vestakId ? "Prerasporedi" : "Dodeli veštaka"}
              </Button>
            </Link>
          )}
          {mozeUnestiRezultat && (
            <Link href={`/analize/${zahtev.id}/rezultat/unos`}>
              <Button size="sm" className="gap-1.5">
                <ClipboardCheck className="h-3.5 w-3.5" />
                Unesi rezultate
              </Button>
            </Link>
          )}
          {/* Akcioni dugmici (pokreni / odbij / obriši / verifikuj) */}
          <AkcioniDugmici
            zahtevId={zahtev.id}
            status={zahtev.status}
            uloga={uloga}
            jeDodeljenVestak={!!zahtev.vestakId}
            imaRezultat={!!zahtev.rezultat}
            jeVerifikovan={zahtev.rezultat?.verifikovan ?? false}
            vestakIdZahteva={zahtev.vestakId}
            korisnikId={korisnikId}
          />
        </div>
      </div>

      {/* ── Metapodaci u mreži ────────────────────────────────────────────── */}
      <div className="grid grid-cols-2 md:grid-cols-3 gap-4 rounded-xl border border-fis-surface3 bg-fis-surface p-5">
        {[
          { naziv: "Dokaz",          vrednost: `[${zahtev.dokaz.sifraDokaza}] ${zahtev.dokaz.naziv}` },
          { naziv: "Tip analize",    vrednost: tipAnalizeNaziv(zahtev.tipAnalize) },
          { naziv: "Kreiran",        vrednost: fmtVreme(zahtev.datumKreiranja) },
          { naziv: "Datum početka",  vrednost: fmt(zahtev.datumPocetka) },
          { naziv: "Rok",            vrednost: fmt(zahtev.rok) },
          { naziv: "Prag upoz. (dana)", vrednost: String(zahtev.pragUpozorenjaDana) },
          { naziv: "Istražitelj",    vrednost: `${zahtev.istrazitelj.korisnik.ime} ${zahtev.istrazitelj.korisnik.prezime}` },
          { naziv: "Veštak",         vrednost: zahtev.vestak ? `${zahtev.vestak.korisnik.ime} ${zahtev.vestak.korisnik.prezime}` : "Nije dodeljeno" },
        ].map(({ naziv, vrednost }) => (
          <div key={naziv}>
            <p className="text-xs font-semibold uppercase tracking-wider text-fis-text3 mb-0.5">{naziv}</p>
            <p className="text-sm text-fis-text1">{vrednost}</p>
          </div>
        ))}
        {zahtev.opis && (
          <div className="col-span-2 md:col-span-3">
            <p className="text-xs font-semibold uppercase tracking-wider text-fis-text3 mb-0.5">Opis</p>
            <p className="text-sm text-fis-text1 whitespace-pre-wrap">{zahtev.opis}</p>
          </div>
        )}
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">

        {/* ── Rezultati (ako postoje) ──────────────────────────────────────── */}
        {zahtev.rezultat && (
          <div className="md:col-span-2 rounded-xl border border-fis-surface3 bg-fis-surface p-5">
            <div className="flex items-center justify-between mb-3">
              <h2 className="font-semibold text-fis-text1">Rezultat analize</h2>
              {zahtev.rezultat.verifikovan
                ? <Badge className="bg-fis-green/10 text-fis-green border-0 text-xs">Verifikovan</Badge>
                : <Badge className="bg-fis-orange/10 text-fis-orange border-0 text-xs">Čeka verifikaciju</Badge>}
            </div>
            <p className="text-sm text-fis-text2 whitespace-pre-wrap">{zahtev.rezultat.sadrzaj}</p>
            <p className="text-xs text-fis-text3 mt-3">
              Uneo: {zahtev.rezultat.uneao.ime} {zahtev.rezultat.uneao.prezime} —{" "}
              {fmtVreme(zahtev.rezultat.datumUnosa)}
            </p>
          </div>
        )}

        {/* ── Istorija statusa — vertikalni timeline ───────────────────────── */}
        <div className="rounded-xl border border-fis-surface3 bg-fis-surface p-5">
          <h2 className="font-semibold text-fis-text1 mb-4">Istorija statusa</h2>
          <ol className="relative border-l border-fis-surface3 space-y-4 ml-3">
            {zahtev.istorijaStatusa.map((s) => (
              <li key={s.id} className="ml-4">
                <div className="absolute -left-1.5 w-3 h-3 rounded-full bg-fis-surface3 border border-fis-surface" />
                <div className="flex items-center gap-2 mb-0.5">
                  <StatusBadge status={s.noviStatus} />
                  <span className="text-xs text-fis-text3">{fmtVreme(s.datumVreme)}</span>
                </div>
                <p className="text-xs text-fis-text2">
                  {s.inicirao.ime} {s.inicirao.prezime}
                  {s.napomena && <span className="italic"> — {s.napomena}</span>}
                </p>
              </li>
            ))}
          </ol>
        </div>

        {/* ── Istorija dodela ───────────────────────────────────────────────── */}
        <div className="rounded-xl border border-fis-surface3 bg-fis-surface p-5">
          <h2 className="font-semibold text-fis-text1 mb-4">Istorija dodela veštaka</h2>
          {zahtev.istorijaDodela.length === 0 ? (
            <p className="text-sm text-fis-text3 italic">Veštak još nije dodeljen.</p>
          ) : (
            <ol className="space-y-3">
              {zahtev.istorijaDodela.map((d) => (
                <li key={d.id} className="rounded-lg bg-fis-surface2 p-3">
                  <p className="text-sm font-medium text-fis-text1">
                    {d.vestak.korisnik.ime} {d.vestak.korisnik.prezime}
                  </p>
                  <p className="text-xs text-fis-text3">
                    Dodelio: {d.dodelio.ime} {d.dodelio.prezime} — {fmtVreme(d.datumDodele)}
                  </p>
                  {d.razlogPromene && (
                    <p className="text-xs text-fis-orange mt-1 italic">
                      Razlog preraspodele: {d.razlogPromene}
                    </p>
                  )}
                </li>
              ))}
            </ol>
          )}
        </div>

      </div>

      {/* ── Istorija izmena zahteva ───────────────────────────────────────── */}
      {zahtev.istorijaIzmena.length > 0 && (
        <div className="rounded-xl border border-fis-surface3 bg-fis-surface p-5">
          <h2 className="font-semibold text-fis-text1 mb-4">Istorija izmena zahteva</h2>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-fis-surface3 text-left">
                  <th className="pb-2 pr-4 text-xs font-semibold uppercase tracking-wider text-fis-text3">Datum</th>
                  <th className="pb-2 pr-4 text-xs font-semibold uppercase tracking-wider text-fis-text3">Polje</th>
                  <th className="pb-2 pr-4 text-xs font-semibold uppercase tracking-wider text-fis-text3">Stara vrednost</th>
                  <th className="pb-2 pr-4 text-xs font-semibold uppercase tracking-wider text-fis-text3">Nova vrednost</th>
                  <th className="pb-2 text-xs font-semibold uppercase tracking-wider text-fis-text3">Izmenio</th>
                </tr>
              </thead>
              <tbody>
                {zahtev.istorijaIzmena.map((izmena) => (
                  <tr key={izmena.id} className="border-b border-fis-surface3/50 hover:bg-fis-surface2">
                    <td className="py-2 pr-4 text-fis-text3 whitespace-nowrap">{fmtVreme(izmena.datumVreme)}</td>
                    <td className="py-2 pr-4 text-fis-text2 font-medium whitespace-nowrap">
                      {poljeNaziv[izmena.polje] ?? izmena.polje}
                    </td>
                    <td className="py-2 pr-4 text-fis-text3 line-through">
                      {fmtAuditVrednost(izmena.staraVrednost)}
                    </td>
                    <td className="py-2 pr-4 text-fis-text1">
                      {fmtAuditVrednost(izmena.novaVrednost)}
                    </td>
                    <td className="py-2 text-fis-text2 whitespace-nowrap">
                      {izmena.korisnik.ime} {izmena.korisnik.prezime}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

    </div>
  );
}
