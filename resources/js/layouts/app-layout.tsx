import { Link, router, usePage } from "@inertiajs/react";
import {
    Bell,
    FolderGit2,
    HardDrive,
    LayoutDashboard,
    ListChecks,
    LogOut,
    Menu,
    Package,
    Rocket,
    ScrollText,
    Server,
    Settings,
    Shield,
    X,
    type LucideIcon,
} from "lucide-react";
import { useEffect, useId, useRef, useState, type ReactNode } from "react";
import { FlashToaster } from "@/components/flash-toaster";
import { ThemeToggle } from "@/components/theme-toggle";
import { Alert } from "@/components/ui/alert";
import type { SharedProps } from "@/types";

const roleLabels: Record<string, string> = {
    owner: "مالک",
    admin: "مدیر",
    operator: "اپراتور",
    viewer: "مشاهده‌گر",
};

interface NavLink {
    href: string;
    label: string;
    icon: LucideIcon;
    enabled: boolean;
}

export function AppLayout({ title, children }: { title: string; children: ReactNode }) {
    const page = usePage<SharedProps>();
    const base = page.props.app.base_path;
    const [open, setOpen] = useState(false);
    const closeRef = useRef<HTMLButtonElement>(null);
    const titleId = useId();
    const path = page.url.split("?")[0] ?? "";
    const role = page.props.auth.user?.role;
    const canAudit = role === "owner" || role === "admin";
    const unread = page.props.notifications.unread;

    const links: NavLink[] = [
        { href: `${base}/dashboard`, label: "داشبورد", icon: LayoutDashboard, enabled: true },
        { href: `${base}/setup`, label: "راه‌اندازی", icon: Rocket, enabled: true },
        { href: `${base}/servers`, label: "سرورها", icon: Server, enabled: true },
        { href: `${base}/catalog`, label: "نرم‌افزارها", icon: Package, enabled: true },
        { href: `${base}/mirrors`, label: "میرورها", icon: Shield, enabled: true },
        { href: `${base}/projects`, label: "پروژه‌ها", icon: FolderGit2, enabled: true },
        { href: "", label: "استقرارها", icon: Rocket, enabled: false },
        { href: `${base}/jobs`, label: "عملیات", icon: ListChecks, enabled: true },
        { href: "", label: "دامنه‌ها", icon: Server, enabled: false },
        { href: "", label: "دیتابیس‌ها", icon: HardDrive, enabled: false },
        { href: "", label: "پشتیبان‌ها", icon: HardDrive, enabled: false },
        { href: "", label: "پایش", icon: LayoutDashboard, enabled: false },
        { href: `${base}/notifications`, label: "اعلان‌ها", icon: Bell, enabled: true },
        { href: `${base}/audit`, label: "گزارش فعالیت", icon: ScrollText, enabled: canAudit },
        { href: `${base}/settings/profile`, label: "تنظیمات", icon: Settings, enabled: true },
    ];

    useEffect(() => {
        if (!open) {
            return;
        }

        closeRef.current?.focus();

        function onKey(event: KeyboardEvent) {
            if (event.key === "Escape") {
                setOpen(false);
            }
        }

        window.addEventListener("keydown", onKey);
        return () => window.removeEventListener("keydown", onKey);
    }, [open]);

    return (
        <div className="min-h-screen bg-canvas">
            <div className="mx-auto flex min-h-screen max-w-[1440px]">
                <Sidebar path={path} links={links} className="hidden w-[264px] shrink-0 md:block" />
                <div className="min-w-0 flex-1">
                    <header className="flex items-center justify-between gap-3 border-b border-line px-4 py-3 md:px-6">
                        <div className="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                className="rounded-lg border border-line p-2 md:hidden"
                                aria-label="باز کردن منو"
                                aria-expanded={open}
                                onClick={() => setOpen(true)}
                            >
                                <Menu className="size-4" aria-hidden="true" />
                            </button>
                            <div className="min-w-0">
                                <p className="text-xs text-muted">راه‌انداز</p>
                                <h1 className="truncate text-lg font-semibold">{title}</h1>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Link
                                href={`${base}/notifications`}
                                className="relative rounded-lg border border-line p-2"
                                aria-label={unread > 0 ? `اعلان‌ها، ${unread} خوانده‌نشده` : "اعلان‌ها"}
                            >
                                <Bell className="size-4" aria-hidden="true" />
                                {unread > 0 ? (
                                    <span className="absolute -top-1 -start-1 min-w-5 rounded-full bg-[#B91C1C] px-1 text-center text-[10px] leading-5 text-white">
                                        {unread > 99 ? "99+" : unread}
                                    </span>
                                ) : null}
                            </Link>
                            <ThemeToggle />
                            <button
                                type="button"
                                className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-sm text-muted hover:bg-surface-muted"
                                onClick={() => router.post(`${base}/logout`)}
                            >
                                <LogOut className="size-4" aria-hidden="true" />
                                خروج
                            </button>
                        </div>
                    </header>
                    <main className="space-y-4 px-4 py-6 md:px-6">
                        {page.props.execution.mode === "disabled" ? (
                            <Alert tone="warning">
                                اجرای دستور میزبان خاموش است. ثبت یک درخواست به‌معنی نصب یا استقرار موفق نیست.
                            </Alert>
                        ) : null}
                        {children}
                    </main>
                </div>
            </div>
            {open ? (
                <div className="fixed inset-0 z-40 md:hidden">
                    <button type="button" className="absolute inset-0 bg-ink/40" aria-label="بستن منو" onClick={() => setOpen(false)} />
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby={titleId}
                        className="absolute inset-y-0 end-0 w-[264px] bg-canvas"
                    >
                        <div className="flex items-center justify-between p-3">
                            <p id={titleId} className="text-sm font-medium">منو</p>
                            <button ref={closeRef} type="button" className="rounded-lg border border-line p-2" aria-label="بستن منو" onClick={() => setOpen(false)}>
                                <X className="size-4" aria-hidden="true" />
                            </button>
                        </div>
                        <Sidebar path={path} links={links} onNavigate={() => setOpen(false)} />
                    </div>
                </div>
            ) : null}
            <FlashToaster />
        </div>
    );
}

