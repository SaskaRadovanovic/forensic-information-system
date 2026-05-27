"use client";

import { useState, useTransition } from "react";
import { Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { obrisiTag } from "@/app/(dashboard)/tagovi/actions";

interface ObrisiTagDugmeProps {
  tagId: number;
  naziv: string;
}

export function ObrisiTagDugme({ tagId, naziv }: ObrisiTagDugmeProps) {
  const [isPending, startTransition] = useTransition();
  const [greska, setGreska] = useState<string | null>(null);

  function handleObrisi() {
    if (!confirm(`Obrisati tag "${naziv}"? Ovo će ga ukloniti sa svih dokumenata.`)) {
      return;
    }

    startTransition(async () => {
      const result = await obrisiTag(tagId);
      if (!result.ok) {
        setGreska(result.greska);
      }
    });
  }

  return (
    <div>
      <Button
        variant="ghost"
        size="sm"
        className="h-7 w-7 p-0 text-fis-text2 hover:text-fis-red hover:bg-fis-red/10"
        onClick={handleObrisi}
        disabled={isPending}
        title={`Obriši tag "${naziv}"`}
      >
        <Trash2 className="h-4 w-4" />
      </Button>
      {greska && (
        <p className="text-xs text-fis-red mt-1">{greska}</p>
      )}
    </div>
  );
}
