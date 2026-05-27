"use client";

// ─── Uvoz zavisnosti ─────────────────────────────────────────────────────────
import { useState, useTransition } from "react";
import { ArrowRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import { promeniPredmetFazu } from "@/app/(dashboard)/predmeti/actions";

// ─── Mapa srpskih naziva faza ────────────────────────────────────────────────
const NAZIV_FAZE: Record<string, string> = {
  ISTRAGA: "Istraga",
  PRIKUPLJANJE_DOKAZA: "Prikupljanje dokaza",
  SUDJENJE: "Suđenje",
};

// ─── Redosled faza za određivanje sledeće ────────────────────────────────────
const REDOSLED_FAZA = ["ISTRAGA", "PRIKUPLJANJE_DOKAZA", "SUDJENJE"];

interface PromeniPredmetFazuDugmeProps {
  predmetId: number;
  trenutnaFaza: string;
}

// ─── Dugme za prelaz na sledeću fazu predmeta ────────────────────────────────

export function PromeniPredmetFazuDugme({
  predmetId,
  trenutnaFaza,
}: PromeniPredmetFazuDugmeProps) {
  const [isPending, startTransition] = useTransition();
  const [greska, setGreska] = useState<string | null>(null);

  // Određujemo sledeću fazu
  const indeks = REDOSLED_FAZA.indexOf(trenutnaFaza);
  const sledecaFaza = indeks !== -1 && indeks < REDOSLED_FAZA.length - 1
    ? REDOSLED_FAZA[indeks + 1]
    : null;

  // Nema dugmeta ako smo u poslednjoj fazi
  if (!sledecaFaza) return null;

  function handlePromeni() {
    if (
      !confirm(
        `Promeniti fazu predmeta u "${NAZIV_FAZE[sledecaFaza!]}"?\n\nFaze se menjaju po unapred definisanom redosledu i nije moguće vratiti se na prethodnu fazu.`
      )
    ) {
      return;
    }

    startTransition(async () => {
      const result = await promeniPredmetFazu(predmetId);
      if (!result.ok) {
        setGreska(result.greska);
      }
    });
  }

  return (
    <div>
      <Button
        variant="outline"
        size="sm"
        className="border-fis-yellow/30 text-fis-yellow hover:bg-fis-yellow/10 hover:border-fis-yellow"
        onClick={handlePromeni}
        disabled={isPending}
      >
        <ArrowRight className="h-4 w-4 mr-2" />
        {isPending
          ? "Ažuriranje..."
          : `Pređi u: ${NAZIV_FAZE[sledecaFaza]}`}
      </Button>
      {/* Prikaz greške ako promena faze nije uspela */}
      {greska && (
        <p className="text-xs text-fis-red mt-2 max-w-xs">{greska}</p>
      )}
    </div>
  );
}
