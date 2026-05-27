import { withAuth } from "next-auth/middleware";

export default withAuth({
  pages: {
    signIn: "/login",
  },
});

export const config = {
  // Zaštiti sve rute osim: login, NextAuth API, Next.js internals, uploads
  matcher: [
    "/((?!login|api/auth|_next/static|_next/image|favicon\\.ico|uploads).*)",
  ],
};
