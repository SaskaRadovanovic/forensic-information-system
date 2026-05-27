import { cn } from "@/lib/utils";

interface TagBadgeProps {
  naziv: string;
  boja?: string;
  className?: string;
}

/**
 * Prikazuje tag sa dinamičnom bojom:
 *   - tekst i border = puna boja
 *   - pozadina = ista boja na 25% opacity (hex + "40")
 */
export function TagBadge({ naziv, boja = "#FACC15", className }: TagBadgeProps) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-semibold",
        className
      )}
      style={{
        color: boja,
        borderColor: boja,
        backgroundColor: `${boja}40`,
      }}
    >
      {naziv}
    </span>
  );
}
