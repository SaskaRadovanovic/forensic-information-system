import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect } from "next/navigation";
import { Suspense } from "react";
import { LoginForm } from "@/components/auth/LoginForm";

export const metadata = {
  title: "Prijava — FIS",
};

export default async function LoginPage() {
  const session = await getServerSession(authOptions);
  if (session) redirect("/dokumentacija");

  return (
    <div
      className="min-h-screen flex items-center justify-center px-4"
      style={{
        background: "#0a0a0a",
        backgroundImage:
          "repeating-linear-gradient(45deg, transparent, transparent 40px, rgba(245,197,24,0.018) 40px, rgba(245,197,24,0.018) 41px)",
      }}
    >
      <Suspense>
        <LoginForm />
      </Suspense>
    </div>
  );
}
