import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect } from "next/navigation";
import { ArrowLeft } from "lucide-react";
import Link from "next/link";
import { TagForm } from "@/components/tagovi/TagForm";

export const metadata = { title: "Novi tag — FIS" };

export default async function NoviTagPage() {
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  // Samo administrator može kreirati tagove (SCRUM-48)
  if (session.user.uloga !== "ADMINISTRATOR") {
    redirect("/dokumentacija");
  }

  return (
    <div className="max-w-md mx-auto">
      <div className="flex items-center gap-2 mb-6 text-sm">
        <Link
          href="/tagovi"
          className="flex items-center gap-1 text-fis-text2 hover:text-fis-text1 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" />
          Tagovi
        </Link>
        <span className="text-fis-text3">/</span>
        <span className="text-fis-text1 font-medium">Novi tag</span>
      </div>

      <TagForm />
    </div>
  );
}
