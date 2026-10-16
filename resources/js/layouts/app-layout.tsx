import { Link, router, usePage } from "@inertiajs/react";
import { Bell, ChevronDown, LogOut, Menu, Settings } from 'lucide-react';
import { useEffect, useState, type ReactNode } from "react";
import { AppLogo } from "@/components/branding/app-logo";
import { FlashToaster } from "@/components/flash-toaster";
import { ContextualHelp } from "@/components/help-link";
import { QuickNavigation } from '@/components/navigation/quick-navigation';
import { SectionNavigation } from '@/components/navigation/section-navigation';
import { destinationForPath, visibleNavigation, type PanelGroup } from '@/lib/panel-navigation';
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
import { notify } from "@/lib/notify";
import type { AuthUser, SharedProps } from "@/types";

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
    const groups = visibleNavigation(canAudit);
    const destination = destinationForPath(path, base);

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
                    className="fixed right-0 top-0 z-50 hidden h-dvh w-[264px] overflow-y-auto lg:block"
                />
                <div className="min-w-0 flex-1 lg:pr-[264px]">
                    <header className="sticky top-0 z-40 border-b border-line bg-canvas">
                        <div className="flex min-h-[4.5rem] items-center justify-between gap-3 px-3 sm:px-5 md:px-7">
                            <div className="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
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
                                <div className="min-w-0 flex-1">
                                    <Link href={`${base}/dashboard`} className="text-xs text-muted hover:text-accent">پنل مدیریت راه‌انداز</Link>
                                    <p className="truncate text-sm font-semibold text-ink">{destination?.label ?? title}</p>
                                </div>
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <QuickNavigation base={base} groups={groups} />
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
                        className="mx-auto w-full min-w-0 max-w-[1480px] px-4 py-6 sm:px-5 md:px-7 md:py-8"
                    >
                        <header className="mb-5 flex min-w-0 flex-wrap items-end justify-between gap-4 border-b border-line pb-5">
                            <div className="min-w-0 max-w-3xl">
                                <p className="mb-1.5 text-xs font-semibold text-accent">
                                    {eyebrow}
                                </p>
                                <h1 className="break-words text-2xl font-bold leading-tight tracking-[-0.02em] text-ink sm:text-[1.75rem]">
                                    {title}
                                </h1>
                                {description ? (
                                    <p className="mt-2 max-w-2xl text-sm leading-7 text-muted sm:text-base">
                                        {description}
                                    </p>
                                ) : null}
                            </div>
                            <div className="flex flex-wrap items-center gap-3"><ContextualHelp path={path} /><span className="rounded-lg border border-line bg-surface px-3 py-2 text-xs text-muted">میزبان همین نصب</span></div>
                        </header>
                        <div className="page-content space-y-5">
                            <SectionNavigation path={path} base={base} canManage={canAudit} />
                            {page.props.execution.mode === "disabled" ? (
                                <Alert tone="warning">
                                    اجرای میزبان غیرفعال است؛ درخواست‌ها قابل پیگیری‌اند و نصب یا استقرار تا اجرای واقعی تأیید نمی‌شود.
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

function Sidebar({ path, groups, className, onNavigate, hideBrand = false }: {
    path: string; groups: PanelGroup[]; className?: string; onNavigate?: () => void; hideBrand?: boolean;
}) {
    const { auth, app } = usePage<SharedProps>().props;
    return <aside className={cn('border-l border-line bg-surface', className)}>
        {!hideBrand ? <div className="border-b border-line px-5 py-5"><AppLogo size="sm" showTagline={false} /></div> : null}
        <div className="m-3 rounded-xl border border-line bg-surface-muted px-3 py-3"><p className="text-[11px] text-muted">فضای کاری محلی</p><p className="mt-1 truncate text-sm font-semibold">{auth.user?.display_name || auth.user?.username}</p></div>
        <nav className="space-y-3 px-3 pb-5" aria-label="اصلی">{groups.map((group, index) => {
            const activeGroup = group.destinations.some(item => path === `${app.base_path}/${item.path}` || path.startsWith(`${app.base_path}/${item.path}/`));
            return <details key={group.label} open={index === 0 || activeGroup || undefined} className="group/nav">
                <summary className="flex min-h-11 list-none items-center justify-between gap-2 px-3 text-xs font-semibold text-muted [&::-webkit-details-marker]:hidden">{group.label}<ChevronDown className="size-3.5 group-open/nav:rotate-180" aria-hidden="true" /></summary>
                <div className="space-y-1">{group.destinations.map(item => {
                    const href = `${app.base_path}/${item.path}`;
                    const active = path === href || path.startsWith(`${href}/`);
                    return <Link key={item.path} href={href} aria-current={active ? 'page' : undefined} onClick={onNavigate} className={cn('flex min-h-11 items-center gap-3 rounded-xl border border-transparent px-3 py-2 text-sm transition-colors', active ? 'border-accent/15 bg-accent-soft font-semibold text-accent' : 'text-muted hover:bg-surface-muted hover:text-ink')}><item.icon className="size-4 shrink-0" aria-hidden="true" />{item.label}</Link>;
                })}</div>
            </details>;
        })}</nav>
        <div className="mx-4 mb-5 border-t border-line pt-4 text-xs leading-6 text-muted">راه‌انداز روی میزبان شما اجرا می‌شود؛ وضعیت هر درخواست در عملیات و لاگ‌ها قابل پیگیری است.</div>
    </aside>;
}

function initialOf(value: string): string {
    return value.trim().charAt(0).toLocaleUpperCase('fa-IR') || 'ر';
}
