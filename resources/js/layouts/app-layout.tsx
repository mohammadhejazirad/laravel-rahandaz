import { Link, router, usePage } from "@inertiajs/react";
import {
    Activity,
    ArchiveRestore,
    Bell,
    ChevronDown,
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
    type LucideIcon,
} from "lucide-react";
import { useEffect, useState, type ReactNode } from "react";
import { AppLogo } from "@/components/branding/app-logo";
import { FlashToaster } from "@/components/flash-toaster";
import { ThemeToggle } from "@/components/theme-toggle";
import { Alert } from "@/components/ui/alert";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { buttonVariants } from "@/components/ui/button-variants";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from "@/components/ui/sheet";
import {
    Modal,
    ModalClose,
    ModalContent,
    ModalDescription,
    ModalFooter,
    ModalHeader,
    ModalTitle,
} from "@/components/ui/modal";
import { cn } from "@/lib/cn";
import { labelOf } from "@/lib/labels";
import { notify } from "@/lib/notify";
import type { AuthUser, SharedProps } from "@/types";

interface NavLink {
    href: string;
    label: string;
    icon: LucideIcon;
    enabled: boolean;
    visible?: boolean;
}

interface NavGroup {
    label: string;
    links: NavLink[];
}

interface AppLayoutProps {
    title: string;
    description?: string;
    eyebrow?: string;
    children: ReactNode;
}

