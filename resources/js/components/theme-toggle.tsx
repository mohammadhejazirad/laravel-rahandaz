import { router, usePage } from "@inertiajs/react";
import { Laptop, Moon, Sun, type LucideIcon } from "lucide-react";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
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
    description: string;
    icon: LucideIcon;
}> = [
    {
        value: "light",
        label: "روشن",
        description: "همیشه از پوسته روشن استفاده شود",
        icon: Sun,
    },
    {
        value: "dark",
        label: "تیره",
        description: "همیشه از پوسته تیره استفاده شود",
        icon: Moon,
    },
    {
        value: "system",
        label: "سیستم",
        description: "از تنظیم روشن یا تیره دستگاه پیروی شود",
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
    const SelectedIcon = selected.icon;
    const resolvedLabel = resolved === "dark" ? "تیره" : "روشن";
    const triggerLabel =
        preference === "system"
            ? `پوسته سیستم، اکنون ${resolvedLabel}`
            : `پوسته ${selected.label}`;

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

    function select(next: string) {
        if (!isThemePreference(next) || next === preference || saving) {
            return;
        }

        const previous = readPreference();
        applyTheme(next);
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
        <DropdownMenu dir="rtl">
            <Tooltip>
                <TooltipTrigger asChild>
                    <span className="inline-flex">
                        <DropdownMenuTrigger asChild>
                            <Button
                                type="button"
                                variant="secondary"
                                size="icon"
                                aria-label={`تغییر پوسته؛ ${triggerLabel}`}
                                disabled={saving}
                            >
                                <SelectedIcon
                                    className="size-[18px]"
                                    aria-hidden="true"
                                />
                            </Button>
                        </DropdownMenuTrigger>
                    </span>
                </TooltipTrigger>
                <TooltipContent side="bottom">{triggerLabel}</TooltipContent>
            </Tooltip>
            <DropdownMenuContent align="end" className="min-w-64">
                <DropdownMenuLabel>انتخاب پوسته</DropdownMenuLabel>
                <DropdownMenuRadioGroup
                    value={preference}
                    onValueChange={select}
                >
                    {options.map((option) => {
                        const Icon = option.icon;

                        return (
                            <DropdownMenuRadioItem
                                key={option.value}
                                value={option.value}
                                disabled={saving}
                            >
                                <Icon aria-hidden="true" />
                                <span className="min-w-0">
                                    <span className="block font-medium">
                                        {option.label}
                                    </span>
                                    <span className="block text-xs leading-5 text-muted">
                                        {option.description}
                                    </span>
                                </span>
                            </DropdownMenuRadioItem>
                        );
                    })}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
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
