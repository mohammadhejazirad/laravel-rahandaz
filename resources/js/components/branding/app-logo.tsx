import { AppLogoSymbol, type LogoSize, type LogoTheme } from "@/components/branding/app-logo-symbol";
import { cn } from "@/lib/cn";

const textSizes: Record<LogoSize, { title: string; tagline: string }> = {
    sm: { title: "text-base", tagline: "text-[10px]" },
    md: { title: "text-lg", tagline: "text-[11px]" },
    lg: { title: "text-2xl", tagline: "text-xs" },
};

const textThemes: Record<LogoTheme, { title: string; tagline: string }> = {
    auto: { title: "text-ink", tagline: "text-muted" },
    light: { title: "text-slate-950", tagline: "text-slate-600" },
    dark: { title: "text-white", tagline: "text-slate-300" },
};

export function AppLogo({
    variant = "full",
    theme = "auto",
    size = "md",
    className,
    decorative = false,
    ariaLabel = "راه‌انداز، پنل مدیریت سرور محلی",
    showTagline = true,
}: {
    variant?: "full" | "symbol";
    theme?: LogoTheme;
    size?: LogoSize;
    className?: string;
    decorative?: boolean;
    ariaLabel?: string;
    showTagline?: boolean;
}) {
    if (variant === "symbol") {
        return <AppLogoSymbol theme={theme} size={size} className={className} decorative={decorative} ariaLabel={ariaLabel} />;
    }

    const textSize = textSizes[size];
    const textTheme = textThemes[theme];

    return (
        <div
            className={cn("inline-flex items-center gap-3", className)}
            role={decorative ? undefined : "img"}
            aria-label={decorative ? undefined : ariaLabel}
            aria-hidden={decorative || undefined}
        >
            <AppLogoSymbol theme={theme} size={size} decorative />
            <span className="leading-tight" aria-hidden="true">
                <span className={cn("block font-bold", textSize.title, textTheme.title)}>راه‌انداز</span>
                {showTagline ? (
                    <span className={cn("block font-medium", textSize.tagline, textTheme.tagline)}>پنل مدیریت سرور محلی</span>
                ) : null}
            </span>
        </div>
    );
}