export function AppLayout({
    title,
    description,
    eyebrow = "فضای کاری محلی",
    children,
}: AppLayoutProps) {
    const page = usePage<SharedProps>();
    const base = page.props.app.base_path;
    const [mobileNavigationOpen, setMobileNavigationOpen] = useState(false);
    const path = page.url.split("?")[0] ?? "";
    const user = page.props.auth.user;
    const role = user?.role;
    const canAudit = role === "owner" || role === "admin";
    const unread = page.props.notifications.unread;
    const groups: NavGroup[] = [
        {
            label: "نمای کلی",
            links: [
                {
                    href: `${base}/dashboard`,
                    label: "داشبورد",
                    icon: LayoutDashboard,
                    enabled: true,
                },
                {
                    href: `${base}/setup`,
                    label: "راه‌اندازی",
                    icon: Rocket,
                    enabled: true,
                },
                {
                    href: `${base}/servers`,
                    label: "سرورها",
                    icon: Server,
                    enabled: true,
                },
            ],
        },
        {
            label: "منابع و استقرار",
            links: [
                {
                    href: `${base}/catalog`,
                    label: "نرم‌افزارها",
                    icon: Package,
                    enabled: true,
                },
                {
                    href: `${base}/mirrors`,
                    label: "میرورها",
                    icon: Shield,
                    enabled: true,
                },
                {
                    href: `${base}/projects`,
                    label: "پروژه‌ها",
                    icon: FolderGit2,
                    enabled: true,
                },
                {
                    href: "",
                    label: "استقرارها",
                    icon: Rocket,
                    enabled: false,
                },
                {
                    href: `${base}/jobs`,
                    label: "عملیات",
                    icon: ListChecks,
                    enabled: true,
                },
            ],
        },
        {
            label: "زیرساخت",
            links: [
                { href: "", label: "دامنه‌ها", icon: Globe2, enabled: false },
                { href: "", label: "دیتابیس‌ها", icon: Database, enabled: false },
                {
                    href: "",
                    label: "پشتیبان‌ها",
                    icon: ArchiveRestore,
                    enabled: false,
                },
                { href: "", label: "پایش", icon: Activity, enabled: false },
            ],
        },
        {
            label: "حساب و گزارش",
            links: [
                {
                    href: `${base}/notifications`,
                    label: "اعلان‌ها",
                    icon: Bell,
                    enabled: true,
                },
                {
                    href: `${base}/audit`,
                    label: "گزارش فعالیت",
                    icon: ScrollText,
                    enabled: true,
                    visible: canAudit,
                },
                {
                    href: `${base}/settings/profile`,
                    label: "تنظیمات",
                    icon: Settings,
                    enabled: true,
                },
            ],
        },
    ];

    useEffect(() => {
        const media = window.matchMedia("(min-width: 1024px)");
        const closeOnDesktop = () => {
            if (media.matches) {
                setMobileNavigationOpen(false);
            }
        };

        media.addEventListener("change", closeOnDesktop);

        return () => media.removeEventListener("change", closeOnDesktop);
    }, []);

    return (
        <div className="min-h-dvh bg-canvas">
            <a
                href="#main-content"
                className="fixed start-4 top-3 z-[90] -translate-y-20 rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-accent-foreground focus:translate-y-0"
            >
                رفتن به محتوای اصلی
            </a>
            <div className="flex min-h-dvh w-full">
                <Sidebar
                    path={path}
                    groups={groups}
                    className="sticky top-0 hidden h-dvh w-[280px] shrink-0 overflow-y-auto lg:block"
                />
                <div className="min-w-0 flex-1">
                    <header className="sticky top-0 z-40 border-b border-line bg-canvas/88 backdrop-blur-xl">
                        <div className="flex min-h-[4.5rem] items-center justify-between gap-3 px-3 sm:px-5 md:px-7">
                            <div className="flex min-w-0 items-center gap-3">
                                <Sheet
                                    open={mobileNavigationOpen}
                                    onOpenChange={setMobileNavigationOpen}
                                >
                                    <SheetTrigger asChild>
                                        <Button
                                            variant="secondary"
                                            size="icon"
                                            className="lg:hidden"
                                            aria-label="باز کردن منوی اصلی"
                                        >
                                            <Menu className="size-5" aria-hidden="true" />
                                        </Button>
                                    </SheetTrigger>
                                    <SheetContent side="right" className="gap-0">
                                        <SheetHeader>
                                            <AppLogo size="sm" showTagline={false} />
                                            <SheetTitle className="sr-only">
                                                منوی اصلی راه‌انداز
                                            </SheetTitle>
                                            <SheetDescription className="sr-only">
                                                دسترسی به بخش‌های پنل
                                            </SheetDescription>
                                        </SheetHeader>
                                        <Sidebar
                                            path={path}
                                            groups={groups}
                                            className="min-h-0 flex-1 overflow-y-auto border-0"
                                            onNavigate={() =>
                                                setMobileNavigationOpen(false)
                                            }
                                            hideBrand
                                        />
                                    </SheetContent>
                                </Sheet>
                                <div className="min-w-0">
                                    <p className="truncate text-xs text-muted">
                                        راه‌انداز / {title}
                                    </p>
                                    <p className="truncate text-sm font-semibold text-ink sm:text-base">
                                        {user?.display_name || user?.username}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Link
                                    href={`${base}/notifications`}
                                    className={cn(
                                        buttonVariants({
                                            variant: "secondary",
                                            size: "icon",
                                        }),
                                        "relative",
                                    )}
                                    aria-label={
                                        unread > 0
                                            ? `اعلان‌ها، ${unread} خوانده‌نشده`
                                            : "اعلان‌ها"
                                    }
                                >
                                    <Bell className="size-[18px]" aria-hidden="true" />
                                    {unread > 0 ? (
                                        <span className="absolute -start-1 -top-1 min-w-5 rounded-full bg-danger px-1 text-center text-[10px] leading-5 text-white ring-2 ring-canvas">
                                            {unread > 99 ? "99+" : unread}
                                        </span>
                                    ) : null}
                                </Link>
                                <ThemeToggle />
                                {user ? <UserMenu user={user} base={base} /> : null}
                            </div>
                        </div>
                    </header>
                    <main
                        id="main-content"
                        className="mx-auto w-full min-w-0 max-w-[1680px] px-4 py-6 sm:px-5 md:px-7 md:py-8"
                    >
                        <header className="mb-7 flex min-w-0 flex-wrap items-end justify-between gap-4 border-b border-line pb-5">
                            <div className="min-w-0 max-w-3xl">
                                <p className="mb-1.5 text-xs font-semibold text-accent">
                                    {eyebrow}
                                </p>
                                <h1 className="break-words text-2xl font-bold leading-tight tracking-[-0.02em] text-ink sm:text-3xl">
                                    {title}
                                </h1>
                                {description ? (
                                    <p className="mt-2 max-w-2xl text-sm leading-7 text-muted sm:text-base">
                                        {description}
                                    </p>
                                ) : null}
                            </div>
                            <span className="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3 py-1.5 text-xs text-muted shadow-sm">
                                <span
                                    className="size-2 rounded-full bg-success shadow-[0_0_0_4px_color-mix(in_srgb,var(--color-success)_12%,transparent)]"
                                    aria-hidden="true"
                                />
                                میزبان محلی
                            </span>
                        </header>
                        <div className="space-y-5">
                            {page.props.execution.mode === "disabled" ? (
                                <Alert tone="warning">
                                    اجرای دستور میزبان خاموش است. ثبت یک درخواست
                                    به‌معنی نصب یا استقرار موفق نیست.
                                </Alert>
                            ) : null}
                            {children}
                        </div>
                    </main>
                </div>
            </div>
            <FlashToaster />
        </div>
    );
}

