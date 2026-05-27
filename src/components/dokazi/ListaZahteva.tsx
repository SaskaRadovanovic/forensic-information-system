"use client";

import { useState, useTransition } from "react";
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
      <div style={{
        display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center",
        border: "1px dashed #2a2a2a", background: "#111111", padding: "60px 20px", textAlign: "center",
      }}>
        <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 13, color: "#999999", fontWeight: 500 }}>
          Nema zahteva na čekanju
        </p>
        <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 11, color: "#555555", marginTop: 4 }}>
          Svi zahtevi su obrađeni.
        </p>
      </div>
    );
  }

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
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
    <div style={{ border: "1px solid #2a2a2a", background: "#111111", padding: "16px 20px" }}>
      {/* Zaglavlje zahteva */}
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 10 }}>
        <div>
          <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 13, color: "#f0ede8", fontWeight: 500 }}>
            {zahtev.dokaz.sifraDokaza} — {zahtev.dokaz.naziv}
          </p>
          <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 11, color: "#999999", marginTop: 3 }}>
            Podnosilac: {zahtev.podnosilac.ime} {zahtev.podnosilac.prezime}
          </p>
          <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 10, color: "#555555", marginTop: 3 }}>
            {new Date(zahtev.datumKreiranja).toLocaleDateString("sr-RS", {
              day: "2-digit", month: "2-digit", year: "numeric",
              hour: "2-digit", minute: "2-digit",
            })}
          </p>
        </div>
        <span className={`fis-badge ${zahtev.tip === "PREDAJA" ? "text-fis-blue border-fis-blue/40" : "text-fis-green border-fis-green/40"}`}>
          {zahtev.tip === "PREDAJA" ? "Predaja" : "Povraćaj"}
        </span>
      </div>

      {/* Razlog zahteva */}
      {zahtev.razlog && (
        <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 12, color: "#999999", fontStyle: "italic", marginBottom: 10 }}>
          &quot;{zahtev.razlog}&quot;
        </p>
      )}

      {/* Polje za napomenu tehničara */}
      <textarea
        placeholder="Napomena (opciono)..."
        value={napomena}
        onChange={(e) => setNapomena(e.target.value)}
        rows={2}
        disabled={isPending}
        style={{
          width: "100%",
          background: isPending ? "#141414" : "#181818",
          border: "1px solid #2a2a2a",
          color: isPending ? "#555555" : "#f0ede8",
          fontFamily: "var(--font-mono), monospace",
          fontSize: 12,
          padding: "9px 12px",
          outline: "none",
          resize: "vertical",
          minHeight: 50,
          marginBottom: 12,
          cursor: isPending ? "not-allowed" : "text",
        }}
      />

      {/* Dugmad za odobrenje/odbijanje */}
      <div style={{ display: "flex", justifyContent: "flex-end", gap: 8 }}>
        <button
          type="button"
          onClick={() => handleOdluka("ODBIJEN")}
          disabled={isPending}
          style={{
            fontFamily: "var(--font-mono), monospace", fontSize: 11, textTransform: "uppercase", letterSpacing: 1,
            padding: "7px 14px", border: "1px solid rgba(239,68,68,0.3)", background: "transparent", color: "#ef4444",
            cursor: isPending ? "not-allowed" : "pointer", display: "inline-flex", alignItems: "center", gap: 5,
            opacity: isPending ? 0.5 : 1,
          }}
        >
          <X className="h-3.5 w-3.5" />
          Odbij
        </button>
        <button
          type="button"
          onClick={() => handleOdluka("ODOBREN")}
          disabled={isPending}
          style={{
            fontFamily: "var(--font-mono), monospace", fontSize: 11, textTransform: "uppercase", letterSpacing: 1,
            padding: "7px 14px", border: "none", background: isPending ? "#c9a015" : "#f5c518", color: "#000",
            cursor: isPending ? "not-allowed" : "pointer", display: "inline-flex", alignItems: "center", gap: 5,
            fontWeight: 600, opacity: isPending ? 0.5 : 1,
          }}
        >
          <Check className="h-3.5 w-3.5" />
          Odobri
        </button>
      </div>
    </div>
  );
}
