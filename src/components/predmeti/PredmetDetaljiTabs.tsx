"use client";

import { useState } from "react";
import Link from "next/link";

type Tab = "dokazi" | "analize" | "dokumenti";

export type DokazTabItem = {
  id: number;
  sifraDokaza: string;
  naziv: string;
  tipDokaza: string;
  status: string;
};

export type AnalizeTabItem = {
  id: number;
  tipAnalize: string;
  status: string;
  rokFormatiran: string | null;
  dokaz: { id: number; naziv: string; sifraDokaza: string };
  vestakIme: string | null;
};

export type DokumentTabItem = {
  id: number;
  naziv: string;
  verzija: number;
  datumFormatiran: string;
};

interface Props {
  dokazi: DokazTabItem[];
  analize: AnalizeTabItem[];
  dokumenti: DokumentTabItem[];
}

// ─── Helpers za badge klase ──────────────────────────────────────────────────

function tipDokazaBadge(tip: string) {
  const m: Record<string, string> = {
    BIOLOSKI_TRAG: "fis-badge fis-badge-red",
    ORUZJE:        "fis-badge fis-badge-orange",
    DOKUMENT:      "fis-badge fis-badge-blue",
    ODECA:         "fis-badge fis-badge-purple",
    UZORAK:        "fis-badge fis-badge-yellow",
  };
  return m[tip] ?? "fis-badge fis-badge-gray";
}

function tipDokazaLabel(tip: string) {
  const m: Record<string, string> = {
    BIOLOSKI_TRAG: "Biološki",
    ORUZJE:        "Oružje",
    DOKUMENT:      "Dokument",
    ODECA:         "Odeća",
    UZORAK:        "Uzorak",
  };
  return m[tip] ?? tip;
}

function statusDokazaBadge(status: string) {
  const m: Record<string, string> = {
    PRIJEM:             "fis-badge fis-badge-yellow",
    U_SKLADISTU:        "fis-badge fis-badge-green",
    IZDATO_ZA_ANALIZU:  "fis-badge fis-badge-yellow",
    VRACENO:            "fis-badge fis-badge-gray",
    KOMPROMITOVAN:      "fis-badge fis-badge-red",
    ARHIVIRANO:         "fis-badge fis-badge-gray",
  };
  return m[status] ?? "fis-badge fis-badge-gray";
}

function statusDokazaLabel(status: string) {
  const m: Record<string, string> = {
    PRIJEM:             "Prijem",
    U_SKLADISTU:        "U skladištu",
    IZDATO_ZA_ANALIZU:  "Na analizi",
    VRACENO:            "Vraćeno",
    KOMPROMITOVAN:      "Kompromitovan",
    ARHIVIRANO:         "Arhivirano",
  };
  return m[status] ?? status;
}

function statusAnalizeBadge(status: string) {
  const m: Record<string, string> = {
    KREIRAN:    "fis-badge fis-badge-gray",
    DODELJEN:   "fis-badge fis-badge-blue",
    U_TOKU:     "fis-badge fis-badge-yellow",
    ZAVRSEN:    "fis-badge fis-badge-green",
    PREKORACEN: "fis-badge fis-badge-red",
    ODBIJEN:    "fis-badge fis-badge-red",
  };
  return m[status] ?? "fis-badge fis-badge-gray";
}

function statusAnalizeLabel(status: string) {
  const m: Record<string, string> = {
    KREIRAN:    "Kreiran",
    DODELJEN:   "Dodeljen",
    U_TOKU:     "U toku",
    ZAVRSEN:    "Završen",
    PREKORACEN: "Prekoračen",
    ODBIJEN:    "Odbijen",
  };
  return m[status] ?? status;
}

function tipAnalizeLabel(tip: string) {
  const m: Record<string, string> = {
    BALISTICKA:    "Ballistička",
    DNK:           "DNK analiza",
    DIGITALNA:     "Digitalna",
    HEMIJSKA:      "Hemijska",
    TOKSIKOLOSKA:  "Toksikološka",
    DOKUMENTOLOSKA:"Dokumentološka",
    DRUGA:         "Druga",
  };
  return m[tip] ?? tip;
}