function UserMenu({ user, base }: { user: AuthUser; base: string }) {
    const displayName = user.display_name || user.username;
    const [logoutOpen, setLogoutOpen] = useState(false);
    const [loggingOut, setLoggingOut] = useState(false);

    function logout() {
        setLoggingOut(true);
        router.post(
            `${base}/logout`,
            {},
            {
                onSuccess: () => setLogoutOpen(false),
                onError: () => notify.error("خروج از حساب انجام نشد."),
                onFinish: () => setLoggingOut(false),
            },
        );
    }

    return (
        <>
            <DropdownMenu dir="rtl">
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        className="flex h-11 max-w-48 items-center gap-2 rounded-xl border border-line bg-surface px-1.5 pe-2.5 text-start shadow-sm hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                        aria-label="باز کردن منوی حساب"
                    >
                        <Avatar>
                            <AvatarFallback>{initialOf(displayName)}</AvatarFallback>
                        </Avatar>
                        <span className="hidden min-w-0 sm:block">
                            <span className="block truncate text-xs font-semibold text-ink">
                                {displayName}
                            </span>
                            <span className="block truncate text-[11px] text-muted">
                                {user.username}
                            </span>
                        </span>
                        <ChevronDown
                            className="hidden size-3.5 text-muted sm:block"
                            aria-hidden="true"
                        />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuLabel>
                        <span className="block truncate text-sm font-semibold text-ink">
                            {displayName}
                        </span>
                        <span
                            className="mt-0.5 block font-mono text-[11px] font-normal text-muted"
                            dir="ltr"
                        >
                            {user.username}
                        </span>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuGroup>
                        <DropdownMenuItem asChild>
                            <Link href={`${base}/settings/profile`}>
                                <Settings aria-hidden="true" />
                                تنظیمات حساب
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={() => setLogoutOpen(true)}
                    >
                        <LogOut aria-hidden="true" />
                        خروج از حساب
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <Modal
                open={logoutOpen}
                onOpenChange={(open) => {
                    if (!loggingOut) {
                        setLogoutOpen(open);
                    }
                }}
            >
                <ModalContent
                    showCloseButton={false}
                    onEscapeKeyDown={(event) => {
                        if (loggingOut) {
                            event.preventDefault();
                        }
                    }}
                    onPointerDownOutside={(event) => {
                        if (loggingOut) {
                            event.preventDefault();
                        }
                    }}
                >
                    <ModalHeader className="pe-0">
                        <span
                            className="mb-3 grid size-12 place-items-center rounded-2xl bg-danger-soft text-danger"
                            aria-hidden="true"
                        >
                            <LogOut className="size-5" />
                        </span>
                        <ModalTitle>از حساب خارج می‌شوید؟</ModalTitle>
                        <ModalDescription>
                            نشست فعلی بسته می‌شود و برای ورود دوباره باید نام
                            کاربری و گذرواژه خود را وارد کنید.
                        </ModalDescription>
                    </ModalHeader>
                    <ModalFooter>
                        <ModalClose asChild>
                            <Button variant="secondary" disabled={loggingOut}>
                                خیر، بمان
                            </Button>
                        </ModalClose>
                        <Button
                            variant="danger"
                            loading={loggingOut}
                            loadingLabel="در حال خروج"
                            onClick={logout}
                        >
                            بله، خارج شو
                        </Button>
                    </ModalFooter>
                </ModalContent>
            </Modal>
        </>
    );
}