function Sidebar({
    path,
    links,
    className = "",
    onNavigate,
}: {
    path: string;
    className?: string;
    links: NavLink[];
    onNavigate?: () => void;
}) {
    const page = usePage<SharedProps>();
    const user = page.props.auth.user;

    return (
        <aside className={`border-e border-line bg-surface ${className}`}>
            <div className="px-4 py-5">
                <p className="text-xs text-muted">کنترل‌پلین</p>
                <p className="text-xl font-semibold">راه‌انداز</p>
                {user ? (
                    <p className="mt-3 text-sm text-muted">
                        <span className="text-ink">{user.display_name || user.username}</span>
                        <span className="mx-1">·</span>
                        {roleLabels[user.role ?? ""] ?? "بدون نقش"}
                    </p>
                ) : null}
            </div>
            <nav className="space-y-1 px-3 pb-6" aria-label="اصلی">
                {links.filter((link) => link.enabled || link.label !== "گزارش فعالیت").map((link) => {
                    const Icon = link.icon;
                    const active = link.enabled && (path === link.href || path.startsWith(`${link.href}/`));

                    if (!link.enabled) {
                        return (
                            <div key={link.label} className="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-muted">
                                <span className="inline-flex items-center gap-2">
                                    <Icon className="size-4" aria-hidden="true" />
                                    {link.label}
                                </span>
                                <span className="text-xs">آماده نیست</span>
                            </div>
                        );
                    }

                    return (
                        <Link
                            key={link.href}
                            href={link.href}
                            aria-current={active ? "page" : undefined}
                            onClick={onNavigate}
                            className={
                                active
                                    ? "flex items-center gap-2 rounded-lg bg-surface-muted px-3 py-2 text-sm font-medium text-ink"
                                    : "flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-muted hover:bg-surface-muted"
                            }
                        >
                            <Icon className="size-4" aria-hidden="true" />
                            {link.label}
                        </Link>
                    );
                })}
            </nav>
        </aside>
    );
}
