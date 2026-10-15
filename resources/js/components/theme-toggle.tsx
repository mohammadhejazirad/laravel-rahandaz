import { router, usePage } from "@inertiajs/react";
import { Laptop, Moon, Sun, type LucideIcon } from "lucide-react";
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

const options: Array<{
    value: ThemePreference;
    label: string;
    icon: LucideIcon;
}> = [
    {
        value: "light",
        label: "روشن",
        icon: Sun,
    },
    {
        value: "dark",
        label: "تیره",
        icon: Moon,
    },
    {
        value: "system",
        label: "سیستم",
        icon: Laptop,
    },
];

export function ThemeToggle() {
    const page = usePage<SharedProps>();
    const [preference, setPreference] = useState<ThemePreference>(readPreference);
    const [resolved, setResolved] = useState<"light" | "dark">(
        readResolvedTheme,
    );
    const [saving, setSaving] = useState(false);
    const selected = options.find((option) => option.value === preference) ?? options[2];
    const next = options[(options.indexOf(selected) + 1) % options.length];
    const SelectedIcon = selected.icon;
    const resolvedLabel = resolved === "dark" ? "تیره" : "روشن";
    const triggerLabel =
        preference === "system"
            ? `پوسته سیستم، اکنون ${resolvedLabel}`
            : `پوسته ${selected.label}`;
    const actionLabel = `${triggerLabel}؛ تغییر به ${next.label}`;

    useEffect(() => {
        const root = document.documentElement;
        const observer = new MutationObserver(() => {
            setPreference(readPreference());
            setResolved(readResolvedTheme());
        });

        observer.observe(root, {
            attributes: true,
            attributeFilter: ["data-theme", "data-theme-preference"],
        });

        return () => observer.disconnect();
    }, []);

    function select(next: ThemePreference) {
        if (next === preference || saving) {
            return;
        }

        const previous = readPreference();
        applyTheme(next);
        setPreference(next);
        setResolved(readResolvedTheme());
        persistGuestPreference(next);

        if (!page.props.auth.user) {
            return;
        }

        setSaving(true);
        router.patch(
            `${page.props.app.base_path}/theme`,
            { theme: next },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => {
                    applyTheme(previous);
                    setPreference(previous);
                    setResolved(readResolvedTheme());
                    persistGuestPreference(previous);
                    notify.error(
                        "ذخیره پوسته انجام نشد و انتخاب قبلی بازگردانده شد.",
                    );
                },
                onFinish: () => setSaving(false),
            },
        );
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span className="inline-flex">
                    <Button
                        type="button"
                        variant="secondary"
                        size="icon"
                        aria-label={`تغییر پوسته؛ ${actionLabel}`}
                        disabled={saving}
                        onClick={() => select(next.value)}
                    >
                        <SelectedIcon
                            className="size-[18px]"
                            aria-hidden="true"
                        />
                    </Button>
                </span>
            </TooltipTrigger>
            <TooltipContent side="bottom">{actionLabel}</TooltipContent>
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

    return isThemePreference(value) ? value : "system";
}

function readResolvedTheme(): "light" | "dark" {
    return document.documentElement.getAttribute("data-theme") === "dark"
        ? "dark"
        : "light";
}

function isThemePreference(value: string | null): value is ThemePreference {
    return value === "light" || value === "dark" || value === "system";
}
