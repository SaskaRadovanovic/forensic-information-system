"use client";

// ─── Uvoz zavisnosti ─────────────────────────────────────────────────────────
import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { obrisiPredmet } from "@/app/(dashboard)/predmeti/actions";

interface ObrisiPredmetDugmeProps {
  predmetId: number;
  naziv: string;
}

// ─── Dugme za brisanje predmeta sa potvrdom ──────────────────────────────────

export function ObrisiPredmetDugme({ predmetId, naziv }: ObrisiPredmetDugmeProps) {
  const router = useRouter();
  const [isPending, startTransition] = useTransition();
  const [greska, setGreska] = useState<string | null>(null);

  function handleObrisi() {
    // Tražimo potvrdu od korisnika pre brisanja
    if (
      !confirm(
        `Obrisati predmet "${naziv}"?\n\nOva akcija je nepovratna. Predmet može biti obrisan samo ako nema vezanih dokaza, dokumenata ili zahteva za analizu.`
      )
    ) {
      return;
    }

    startTransition(async () => {
      const result = await obrisiPredmet(predmetId);
      if (!result.ok) {
        setGreska(result.greska);
        return;
      }
      // Uspešno brisanje — vraćamo se na listu predmeta
      router.push("/predmeti");
    });
  }

  return (
    <div>
      <Button
        variant="outline"
        size="sm"
        className="border-fis-red/30 text-fis-red hover:bg-fis-red/10 hover:border-fis-red"
        onClick={handleObrisi}
        disabled={isPending}
      >
        <Trash2 className="h-4 w-4 mr-2" />
        {isPending ? "Brisanje..." : "Obriši"}
      </Button>
      {/* Prikaz greške ako brisanje nije uspelo */}
      {greska && (
        <p className="text-xs text-fis-red mt-2 max-w-xs">{greska}</p>
      )}
    </div>
  );
}
