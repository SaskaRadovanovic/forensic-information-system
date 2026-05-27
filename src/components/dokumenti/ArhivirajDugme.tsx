"use client";

import { useTransition } from "react";
import { useRouter } from "next/navigation";
import { Archive } from "lucide-react";
import { Button } from "@/components/ui/button";
import { arhivirajDokument } from "@/app/(dashboard)/dokumentacija/[id]/actions";

export function ArhivirajDugme({ dokumentId }: { dokumentId: number }) {
  const [isPending, startTransition] = useTransition();
  const router = useRouter();

  function handleArhiviraj() {
    if (
      !confirm(
        "Da li ste sigurni da želite da arhivirate ovaj dokument?\n\nDokument će dobiti status ARHIVIRAN i neće moći da se menja."
      )
    ) {
      return;
    }

    startTransition(async () => {
      const result = await arhivirajDokument(dokumentId);
      if (result.ok) {
        router.refresh();
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
      className="text-amber-700 border-amber-300 hover:bg-amber-50 hover:text-amber-800"
    >
      <Archive className="h-4 w-4 mr-2" />
      {isPending ? "Arhiviranje..." : "Arhiviraj"}
    </Button>
  );
}
