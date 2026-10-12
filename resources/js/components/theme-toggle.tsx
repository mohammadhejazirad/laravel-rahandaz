import { router, usePage } from "@inertiajs/react";
import { Moon, Sun } from "lucide-react";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import { notify } from "@/lib/notify";
import { applyTheme } from "@/lib/theme";
import type { SharedProps, ThemePreference } from "@/types";

export function ThemeToggle() {
    const page = usePage<SharedProps>();
    const [resolved, setResolved] = useState<"light" | "dark">(
        readResolvedTheme,
    );
    const target = resolved === "dark" ? "light" : "dark";
    const label = target === "dark" ? "فعال‌کردن پوسته تیره" : "فعال‌کردن پوسته روشن";
    const Icon = target === "dark" ? Moon : Sun;

    useEffect(() => {
        const root = document.documentElement;
        const observer = new MutationObserver(() => {
            setResolved(readResolvedTheme());
        });

        observer.observe(root, {
            attributes: true,
            attributeFilter: ["data-theme"],
        });

        return () => observer.disconnect();
    }, []);

    function toggle() {
        const previous = readPreference();
        const next: ThemePreference = target;
        applyTheme(next);

        persistGuestPreference(next);

        if (page.props.auth.user) {
            router.patch(
                `${page.props.app.base_path}/theme`,
                { theme: next },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onError: () => {
                        applyTheme(previous);
                        persistGuestPreference(previous);
                        notify.error(
                            "ذخیره پوسته انجام نشد و انتخاب قبلی بازگردانده شد.",
                        );
                    },
                },
            );
            return;
        }
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="secondary"
                    size="icon"
                    aria-label={label}
                    onClick={toggle}
                >
                    <Icon className="size-[18px]" aria-hidden="true" />
                </Button>
            </TooltipTrigger>
            <TooltipContent side="bottom">{label}</TooltipContent>
        </Tooltip>
    );
}

function persistGuestPreference(preference: ThemePreference): void {
    try {
        localStorage.setItem("rahandaz-theme", preference);
    } catch {
        // The selected theme still applies to the current page.
    }
}

function readPreference(): ThemePreference {
    const value = document.documentElement.getAttribute(
        "data-theme-preference",
    );

    if (value === "light" || value === "dark" || value === "system") {
        return value;
    }

    return "system";
}

function readResolvedTheme(): "light" | "dark" {
    return document.documentElement.getAttribute("data-theme") === "dark"
        ? "dark"
        : "light";
}
