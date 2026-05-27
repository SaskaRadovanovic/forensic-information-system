"use client";

import { useTransition } from "react";
import { useRouter } from "next/navigation";
import { Archive } from "lucide-react";
import { arhivirajDokaz } from "@/app/(dashboard)/dokazi/[id]/actions";

// ─── Dugme za arhiviranje dokaza (samo za administratora) ───────────────────

export function ArhivirajDokazDugme({ dokazId }: { dokazId: number }) {
  const [isPending, startTransition] = useTransition();
  const router = useRouter();

  // Handler za klik na dugme arhiviranja
  function handleArhiviraj() {
    // Potvrda pre arhiviranja
    if (
      !confirm(
        "Da li ste sigurni da želite da arhivirate ovaj dokaz?\n\nDokaz će dobiti status ARHIVIRANO i neće se prikazivati u listi."
      )
    ) {
      return;
    }

    // Pokretanje server action-a
    startTransition(async () => {
      const result = await arhivirajDokaz(dokazId);
      if (result.ok) {
        router.push("/dokazi");
      } else {
        alert(result.greska);
      }
    });
  }

  return (
    <button
      type="button"
      onClick={handleArhiviraj}
      disabled={isPending}
      style={{
        fontFamily: "var(--font-mono), monospace",
        fontSize: 11,
        textTransform: "uppercase",
        letterSpacing: 1,
        padding: "7px 14px",
        border: "1px solid rgba(239,68,68,0.3)",
        background: "transparent",
        color: "#ef4444",
        cursor: isPending ? "not-allowed" : "pointer",
        display: "inline-flex",
        alignItems: "center",
        gap: 6,
        opacity: isPending ? 0.5 : 1,
      }}
    >
      <Archive className="h-3.5 w-3.5" />
      {isPending ? "Arhiviranje..." : "Arhiviraj"}
    </button>
  );
}
