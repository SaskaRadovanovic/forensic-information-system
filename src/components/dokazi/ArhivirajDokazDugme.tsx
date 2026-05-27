"use client";

import { useTransition } from "react";
import { useRouter } from "next/navigation";
import { Archive } from "lucide-react";
import { Button } from "@/components/ui/button";
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
    <Button
      variant="outline"
      size="sm"
      onClick={handleArhiviraj}
      disabled={isPending}
      className="text-fis-red border-fis-red/30 hover:bg-fis-red/10 hover:text-fis-red"
    >
      <Archive className="h-4 w-4 mr-2" />
      {isPending ? "Arhiviranje..." : "Arhiviraj"}
    </Button>
  );
}
