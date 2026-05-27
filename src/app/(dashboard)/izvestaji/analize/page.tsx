"use client";

// ─── Izveštaji analiza ────────────────────────────────────────────────────────
import { useEffect, useState, useCallback } from "react";
import { BarChart2, Download, RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/button";

// ─── Tipovi podataka iz API-ja ────────────────────────────────────────────────

interface AnalizaPoPredmetu {
  predmetId: number;
  predmetNaziv: string;
  ukupno: number;
  zavrsene: number;
  uToku: number;
  prekoracene: number;
}

interface AnalizaSaKasnjenjom {
  id: number;
  tipAnalize: string;
  rok: string | null;
  predmetNaziv: string;
  vestakIme: string | null;
}

interface StatistikaPoTipu {
  tipAnalize: string;
  prosecnoTrajanjeDana: number | null;
  ukupnoZavrsenih: number;
}

interface OpreterecenjeVestaka {
  vestakId: number;
  vestakIme: string;
  aktivnih: number;
  zavrsenih: number;
}

interface IzvestajiPodaci {
  analizePoPredmetu: AnalizaPoPredmetu[];
  analizeSaKasnjenjem: AnalizaSaKasnjenjom[];
  statistikaPoTipu: StatistikaPoTipu[];
  opterecenjeVestaka: OpreterecenjeVestaka[];
}

// ─── CSV helper ───────────────────────────────────────────────────────────────

function preuzmiCSV(podaci: object[], naziv: string) {
  if (podaci.length === 0) return;
  const zaglavlje = Object.keys(podaci[0]).join(";");
  const redovi = podaci.map((r) =>
    Object.values(r)
      .map((v) => (v === null || v === undefined ? "" : String(v)))
      .join(";")
  );
  const sadrzaj = [zaglavlje, ...redovi].join("\n");
  const blob = new Blob(["﻿" + sadrzaj], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `${naziv}-${new Date().toISOString().slice(0, 10)}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}

// ─── Komponenta ───────────────────────────────────────────────────────────────

export default function IzvestajiAnalizePage() {
  const [podaci, setPodaci] = useState<IzvestajiPodaci | null>(null);
  const [ucitava, setUcitava] = useState(true);
  const [greska, setGreska] = useState<string | null>(null);

  const ucitajPodatke = useCallback(async () => {
    setUcitava(true);
    setGreska(null);
    try {
      const res = await fetch("/api/izvestaji/analize");
      const json = await res.json();
      if (!json.ok) throw new Error(json.greska ?? "Greška pri učitavanju");
      setPodaci(json.podaci);
    } catch (e) {
      setGreska(e instanceof Error ? e.message : "Neočekivana greška");
    } finally {
      setUcitava(false);
    }
  }, []);

  useEffect(() => {
    ucitajPodatke();
  }, [ucitajPodatke]);

  return (
    <div className="space-y-8">

      {/* ── Zaglavlje ─────────────────────────────────────────────────────── */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <BarChart2 className="h-5 w-5 text-fis-text2" />
          <div>
            <h1 className="text-xl font-semibold text-fis-text1">Izveštaji analiza</h1>
            <p className="text-sm text-fis-text2">Pregled statusa i performansi forenzičkih analiza</p>
          </div>
        </div>
        <Button variant="outline" size="sm" onClick={ucitajPodatke} disabled={ucitava} className="gap-1.5">
          <RefreshCw className={`h-3.5 w-3.5 ${ucitava ? "animate-spin" : ""}`} />
          Osveži
        </Button>
      </div>

      {/* ── Greška ────────────────────────────────────────────────────────── */}
      {greska && (
        <div className="rounded-xl border border-fis-red/30 bg-fis-red/5 p-4 text-sm text-fis-red">
          {greska}
        </div>
      )}

      {/* ── Učitavanje ────────────────────────────────────────────────────── */}
      {ucitava && !podaci && (
        <div className="rounded-xl border border-fis-surface3 bg-fis-surface p-12 text-center text-sm text-fis-text3">
          Učitavanje izveštaja…
        </div>
      )}

      {podaci && (
        <>
          {/* ── 1. Analize po predmetu ──────────────────────────────────── */}
          <IzvestajSekcija
            naslov="Analize po predmetu"
            opis="Ukupan broj zahteva, završenih, u toku i prekoračenih po predmetu"
            onPreuzmi={() => preuzmiCSV(podaci.analizePoPredmetu, "analize-po-predmetu")}
          >
            {podaci.analizePoPredmetu.length === 0 ? (
              <PraznaTabelaPoruka />
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-fis-surface3 bg-fis-surface2">
                    {["Predmet", "Ukupno", "Završene", "U toku", "Prekoračene"].map((z) => (
                      <th key={z} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-fis-text3 first:rounded-tl-lg last:rounded-tr-lg">
                        {z}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {podaci.analizePoPredmetu.map((r) => (
                    <tr key={r.predmetId} className="border-b border-fis-surface3 last:border-0 hover:bg-fis-surface2 transition-colors">
                      <td className="px-4 py-3 font-medium text-fis-text1">{r.predmetNaziv}</td>
                      <td className="px-4 py-3 text-fis-text2">{r.ukupno}</td>
                      <td className="px-4 py-3 text-fis-green">{r.zavrsene}</td>
                      <td className="px-4 py-3 text-fis-yellow">{r.uToku}</td>
                      <td className="px-4 py-3 text-fis-red">{r.prekoracene}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </IzvestajSekcija>

          {/* ── 2. Analize sa kašnjenjem ────────────────────────────────── */}
          <IzvestajSekcija
            naslov="Analize sa kašnjenjem"
            opis="Zahtevi koji su prekoračili rok ili imaju status PREKORACEN"
            onPreuzmi={() => preuzmiCSV(podaci.analizeSaKasnjenjem, "analize-kasnjenje")}
          >
            {podaci.analizeSaKasnjenjem.length === 0 ? (
              <p className="px-4 py-8 text-center text-sm text-fis-green">
                Nema analiza sa kašnjenjem
              </p>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-fis-surface3 bg-fis-surface2">
                    {["#", "Tip analize", "Rok", "Predmet", "Veštak"].map((z) => (
                      <th key={z} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-fis-text3">
                        {z}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {podaci.analizeSaKasnjenjem.map((r) => (
                    <tr key={r.id} className="border-b border-fis-surface3 last:border-0 hover:bg-fis-surface2 transition-colors">
                      <td className="px-4 py-3 text-fis-text3">{r.id}</td>
                      <td className="px-4 py-3 text-fis-text1">{r.tipAnalize}</td>
                      <td className="px-4 py-3 text-fis-red">
                        {r.rok ? new Date(r.rok).toLocaleDateString("sr-RS") : "—"}
                      </td>
                      <td className="px-4 py-3 text-fis-text2">{r.predmetNaziv}</td>
                      <td className="px-4 py-3 text-fis-text2">{r.vestakIme ?? "Nije dodeljen"}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </IzvestajSekcija>

          {/* ── 3. Statistika po tipu analize ───────────────────────────── */}
          <IzvestajSekcija
            naslov="Statistika po tipu analize"
            opis="Prosečno trajanje i broj završenih analiza po tipu"
            onPreuzmi={() => preuzmiCSV(podaci.statistikaPoTipu, "statistika-po-tipu")}
          >
            {podaci.statistikaPoTipu.length === 0 ? (
              <PraznaTabelaPoruka />
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-fis-surface3 bg-fis-surface2">
                    {["Tip analize", "Prosečno trajanje (dana)", "Ukupno završenih"].map((z) => (
                      <th key={z} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-fis-text3">
                        {z}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {podaci.statistikaPoTipu.map((r) => (
                    <tr key={r.tipAnalize} className="border-b border-fis-surface3 last:border-0 hover:bg-fis-surface2 transition-colors">
                      <td className="px-4 py-3 font-medium text-fis-text1">{r.tipAnalize}</td>
                      <td className="px-4 py-3 text-fis-text2">
                        {r.prosecnoTrajanjeDana !== null ? `${r.prosecnoTrajanjeDana} dana` : "—"}
                      </td>
                      <td className="px-4 py-3 text-fis-text2">{r.ukupnoZavrsenih}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </IzvestajSekcija>

          {/* ── 4. Opterećenje veštaka ──────────────────────────────────── */}
          <IzvestajSekcija
            naslov="Opterećenje veštaka"
            opis="Broj aktivnih i završenih analiza po veštaku"
            onPreuzmi={() => preuzmiCSV(podaci.opterecenjeVestaka, "opterecenje-vestaka")}
          >
            {podaci.opterecenjeVestaka.length === 0 ? (
              <PraznaTabelaPoruka />
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-fis-surface3 bg-fis-surface2">
                    {["Veštak", "Aktivnih analiza", "Završenih analiza", "Ukupno"].map((z) => (
                      <th key={z} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-fis-text3">
                        {z}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {podaci.opterecenjeVestaka.map((r) => (
                    <tr key={r.vestakId} className="border-b border-fis-surface3 last:border-0 hover:bg-fis-surface2 transition-colors">
                      <td className="px-4 py-3 font-medium text-fis-text1">{r.vestakIme}</td>
                      <td className="px-4 py-3 text-fis-yellow">{r.aktivnih}</td>
                      <td className="px-4 py-3 text-fis-green">{r.zavrsenih}</td>
                      <td className="px-4 py-3 text-fis-text2">{r.aktivnih + r.zavrsenih}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </IzvestajSekcija>
        </>
      )}
    </div>
  );
}

// ─── Sekcija izveštaja (wrapper) ──────────────────────────────────────────────

function IzvestajSekcija({
  naslov,
  opis,
  onPreuzmi,
  children,
}: {
  naslov: string;
  opis: string;
  onPreuzmi: () => void;
  children: React.ReactNode;
}) {
  return (
    <div className="rounded-xl border border-fis-surface3 bg-fis-surface overflow-hidden">
      <div className="flex items-start justify-between gap-4 px-5 py-4 border-b border-fis-surface3">
        <div>
          <h2 className="font-semibold text-fis-text1">{naslov}</h2>
          <p className="text-xs text-fis-text3 mt-0.5">{opis}</p>
        </div>
        <Button variant="outline" size="sm" onClick={onPreuzmi} className="gap-1.5 flex-shrink-0">
          <Download className="h-3.5 w-3.5" />
          CSV
        </Button>
      </div>
      <div className="overflow-x-auto">{children}</div>
    </div>
  );
}

function PraznaTabelaPoruka() {
  return (
    <p className="px-4 py-8 text-center text-sm text-fis-text3 italic">
      Nema podataka za prikaz
    </p>
  );
}
