import { notFound, redirect } from "next/navigation";
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import Link from "next/link";
import { ObrisiPredmetDugme } from "@/components/predmeti/ObrisiPredmetDugme";
import { ZatvoriPredmetDugme } from "@/components/predmeti/ZatvoriPredmetDugme";
import { PromeniPredmetFazuDugme } from "@/components/predmeti/PromeniPredmetFazuDugme";
import { PredmetDetaljiTabs } from "@/components/predmeti/PredmetDetaljiTabs";
import type { DokazTabItem, AnalizeTabItem, DokumentTabItem } from "@/components/predmeti/PredmetDetaljiTabs";

// ─── 5 faza predmeta ─────────────────────────────────────────────────────────

const REDOSLED_FAZA = [
  "OTVOREN_SLUCAJ",
  "PRIKUPLJANJE_DOKAZA",
  "ANALIZA_DOKAZA",
  "DONOSENJE_ZAKLJUCKA",
  "ZATVOREN_SLUCAJ",
] as const;

const FAZA_LABEL: Record<string, string> = {
  OTVOREN_SLUCAJ:      "OTVOREN\nSLUČAJ",
  PRIKUPLJANJE_DOKAZA: "PRIKUPLJANJE\nDOKAZA",
  ANALIZA_DOKAZA:      "ANALIZA\nDOKAZA",
  DONOSENJE_ZAKLJUCKA: "DONOŠENJE\nZAKLJUČKA",
  ZATVOREN_SLUCAJ:     "ZATVOREN\nSLUČAJ",
};

const FAZA_BADGE: Record<string, string> = {
  OTVOREN_SLUCAJ:      "fis-badge fis-badge-blue",
  PRIKUPLJANJE_DOKAZA: "fis-badge fis-badge-yellow",
  ANALIZA_DOKAZA:      "fis-badge fis-badge-orange",
  DONOSENJE_ZAKLJUCKA: "fis-badge fis-badge-purple",
  ZATVOREN_SLUCAJ:     "fis-badge fis-badge-green",
};

