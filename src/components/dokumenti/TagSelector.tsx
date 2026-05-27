"use client";

import { useTransition } from "react";
import { cn } from "@/lib/utils";
import { Check, Plus } from "lucide-react";
import { toggleTagNaDokumentu } from "@/app/(dashboard)/tagovi/actions";

interface Tag {
  id: number;
  naziv: string;
  boja: string;
}

interface TagSelectorProps {
  dokumentId: number;
  sviTagovi: Tag[];
  odabraniTagIds: number[];
}

export function TagSelector({
  dokumentId,
  sviTagovi,
  odabraniTagIds,
}: TagSelectorProps) {
  const [isPending, startTransition] = useTransition();
  const odabraniSet = new Set(odabraniTagIds);

  function handleToggle(tagId: number) {
    startTransition(async () => {
      await toggleTagNaDokumentu(dokumentId, tagId);
    });
  }

  if (sviTagovi.length === 0) {
    return (
      <p className="text-sm text-fis-text2 italic">
        Nema dostupnih tagova. Administrator treba da kreira tagove.
      </p>
    );
  }

  return (
    <div className="space-y-3">
      <p className="text-xs text-fis-text2">
        Klikni na tag da ga dodaš ili ukloniš sa dokumenta.
      </p>
      <div className="flex flex-wrap gap-2">
        {sviTagovi.map((tag) => {
          const isSelected = odabraniSet.has(tag.id);
          return (
            <button
              key={tag.id}
              onClick={() => handleToggle(tag.id)}
              disabled={isPending}
              className={cn(
                "inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold border-2 transition-all",
                "disabled:opacity-50 disabled:cursor-not-allowed",
                isSelected ? "opacity-100" : "opacity-50 hover:opacity-80"
              )}
              style={
                isSelected
                  ? {
                      color: tag.boja,
                      borderColor: tag.boja,
                      backgroundColor: `${tag.boja}40`,
                    }
                  : {
                      color: tag.boja,
                      borderColor: `${tag.boja}60`,
                      backgroundColor: `${tag.boja}15`,
                    }
              }
            >
              {isSelected ? (
                <Check className="h-3.5 w-3.5" />
              ) : (
                <Plus className="h-3.5 w-3.5" />
              )}
              {tag.naziv}
            </button>
          );
        })}
      </div>
      {isPending && (
        <p className="text-xs text-fis-text2">Čuvanje...</p>
      )}
    </div>
  );
}
