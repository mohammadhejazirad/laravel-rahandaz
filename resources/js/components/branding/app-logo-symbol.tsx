import { useEffect, useState } from "react";
import { cn } from "@/lib/cn";

export type LogoTheme = "auto" | "light" | "dark";
export type LogoSize = "sm" | "md" | "lg";

const assets: Record<Exclude<LogoTheme, "auto">, string> = {
    light: "/branding/logo-symbol-light.svg",
    dark: "/branding/logo-symbol-dark.svg",
};

const sizes: Record<LogoSize, string> = {
    sm: "size-8",
    md: "size-10",
    lg: "size-13",
};

export function AppLogoSymbol({
    theme = "auto",
    size = "md",
    className,
    decorative = false,
    ariaLabel = "راه‌انداز",
}: {
    theme?: LogoTheme;
    size?: LogoSize;
    className?: string;
    decorative?: boolean;
    ariaLabel?: string;
}) {
    const resolvedTheme = useLogoTheme(theme);

    return (
        <img
            src={assets[resolvedTheme]}
            width="64"
            height="64"
            alt={decorative ? "" : ariaLabel}
            aria-hidden={decorative || undefined}
            className={cn("block shrink-0", sizes[size], className)}
        />
    );
}

function useLogoTheme(theme: LogoTheme): Exclude<LogoTheme, "auto"> {
    const [resolved, setResolved] = useState<Exclude<LogoTheme, "auto">>(() => resolveLogoTheme(theme));

    useEffect(() => {
        if (theme !== "auto") {
            setResolved(theme);
            return;
        }

        const root = document.documentElement;
        const update = () => setResolved(resolveLogoTheme("auto"));
        const observer = new MutationObserver(update);

        update();
        observer.observe(root, { attributes: true, attributeFilter: ["data-theme"] });

        return () => observer.disconnect();
    }, [theme]);

    return resolved;
}

function resolveLogoTheme(theme: LogoTheme): Exclude<LogoTheme, "auto"> {
    if (theme !== "auto") {
        return theme;
    }

    return document.documentElement.getAttribute("data-theme") === "dark" ? "dark" : "light";
}