// ─── Komponenta tabova ───────────────────────────────────────────────────────

export function PredmetDetaljiTabs({ dokazi, analize, dokumenti }: Props) {
  const [tab, setTab] = useState<Tab>("dokazi");

  return (
    <>
      {/* ── Tab navigacija ─────────────────────────────────────────────── */}
      <div className="fis-tabs">
        <button
          className={`fis-tab${tab === "dokazi" ? " active" : ""}`}
          onClick={() => setTab("dokazi")}
        >
          Dokazi ({dokazi.length})
        </button>
        <button
          className={`fis-tab${tab === "analize" ? " active" : ""}`}
          onClick={() => setTab("analize")}
        >
          Analize ({analize.length})
        </button>
        <button
          className={`fis-tab${tab === "dokumenti" ? " active" : ""}`}
          onClick={() => setTab("dokumenti")}
        >
          Dokumenti ({dokumenti.length})
        </button>
      </div>

      {/* ── Tab: Dokazi ────────────────────────────────────────────────── */}
      {tab === "dokazi" && (
        dokazi.length === 0 ? (
          <div className="fis-empty">Nema dodeljenih dokaza</div>
        ) : (
          <div className="fis-card">
            <table className="fis-table">
              <thead>
                <tr>
                  <th>ID dokaza</th>
                  <th>Naziv</th>
                  <th>Tip</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {dokazi.map((d) => (
                  <tr key={d.id}>
                    <td>{d.sifraDokaza}</td>
                    <td style={{ color: "var(--color-fis-text1)" }}>{d.naziv}</td>
                    <td><span className={tipDokazaBadge(d.tipDokaza)}>{tipDokazaLabel(d.tipDokaza)}</span></td>
                    <td><span className={statusDokazaBadge(d.status)}>{statusDokazaLabel(d.status)}</span></td>
                    <td>
                      <Link href={`/dokazi/${d.id}`} className="fis-btn fis-btn-outline fis-btn-sm">→</Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )
      )}

      {/* ── Tab: Analize ───────────────────────────────────────────────── */}
      {tab === "analize" && (
        analize.length === 0 ? (
          <div className="fis-empty">Nema zahteva za analizu</div>
        ) : (
          <div className="fis-card">
            <table className="fis-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Dokaz</th>
                  <th>Tip analize</th>
                  <th>Veštak</th>
                  <th>Rok</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {analize.map((a) => (
                  <tr key={a.id}>
                    <td>{a.id}</td>
                    <td style={{ color: "var(--color-fis-text1)" }}>
                      {a.dokaz.sifraDokaza} – {a.dokaz.naziv}
                    </td>
                    <td>{tipAnalizeLabel(a.tipAnalize)}</td>
                    <td>{a.vestakIme ?? "—"}</td>
                    <td>{a.rokFormatiran ?? "—"}</td>
                    <td><span className={statusAnalizeBadge(a.status)}>{statusAnalizeLabel(a.status)}</span></td>
                    <td>
                      <Link href={`/analize/${a.id}`} className="fis-btn fis-btn-outline fis-btn-sm">→</Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )
      )}

      {/* ── Tab: Dokumenti ─────────────────────────────────────────────── */}
      {tab === "dokumenti" && (
        dokumenti.length === 0 ? (
          <div className="fis-empty">Nema dokumentacije</div>
        ) : (
          <div className="fis-card">
            <table className="fis-table">
              <thead>
                <tr>
                  <th>Naziv</th>
                  <th>Verzija</th>
                  <th>Datum</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {dokumenti.map((d) => (
                  <tr key={d.id}>
                    <td style={{ color: "var(--color-fis-text1)" }}>{d.naziv}</td>
                    <td>v{d.verzija}</td>
                    <td>{d.datumFormatiran}</td>
                    <td>
                      <Link href={`/dokumentacija/${d.id}`} className="fis-btn fis-btn-ghost fis-btn-sm">
                        Otvori
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )
      )}
    </>
  );
}
