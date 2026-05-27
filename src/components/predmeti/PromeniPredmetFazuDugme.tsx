"use client";

import { useState, useTransition } from "react";
import { promeniPredmetFazu } from "@/app/(dashboard)/predmeti/actions";

const NAZIV_FAZE: Record<string, string> = {
  OTVOREN_SLUCAJ:      "Otvoren slučaj",
  PRIKUPLJANJE_DOKAZA: "Prikupljanje dokaza",
  ANALIZA_DOKAZA:      "Analiza dokaza",
  DONOSENJE_ZAKLJUCKA: "Donošenje zaključka",
  ZATVOREN_SLUCAJ:     "Zatvoren slučaj",
};

const REDOSLED_FAZA = [
  "OTVOREN_SLUCAJ",
  "PRIKUPLJANJE_DOKAZA",
  "ANALIZA_DOKAZA",
  "DONOSENJE_ZAKLJUCKA",
  "ZATVOREN_SLUCAJ",
];

interface Props {
  predmetId: number;
  trenutnaFaza: string;
}

export function PromeniPredmetFazuDugme({ predmetId, trenutnaFaza }: Props) {
  const [isPending, startTransition] = useTransition();
  const [greska, setGreska] = useState<string | null>(null);

  const indeks = REDOSLED_FAZA.indexOf(trenutnaFaza);
  const sledecaFaza = indeks !== -1 && indeks < REDOSLED_FAZA.length - 1
    ? REDOSLED_FAZA[indeks + 1]
    : null;

  if (!sledecaFaza) return null;

  function handlePromeni() {
    if (
      !confirm(
        `Promeniti fazu predmeta u "${NAZIV_FAZE[sledecaFaza!]}"?\n\nFaze se menjaju po unapred definisanom redosledu i nije moguće vratiti se na prethodnu fazu.`
      )
    ) return;

    startTransition(async () => {
      const result = await promeniPredmetFazu(predmetId);
      if (!result.ok) setGreska(result.greska);
    });
  }

  return (
    <div>
      <button
        className="fis-btn fis-btn-primary"
        onClick={handlePromeni}
        disabled={isPending}
      >
        {isPending ? "Ažuriranje…" : "Pređi u sledeću fazu →"}
      </button>
      {greska && (
        <p style={{ fontFamily: "var(--font-mono)", fontSize: 11, color: "var(--color-fis-red)", marginTop: 8 }}>
          {greska}
        </p>
      )}
    </div>
  );
}
