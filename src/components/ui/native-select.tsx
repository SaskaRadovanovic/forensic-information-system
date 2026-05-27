import * as React from "react";
import { cn } from "@/lib/utils";

/**
 * Native <select> wrapper styled for the ForenzIS dark theme.
 * Uses React.forwardRef so it works seamlessly with react-hook-form register().
 */
const NativeSelect = React.forwardRef<
  HTMLSelectElement,
  React.SelectHTMLAttributes<HTMLSelectElement>
>(({ className, children, ...props }, ref) => {
  return (
    <select
      ref={ref}
      className={cn(
        "flex h-9 w-full rounded-md px-3 py-1 text-sm shadow-xs",
        "bg-fis-surface2 text-fis-text1 border border-fis-surface3",
        "focus:outline-none focus-visible:ring-2 focus-visible:ring-fis-yellow focus-visible:ring-offset-0",
        "disabled:cursor-not-allowed disabled:opacity-50",
        "[&>option]:bg-fis-surface2 [&>option]:text-fis-text1",
        className
      )}
      {...props}
    >
      {children}
    </select>
  );
});
NativeSelect.displayName = "NativeSelect";

export { NativeSelect };