export default async function PredmetDetaljiPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  const { id } = await params;
  const predmetId = parseInt(id, 10);
  if (isNaN(predmetId)) notFound();

  const predmet = await prisma.predmet.findUnique({
    where: { id: predmetId },
    include: {
      dokazi: {
        select: { id: true, sifraDokaza: true, naziv: true, tipDokaza: true, status: true },
        orderBy: { datumPrijema: "desc" },
      },
      dokumenti: {
        select: { id: true, naziv: true, verzija: true, datumKreiranja: true },
        orderBy: { datumKreiranja: "desc" },
      },
      zahteviZaAnalizu: {
        select: {
          id: true,
          tipAnalize: true,
          status: true,
          rok: true,
          dokaz: { select: { id: true, naziv: true, sifraDokaza: true } },
          vestak: { select: { korisnik: { select: { ime: true, prezime: true } } } },
        },
        orderBy: { datumKreiranja: "desc" },
      },
      _count: { select: { dokazi: true, dokumenti: true, zahteviZaAnalizu: true } },
    },
  });

  if (!predmet) notFound();

  const uloga        = session.user.uloga;
  const zatvoren     = predmet.status === "ZATVOREN";
  const mozeMenjati      = (uloga === "ADMINISTRATOR" || uloga === "ISTRAZITELJ") && !zatvoren;
  const mozeZatvoriti    = (uloga === "ADMINISTRATOR" || uloga === "ISTRAZITELJ") && !zatvoren;
  const mozeMenjatiFazu  = (uloga === "ADMINISTRATOR" || uloga === "ISTRAZITELJ") && !zatvoren;
  const mozeObrisati     = uloga === "ADMINISTRATOR";

  // ── Stanje svakog koraka u stepperu ──────────────────────────────────────
  const fazaIdx = REDOSLED_FAZA.indexOf(predmet.faza as any);

  function stepState(i: number): "done" | "active" | "future" {
    if (zatvoren) return "done";
    if (i < fazaIdx) return "done";
    if (i === fazaIdx) return "active";
    return "future";
  }

  function stepKlasa(i: number) {
    return `fis-phase-step ${stepState(i)}`;
  }

  function connectorKlasa(i: number) {
    return stepState(i) === "done" ? "fis-phase-connector done" : "fis-phase-connector";
  }

  // ── Serijalizacija za client komponentu ───────────────────────────────────
  const dokaziTab: DokazTabItem[] = predmet.dokazi.map((d) => ({
    id: d.id, sifraDokaza: d.sifraDokaza, naziv: d.naziv,
    tipDokaza: d.tipDokaza, status: d.status,
  }));

  const analizeTab: AnalizeTabItem[] = predmet.zahteviZaAnalizu.map((z) => ({
    id: z.id,
    tipAnalize: z.tipAnalize,
    status: z.status,
    rokFormatiran: z.rok ? new Date(z.rok).toLocaleDateString("sr-RS") : null,
    dokaz: { id: z.dokaz.id, naziv: z.dokaz.naziv, sifraDokaza: z.dokaz.sifraDokaza },
    vestakIme: z.vestak ? `${z.vestak.korisnik.ime} ${z.vestak.korisnik.prezime}` : null,
  }));

  const dokumentiTab: DokumentTabItem[] = predmet.dokumenti.map((d) => ({
    id: d.id, naziv: d.naziv, verzija: d.verzija,
    datumFormatiran: new Date(d.datumKreiranja).toLocaleDateString("sr-RS"),
  }));

  const datumOtvaranja = new Date(predmet.datumOtvaranja).toLocaleDateString("sr-RS", {
    day: "2-digit", month: "2-digit", year: "numeric",
  });

  return (
    <div>
      {/* ── Breadcrumb ──────────────────────────────────────────────────── */}
      <div className="fis-breadcrumb">
        <Link href="/predmeti" className="fis-btn fis-btn-ghost fis-btn-sm">
          ← Predmeti
        </Link>
      </div>

      {/* ── Eyebrow + Naslov ────────────────────────────────────────────── */}
      <div className="page-eyebrow">Istraga</div>
      <div className="page-title">{predmet.naziv}</div>

      {/* ── Phase stepper — 5 faza ──────────────────────────────────────── */}
      <div className="fis-phase-stepper" style={{ marginBottom: 0 }}>
        {REDOSLED_FAZA.map((faza, i) => (
          <div key={faza} style={{ display: "contents" }}>
            <div className={stepKlasa(i)}>
              <div className="fis-phase-circle">
                {stepState(i) === "done" ? "✓" : `0${i + 1}`}
              </div>
              <div style={{
                fontFamily: "var(--font-mono)",
                fontSize: 9,
                fontWeight: 600,
                textTransform: "uppercase",
                letterSpacing: "1.5px",
                color: stepState(i) === "active"
                  ? "var(--color-fis-yellow)"
                  : stepState(i) === "done"
                  ? "var(--color-fis-green)"
                  : "var(--color-fis-text3)",
                textAlign: "center",
                lineHeight: 1.4,
                marginTop: 6,
                whiteSpace: "pre-line",
              }}>
                {FAZA_LABEL[faza]}
              </div>
            </div>
            {i < REDOSLED_FAZA.length - 1 && (
              <div className={connectorKlasa(i)} />
            )}
          </div>
        ))}
      </div>

      {/* ── Akcijska traka ──────────────────────────────────────────────── */}
      <div style={{
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        padding: "14px 0",
        marginBottom: 20,
        borderBottom: "1px solid var(--color-fis-border)",
        flexWrap: "wrap",
        gap: 8,
      }}>
        <div>
          {mozeMenjatiFazu && (
            <PromeniPredmetFazuDugme predmetId={predmet.id} trenutnaFaza={predmet.faza} />
          )}
        </div>
        <div style={{ display: "flex", gap: 8 }}>
          {mozeZatvoriti && (
            <ZatvoriPredmetDugme predmetId={predmet.id} naziv={predmet.naziv} />
          )}
        </div>
      </div>

      {/* ── Podaci o predmetu ────────────────────────────────────────────── */}
      <div className="fis-card" style={{ marginBottom: 20 }}>
        <div className="fis-card-header">
          <h3>Podaci o predmetu</h3>
          {zatvoren
            ? <span className="fis-badge fis-badge-gray">Zatvoren</span>
            : <span className={FAZA_BADGE[predmet.faza]}>{FAZA_LABEL[predmet.faza]?.replace("\n", " ")}</span>
          }
        </div>
        <div className="fis-card-body">
          <div className="fis-form-grid">

            <div className="fis-form-group full">
              <label>Naziv</label>
              <div className="fis-input" style={{ cursor: "default" }}>
                {predmet.naziv}
              </div>
            </div>

            <div className="fis-form-group">
              <label>Datum otvaranja</label>
              <div className="fis-input" style={{ cursor: "default" }}>
                {datumOtvaranja}
              </div>
            </div>

            <div className="fis-form-group">
              <label>ID predmeta</label>
              <div className="fis-input" style={{ cursor: "default" }}>
                #{predmet.id}
              </div>
            </div>

            {predmet.opis && (
              <div className="fis-form-group full">
                <label>Opis predmeta</label>
                <div
                  className="fis-input"
                  style={{ cursor: "default", minHeight: 90, whiteSpace: "pre-wrap", lineHeight: 1.6 }}
                >
                  {predmet.opis}
                </div>
              </div>
            )}

          </div>
        </div>
      </div>

      {/* ── Tabovi ───────────────────────────────────────────────────────── */}
      <PredmetDetaljiTabs
        dokazi={dokaziTab}
        analize={analizeTab}
        dokumenti={dokumentiTab}
      />

      {/* ── Dugmad ───────────────────────────────────────────────────────── */}
      <div style={{ display: "flex", gap: 8, alignItems: "center", marginTop: 20 }}>
        {mozeMenjati && (
          <Link href={`/predmeti/${predmet.id}/izmeni`} className="fis-btn fis-btn-outline">
            Izmeni
          </Link>
        )}
        {mozeObrisati && (
          <ObrisiPredmetDugme predmetId={predmet.id} naziv={predmet.naziv} />
        )}
      </div>
    </div>
  );
}
