"use client";

import { useState, useTransition } from "react";
import { zatvoriPredmet } from "@/app/(dashboard)/predmeti/actions";

interface Props {
  predmetId: number;
  naziv: string;
}

export function ZatvoriPredmetDugme({ predmetId, naziv }: Props) {
  const [isPending, startTransition] = useTransition();
  const [greska, setGreska] = useState<string | null>(null);

  function handleZatvori() {
    if (
      !confirm(
        `Zatvoriti predmet "${naziv}"?\n\nPredmet će biti označen kao zatvoren. Ova akcija nije uslovljena završetkom istrage.`
      )
    ) return;

    startTransition(async () => {
      const result = await zatvoriPredmet(predmetId);
      if (!result.ok) setGreska(result.greska);
    });
  }

  return (
    <div>
      <button
        className="fis-btn fis-btn-ghost"
        onClick={handleZatvori}
        disabled={isPending}
      >
        {isPending ? "Zatvaranje…" : "Zatvori predmet"}
      </button>
      {greska && (
        <p style={{ fontFamily: "var(--font-mono)", fontSize: 11, color: "var(--color-fis-red)", marginTop: 8 }}>
          {greska}
        </p>
      )}
    </div>
  );
}
