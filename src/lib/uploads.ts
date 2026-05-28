import fs from "fs/promises";
import path from "path";

const UPLOADS_DIR = path.join(process.cwd(), "uploads");
const MAX_SIZE_BYTES = 20 * 1024 * 1024; // 20 MB

/** Osigurava da /uploads direktorijum postoji */
export async function ensureUploadsDir(): Promise<void> {
  await fs.mkdir(UPLOADS_DIR, { recursive: true });
}

/** Validira da je fajl PDF i ne prelazi 20 MB */
export function validatePdf(file: File): { ok: true } | { ok: false; error: string } {
  if (file.type !== "application/pdf") {
    return { ok: false, error: "Dozvoljen je isključivo PDF format." };
  }
  if (file.size > MAX_SIZE_BYTES) {
    return { ok: false, error: "Maksimalna veličina fajla je 20 MB." };
  }
  return { ok: true };
}


export async function savePdf(file: File): Promise<string> {
  await ensureUploadsDir();

  const timestamp = Date.now();
  const safeName = file.name
    .replace(/[^a-zA-Z0-9._-]/g, "_")
    .replace(/\.pdf$/i, "");
  const fileName = `${timestamp}_${safeName}.pdf`;
  const fullPath = path.join(UPLOADS_DIR, fileName);

  const buffer = Buffer.from(await file.arrayBuffer());
  await fs.writeFile(fullPath, buffer);

  return `/uploads/${fileName}`;
}


export async function copyPdf(relativePath: string, dokumentId: number): Promise<string> {
  await ensureUploadsDir();

  const srcPath = path.join(process.cwd(), relativePath);


  try {
    await fs.access(srcPath);
  } catch {
    return relativePath;
  }

  const timestamp = Date.now();
  const destFileName = `${dokumentId}_archive_${timestamp}.pdf`;
  const destPath = path.join(UPLOADS_DIR, destFileName);

  await fs.copyFile(srcPath, destPath);

  return `/uploads/${destFileName}`;
}

/**
 * Briše PDF fajl sa diska (neobavezno — za cleanup).
 */
export async function deletePdf(relativePath: string): Promise<void> {
  try {
    const fullPath = path.join(process.cwd(), relativePath);
    await fs.unlink(fullPath);
  } catch {
    // Ignorišemo grešku ako fajl ne postoji
  }
}

/** Vraća apsolutni path za dati relativni /uploads/... path */
export function absolutePath(relativePath: string): string {
  return path.join(process.cwd(), relativePath);
}
