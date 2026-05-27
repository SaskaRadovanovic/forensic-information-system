"use server";

import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { revalidatePath } from "next/cache";

type Result = { ok: true } | { ok: false; greska: string };

// ─── SCRUM-48: Kreiranje taga (samo ADMINISTRATOR) ────────────────────────────

export async function kreirajTag(formData: FormData): Promise<Result> {
  const session = await getServerSession(authOptions);
  if (!session || session.user.uloga !== "ADMINISTRATOR") {
    return { ok: false, greska: "Nemate dozvolu za ovu akciju." };
  }

  const naziv = (formData.get("naziv") as string | null)?.trim();
  if (!naziv || naziv.length < 2) {
    return { ok: false, greska: "Naziv taga mora imati najmanje 2 karaktera." };
  }

  const boja = (formData.get("boja") as string | null)?.trim() || "#FACC15";

  try {
    await prisma.tag.create({ data: { naziv, boja } });
    revalidatePath("/tagovi");
    return { ok: true };
  } catch (e: unknown) {
    const err = e as { code?: string };
    if (err.code === "P2002") {
      return { ok: false, greska: `Tag "${naziv}" već postoji.` };
    }
    return { ok: false, greska: "Greška na serveru. Pokušajte ponovo." };
  }
}

// ─── SCRUM-47: Brisanje taga (samo ADMINISTRATOR) ────────────────────────────

export async function obrisiTag(tagId: number): Promise<Result> {
  const session = await getServerSession(authOptions);
  if (!session || session.user.uloga !== "ADMINISTRATOR") {
    return { ok: false, greska: "Nemate dozvolu za ovu akciju." };
  }

  try {
    await prisma.tag.delete({ where: { id: tagId } });
    revalidatePath("/tagovi");
    return { ok: true };
  } catch {
    return { ok: false, greska: "Greška pri brisanju taga." };
  }
}

// ─── SCRUM-34: Toggle taga na dokumentu ──────────────────────────────────────

export async function toggleTagNaDokumentu(
  dokumentId: number,
  tagId: number
): Promise<Result> {
  const session = await getServerSession(authOptions);
  if (!session) {
    return { ok: false, greska: "Niste prijavljeni." };
  }

  try {
    const postojeci = await prisma.dokumentTag.findUnique({
      where: { dokumentId_tagId: { dokumentId, tagId } },
    });

    if (postojeci) {
      await prisma.dokumentTag.delete({
        where: { dokumentId_tagId: { dokumentId, tagId } },
      });
    } else {
      await prisma.dokumentTag.create({
        data: { dokumentId, tagId },
      });
    }

    revalidatePath(`/dokumentacija/${dokumentId}`);
    revalidatePath(`/dokumentacija/${dokumentId}/tagovi`);
    return { ok: true };
  } catch {
    return { ok: false, greska: "Greška pri izmeni taga." };
  }
}
