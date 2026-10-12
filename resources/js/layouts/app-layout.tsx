import { Link, router, usePage } from '@inertiajs/react';
import { FolderGit2, HardDrive, LayoutDashboard, LogOut, Menu, Rocket, Server, Settings, X } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { FlashToaster } from '@/components/flash-toaster';
import { ThemeToggle } from '@/components/theme-toggle';
import type { SharedProps } from '@/types';

const roleLabels: Record<string, string> = {
    owner: 'مالک',
    admin: 'مدیر',
    operator: 'اپراتور',
    viewer: 'مشاهده‌گر',
};

export function AppLayout({ title, children }: { title: string; children: ReactNode }) {
    const page = usePage<SharedProps>();
    const base = page.props.app.base_path;
    const [open, setOpen] = useState(false);
    const path = page.url.split('?')[0] ?? '';

    useEffect(() => {
        function onKey(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        }

        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    const links = [
        { href: `${base}/dashboard`, label: 'داشبورد', icon: LayoutDashboard, enabled: true },
        { href: `${base}/setup`, label: 'راه‌اندازی', icon: Rocket, enabled: true },
        { href: '', label: 'سرور', icon: Server, enabled: false },
        { href: '', label: 'پروژه‌ها', icon: FolderGit2, enabled: false },
        { href: '', label: 'پشتیبان‌ها', icon: HardDrive, enabled: false },
        { href: '', label: 'تنظیمات', icon: Settings, enabled: false },
    ];

    return (
        <div className="min-h-screen bg-canvas">
            <div className="mx-auto flex min-h-screen max-w-[1440px]">
                <Sidebar path={path} links={links} className="hidden w-[264px] shrink-0 md:block" />
                <div className="min-w-0 flex-1">
                    <header className="flex items-center justify-between gap-3 border-b border-line px-4 py-3 md:px-6">
                        <div className="flex items-center gap-3">
                            <button type="button" className="rounded-lg border border-line p-2 md:hidden" aria-label="باز کردن منو" onClick={() => setOpen(true)}>
                                <Menu className="size-4" aria-hidden="true" />
                            </button>
                            <div>
                                <p className="text-xs text-muted">راه‌انداز</p>
                                <h1 className="text-lg font-semibold">{title}</h1>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
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
                    <main className="px-4 py-6 md:px-6">{children}</main>
                </div>
            </div>
            {open ? (
                <div className="fixed inset-0 z-40 bg-ink/40 md:hidden" onClick={() => setOpen(false)}>
                    <div className="absolute inset-y-0 end-0 w-[264px] bg-canvas" onClick={(event) => event.stopPropagation()}>
                        <div className="flex justify-end p-3">
                            <button type="button" className="rounded-lg border border-line p-2" aria-label="بستن منو" onClick={() => setOpen(false)}>
                                <X className="size-4" aria-hidden="true" />
                            </button>
                        </div>
                        <Sidebar path={path} links={links} />
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
    className = '',
}: {
    path: string;
    className?: string;
    links: Array<{ href: string; label: string; icon: typeof LayoutDashboard; enabled: boolean }>;
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
                        {roleLabels[user.role ?? ''] ?? 'بدون نقش'}
                    </p>
                ) : null}
            </div>
            <nav className="space-y-1 px-3 pb-6" aria-label="اصلی">
                {links.map((link) => {
                    const Icon = link.icon;
                    const active = link.enabled && (path === link.href || path.startsWith(`${link.href}/`));

                    if (!link.enabled) {
                        return (
                            <div key={link.label} className="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-muted">
                                <span className="inline-flex items-center gap-2">
                                    <Icon className="size-4" aria-hidden="true" />
                                    {link.label}
                                </span>
                                <span className="text-xs">به‌زودی</span>
                            </div>
                        );
                    }

                    return (
                        <Link
                            key={link.href}
                            href={link.href}
                            aria-current={active ? 'page' : undefined}
                            className={active ? 'flex items-center gap-2 rounded-lg bg-surface-muted px-3 py-2 text-sm font-medium text-ink' : 'flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-muted hover:bg-surface-muted'}
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
