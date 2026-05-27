"use client";

import { useState, useTransition } from "react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Textarea } from "@/components/ui/textarea";
import { Check, X } from "lucide-react";
import { obradiZahtev } from "@/app/(dashboard)/dokazi/zahtevi/actions";
import { useRouter } from "next/navigation";

// ─── Tipovi ─────────────────────────────────────────────────────────────────

interface Zahtev {
  id: number;
  tip: string;
  razlog: string | null;
  status: string;
  datumKreiranja: Date;
  dokaz: {
    sifraDokaza: string;
    naziv: string;
  };
  podnosilac: {
    ime: string;
    prezime: string;
  };
}

interface ListaZahtevaProps {
  zahtevi: Zahtev[];
}

// ─── Komponenta ─────────────────────────────────────────────────────────────

export function ListaZahteva({ zahtevi }: ListaZahtevaProps) {
  // Ako nema zahteva na čekanju
  if (zahtevi.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-fis-surface3 bg-fis-surface py-16 text-center">
        <p className="font-medium text-fis-text2">Nema zahteva na čekanju</p>
        <p className="text-sm text-fis-text3 mt-1">
          Svi zahtevi su obrađeni.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {zahtevi.map((zahtev) => (
        <ZahtevKartica key={zahtev.id} zahtev={zahtev} />
      ))}
    </div>
  );
}

// ─── Kartica pojedinačnog zahteva ───────────────────────────────────────────

function ZahtevKartica({ zahtev }: { zahtev: Zahtev }) {
  const [isPending, startTransition] = useTransition();
  const [napomena, setNapomena] = useState("");
  const router = useRouter();

  // Handler za odobrenje/odbijanje zahteva
  function handleOdluka(odluka: "ODOBREN" | "ODBIJEN") {
    startTransition(async () => {
      const result = await obradiZahtev(zahtev.id, odluka, napomena);
      if (result.ok) {
        router.refresh();
      } else {
        alert(result.greska);
      }
    });
  }

  return (
    <div className="rounded-xl border border-fis-surface3 bg-fis-surface p-4 space-y-3">
      {/* Zaglavlje zahteva */}
      <div className="flex items-start justify-between">
        <div>
          <p className="text-sm font-medium text-fis-text1">
            {zahtev.dokaz.sifraDokaza} — {zahtev.dokaz.naziv}
          </p>
          <p className="text-xs text-fis-text2 mt-0.5">
            Podnosilac: {zahtev.podnosilac.ime} {zahtev.podnosilac.prezime}
          </p>
          <p className="text-xs text-fis-text3 mt-0.5">
            {new Date(zahtev.datumKreiranja).toLocaleDateString("sr-RS", {
              day: "2-digit", month: "2-digit", year: "numeric",
              hour: "2-digit", minute: "2-digit",
            })}
          </p>
        </div>
        <Badge className={zahtev.tip === "PREDAJA"
          ? "bg-fis-blue/10 text-fis-blue border-0 text-xs"
          : "bg-fis-green/10 text-fis-green border-0 text-xs"
        }>
          {zahtev.tip === "PREDAJA" ? "Predaja" : "Povraćaj"}
        </Badge>
      </div>

      {/* Razlog zahteva */}
      {zahtev.razlog && (
        <p className="text-sm text-fis-text2 italic">&quot;{zahtev.razlog}&quot;</p>
      )}

      {/* Polje za napomenu tehničara */}
      <Textarea
        placeholder="Napomena (opciono)..."
        value={napomena}
        onChange={(e) => setNapomena(e.target.value)}
        rows={2}
        disabled={isPending}
        className="text-sm"
      />

      {/* Dugmad za odobrenje/odbijanje */}
      <div className="flex gap-2 justify-end">
        <Button
          variant="outline"
          size="sm"
          onClick={() => handleOdluka("ODBIJEN")}
          disabled={isPending}
          className="text-fis-red border-fis-red/30 hover:bg-fis-red/10"
        >
          <X className="h-4 w-4 mr-1" />
          Odbij
        </Button>
        <Button
          size="sm"
          onClick={() => handleOdluka("ODOBREN")}
          disabled={isPending}
          className="bg-fis-green text-black hover:bg-fis-green/80"
        >
          <Check className="h-4 w-4 mr-1" />
          Odobri
        </Button>
      </div>
    </div>
  );
}
