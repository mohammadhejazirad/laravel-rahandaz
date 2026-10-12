import type { ReactNode } from 'react';
import { FlashToaster } from '@/components/flash-toaster';
import { ThemeToggle } from '@/components/theme-toggle';

export function AuthLayout({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="flex min-h-screen items-center justify-center px-4 py-10">
            <div className="w-full max-w-md">
                <div className="mb-6 flex items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted">کنترل‌پلین</p>
                        <h1 className="text-2xl font-semibold text-ink">راه‌انداز</h1>
                    </div>
                    <ThemeToggle />
                </div>
                <section className="rounded-xl border border-line bg-surface p-6 shadow-sm">
                    <h2 className="mb-5 text-xl font-semibold">{title}</h2>
                    {children}
                </section>
            </div>
            <FlashToaster />
        </div>
    );
}
