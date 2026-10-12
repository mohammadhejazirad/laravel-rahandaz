import { Link, router, usePage } from "@inertiajs/react";
import {
    Activity,
    ArchiveRestore,
    Bell,
    Database,
    FolderGit2,
    Globe2,
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
import { AppLogo } from "@/components/branding/app-logo";
import { FlashToaster } from "@/components/flash-toaster";
import { ThemeToggle } from "@/components/theme-toggle";
import { Alert } from "@/components/ui/alert";
import { labelOf } from "@/lib/labels";
import { notify } from "@/lib/notify";
import type { SharedProps } from "@/types";

interface NavLink {
    href: string;
    label: string;
    icon: LucideIcon;
    enabled: boolean;
    visible?: boolean;
}

interface AppLayoutProps {
    title: string;
    description?: string;
    eyebrow?: string;
    children: ReactNode;
}

export function AppLayout({ title, description, eyebrow = "فضای کاری محلی", children }: AppLayoutProps) {
    const page = usePage<SharedProps>();
    const base = page.props.app.base_path;
    const [open, setOpen] = useState(false);
    const menuRef = useRef<HTMLButtonElement>(null);
    const closeRef = useRef<HTMLButtonElement>(null);
    const drawerRef = useRef<HTMLDivElement>(null);
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
        { href: "", label: "دامنه‌ها", icon: Globe2, enabled: false },
        { href: "", label: "دیتابیس‌ها", icon: Database, enabled: false },
        { href: "", label: "پشتیبان‌ها", icon: ArchiveRestore, enabled: false },
        { href: "", label: "پایش", icon: Activity, enabled: false },
        { href: `${base}/notifications`, label: "اعلان‌ها", icon: Bell, enabled: true },
        { href: `${base}/audit`, label: "گزارش فعالیت", icon: ScrollText, enabled: true, visible: canAudit },
        { href: `${base}/settings/profile`, label: "تنظیمات", icon: Settings, enabled: true },
    ];

    useEffect(() => {
        const media = window.matchMedia("(min-width: 1024px)");
        const closeOnDesktop = () => {
            if (media.matches) {
                setOpen(false);
            }
        };

        media.addEventListener("change", closeOnDesktop);

        return () => media.removeEventListener("change", closeOnDesktop);
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        const previousBodyOverflow = document.body.style.overflow;
        const previousHtmlOverflow = document.documentElement.style.overflow;
        const menuButton = menuRef.current;
        document.body.style.overflow = "hidden";
        document.documentElement.style.overflow = "hidden";
        closeRef.current?.focus();

        function onKey(event: KeyboardEvent) {
            if (event.key === "Escape") {
                setOpen(false);
                return;
            }

            if (event.key !== "Tab") {
                return;
            }

            const focusable = drawerRef.current?.querySelectorAll<HTMLElement>(
                'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])',
            );

            if (!focusable?.length) {
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        window.addEventListener("keydown", onKey);

        return () => {
            window.removeEventListener("keydown", onKey);
            document.body.style.overflow = previousBodyOverflow;
            document.documentElement.style.overflow = previousHtmlOverflow;
            menuButton?.focus();
        };
    }, [open]);

    return (
        <div className="min-h-screen bg-canvas">
            <a
                href="#main-content"
                className="fixed start-4 top-3 z-[70] -translate-y-20 rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-accent-foreground focus:translate-y-0"
            >
                رفتن به محتوای اصلی
            </a>
            <div className="flex min-h-dvh w-full">
                <Sidebar path={path} links={links} className="sticky top-0 hidden h-dvh w-[264px] shrink-0 overflow-y-auto lg:block" />
                <div className="min-w-0 flex-1" inert={open ? true : undefined}>
                    <header className="sticky top-0 z-30 flex min-h-16 items-center justify-between gap-3 border-b border-line bg-canvas/85 px-3 py-2 backdrop-blur-xl sm:px-4 md:px-7">
                        <div className="flex min-w-0 items-center gap-3">
                            <button
                                ref={menuRef}
                                type="button"
                                className="grid size-11 shrink-0 place-items-center rounded-xl border border-line bg-surface shadow-sm lg:hidden"
                                aria-label="باز کردن منو"
                                aria-expanded={open}
                                aria-controls="mobile-navigation"
                                onClick={() => setOpen(true)}
                            >
                                <Menu className="size-5" aria-hidden="true" />
                            </button>
                            <div className="min-w-0">
                                <p className="hidden text-xs text-muted sm:block">راه‌انداز / {title}</p>
                                <p className="truncate text-sm font-semibold sm:text-base">
                                    {page.props.auth.user?.display_name || page.props.auth.user?.username}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-1.5 sm:gap-2">
                            <Link
                                href={`${base}/notifications`}
                                className="relative grid size-11 shrink-0 place-items-center rounded-xl border border-line bg-surface shadow-sm hover:bg-surface-muted"
                                aria-label={unread > 0 ? `اعلان‌ها، ${unread} خوانده‌نشده` : "اعلان‌ها"}
                            >
                                <Bell className="size-[18px]" aria-hidden="true" />
                                {unread > 0 ? (
                                    <span className="absolute -start-1 -top-1 min-w-5 rounded-full bg-[#B91C1C] px-1 text-center text-[10px] leading-5 text-white">
                                        {unread > 99 ? "99+" : unread}
                                    </span>
                                ) : null}
                            </Link>
                            <div className="hidden sm:block">
                                <ThemeToggle compact />
                            </div>
                            <button
                                type="button"
                                className="inline-flex size-11 shrink-0 items-center justify-center gap-1 rounded-xl text-sm text-muted hover:bg-surface-muted hover:text-ink lg:w-auto lg:px-3"
                                onClick={() => router.post(`${base}/logout`, {}, {
                                    onError: () => notify.error("خروج از حساب انجام نشد."),
                                })}
                                aria-label="خروج از حساب"
                            >
                                <LogOut className="size-4" aria-hidden="true" />
                                <span className="hidden lg:inline">خروج</span>
                            </button>
                        </div>
                    </header>
                    <main id="main-content" className="min-w-0 max-w-full px-4 py-6 sm:px-5 md:px-7 md:py-8">
                        <header className="mb-6 flex min-w-0 flex-wrap items-end justify-between gap-4">
                            <div className="min-w-0 max-w-3xl">
                                <p className="mb-1 text-xs font-semibold text-accent">{eyebrow}</p>
                                <h1 className="break-words text-2xl font-bold leading-tight text-ink sm:text-3xl">{title}</h1>
                                {description ? <p className="mt-2 text-sm leading-7 text-muted sm:text-base">{description}</p> : null}
                            </div>
                            <span className="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3 py-1 text-xs text-muted shadow-sm">
                                <span className="size-1.5 rounded-full bg-success" aria-hidden="true" />
                                میزبان محلی
                            </span>
                        </header>
                        <div className="space-y-4">
                            {page.props.execution.mode === "disabled" ? (
                                <Alert tone="warning">
                                    اجرای دستور میزبان خاموش است. ثبت یک درخواست به‌معنی نصب یا استقرار موفق نیست.
                                </Alert>
                            ) : null}
                            {children}
                        </div>
                    </main>
                </div>
            </div>
            {open ? (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <button type="button" className="absolute inset-0 bg-slate-950/55 backdrop-blur-sm" aria-label="بستن منو" onClick={() => setOpen(false)} />
                    <div
                        ref={drawerRef}
                        id="mobile-navigation"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby={titleId}
                        dir="rtl"
                        data-side="right"
                        className="rahandaz-drawer absolute inset-y-0 right-0 flex w-[min(85vw,320px)] max-w-full flex-col bg-surface shadow-2xl"
                    >
                        <div className="flex items-center justify-between gap-2 border-b border-line p-3">
                            <div id={titleId} className="min-w-0"><AppLogo size="sm" showTagline={false} /></div>
                            <button ref={closeRef} type="button" className="grid size-11 shrink-0 place-items-center rounded-lg border border-line" aria-label="بستن منو" onClick={() => setOpen(false)}>
                                <X className="size-4" aria-hidden="true" />
                            </button>
                        </div>
                        <Sidebar path={path} links={links} className="min-h-0 flex-1 overflow-y-auto" onNavigate={() => setOpen(false)} hideBrand />
                        <div className="border-t border-line p-3 sm:hidden">
                            <p className="mb-2 text-xs font-medium text-muted">پوسته</p>
                            <ThemeToggle />
                        </div>
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
    hideBrand = false,
}: {
    path: string;
    className?: string;
    links: NavLink[];
    onNavigate?: () => void;
    hideBrand?: boolean;
}) {
    const { auth, enum_labels: labels } = usePage<SharedProps>().props;
    const user = auth.user;

    return (
        <aside className={`border-e border-line bg-surface/95 ${className}`}>
            {hideBrand ? null : <div className="border-b border-line px-4 py-5"><AppLogo /></div>}
            <div className="px-4 py-4">
                {user ? (
                    <div className="rounded-xl border border-line bg-surface-muted/70 px-3 py-2.5">
                        <p className="truncate text-sm font-semibold text-ink">{user.display_name || user.username}</p>
                        <p className="text-xs text-muted">{user.role ? labelOf(user.role, labels) : "بدون نقش"}</p>
                    </div>
                ) : null}
            </div>
            <nav className="space-y-1 px-3 pb-6" aria-label="اصلی">
                {links.filter((link) => link.visible !== false).map((link) => {
                    const Icon = link.icon;
                    const active = link.enabled && (path === link.href || path.startsWith(`${link.href}/`));

                    if (!link.enabled) {
                        return (
                            <div key={link.label} aria-disabled="true" className="flex min-h-11 items-center justify-between gap-2 rounded-xl px-3 py-2 text-sm text-muted opacity-70">
                                <span className="inline-flex min-w-0 items-center gap-2">
                                    <Icon className="size-4 shrink-0" aria-hidden="true" />
                                    {link.label}
                                </span>
                                <span className="shrink-0 rounded-full border border-line px-1.5 text-[10px]">بعداً</span>
                            </div>
                        );
                    }

                    return (
                        <Link
                            key={link.href}
                            href={link.href}
                            aria-current={active ? "page" : undefined}
                            onClick={onNavigate}
                            className={active
                                ? "flex min-h-11 items-center gap-2 rounded-xl bg-accent-soft px-3 py-2 text-sm font-semibold text-accent"
                                : "flex min-h-11 items-center gap-2 rounded-xl px-3 py-2 text-sm text-muted hover:bg-surface-muted hover:text-ink"}
                        >
                            <Icon className="size-4 shrink-0" aria-hidden="true" />
                            {link.label}
                        </Link>
                    );
                })}
            </nav>
        </aside>
    );
}
