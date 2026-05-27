"use client";

// ─── Uvoz zavisnosti ─────────────────────────────────────────────────────────
import { useState, useTransition } from "react";
import { Lock } from "lucide-react";
import { Button } from "@/components/ui/button";
import { zatvoriPredmet } from "@/app/(dashboard)/predmeti/actions";

interface ZatvoriPredmetDugmeProps {
  predmetId: number;
  naziv: string;
}

// ─── Dugme za zatvaranje predmeta sa potvrdom ────────────────────────────────

export function ZatvoriPredmetDugme({ predmetId, naziv }: ZatvoriPredmetDugmeProps) {
  const [isPending, startTransition] = useTransition();
  const [greska, setGreska] = useState<string | null>(null);

  function handleZatvori() {
    // Tražimo potvrdu — zatvaranje predmeta nije uslovljeno završetkom istrage
    if (
      !confirm(
        `Zatvoriti predmet "${naziv}"?\n\nPredmet će biti označen kao zatvoren. Ova akcija nije uslovljena završetkom istrage.`
      )
    ) {
      return;
    }

    startTransition(async () => {
      const result = await zatvoriPredmet(predmetId);
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
        className="border-fis-surface3 text-fis-text2 hover:bg-fis-surface2"
        onClick={handleZatvori}
        disabled={isPending}
      >
        <Lock className="h-4 w-4 mr-2" />
        {isPending ? "Zatvaranje..." : "Zatvori predmet"}
      </Button>
      {/* Prikaz greške ako zatvaranje nije uspelo */}
      {greska && (
        <p className="text-xs text-fis-red mt-2 max-w-xs">{greska}</p>
      )}
    </div>
  );
}