function Sidebar({
    path,
    groups,
    className,
    onNavigate,
    hideBrand = false,
}: {
    path: string;
    groups: NavGroup[];
    className?: string;
    onNavigate?: () => void;
    hideBrand?: boolean;
}) {
    const { auth, enum_labels: labels } = usePage<SharedProps>().props;
    const user = auth.user;

    return (
        <aside className={cn("border-e border-line bg-surface/96", className)}>
            {hideBrand ? null : (
                <div className="border-b border-line px-5 py-5">
                    <AppLogo />
                </div>
            )}
            {user ? (
                <div className="px-4 py-4">
                    <div className="flex items-center gap-3 rounded-xl border border-line bg-surface-muted/65 p-3">
                        <Avatar size="lg">
                            <AvatarFallback>
                                {initialOf(user.display_name || user.username)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold text-ink">
                                {user.display_name || user.username}
                            </p>
                            <p className="truncate text-xs text-muted">
                                {user.role
                                    ? labelOf(user.role, labels)
                                    : "بدون نقش"}
                            </p>
                        </div>
                    </div>
                </div>
            ) : null}
            <nav className="space-y-5 px-3 pb-8" aria-label="اصلی">
                {groups.map((group) => {
                    const visibleLinks = group.links.filter(
                        (link) => link.visible !== false,
                    );

                    if (visibleLinks.length === 0) {
                        return null;
                    }

                    return (
                        <div key={group.label}>
                            <p className="mb-1.5 px-3 text-[11px] font-semibold text-muted">
                                {group.label}
                            </p>
                            <div className="space-y-1">
                                {visibleLinks.map((link) => {
                                    const Icon = link.icon;
                                    const active =
                                        link.enabled &&
                                        (path === link.href ||
                                            path.startsWith(`${link.href}/`));

                                    if (!link.enabled) {
                                        return (
                                            <div
                                                key={link.label}
                                                aria-disabled="true"
                                                className="flex min-h-10 items-center justify-between gap-2 rounded-xl px-3 py-2 text-sm text-muted opacity-60"
                                            >
                                                <span className="inline-flex min-w-0 items-center gap-2.5">
                                                    <Icon
                                                        className="size-4 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                    {link.label}
                                                </span>
                                                <span className="shrink-0 rounded-full border border-line px-1.5 text-[10px]">
                                                    بعداً
                                                </span>
                                            </div>
                                        );
                                    }

                                    return (
                                        <Link
                                            key={link.href}
                                            href={link.href}
                                            aria-current={
                                                active ? "page" : undefined
                                            }
                                            onClick={onNavigate}
                                            className={cn(
                                                "group flex min-h-10 items-center gap-2.5 rounded-xl border border-transparent px-3 py-2 text-sm transition-colors",
                                                active
                                                    ? "border-accent/15 bg-accent-soft font-semibold text-accent"
                                                    : "text-muted hover:bg-surface-muted hover:text-ink",
                                            )}
                                        >
                                            <Icon
                                                className="size-4 shrink-0"
                                                aria-hidden="true"
                                            />
                                            {link.label}
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    );
                })}
            </nav>
        </aside>
    );
}

function initialOf(value: string): string {
    return value.trim().charAt(0).toLocaleUpperCase("fa-IR") || "ر";
}
