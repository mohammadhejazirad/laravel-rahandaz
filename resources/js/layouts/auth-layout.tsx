import { LockKeyhole, ServerCog, ShieldCheck, type LucideIcon } from "lucide-react";
import type { ReactNode } from "react";
import { AppLogo } from "@/components/branding/app-logo";
import { FlashToaster } from "@/components/flash-toaster";
import { ThemeToggle } from "@/components/theme-toggle";

import { PwaControls } from "@/components/pwa/pwa-controls";

export function AuthLayout({ title, children }: { title: string; children: ReactNode }) {
    return (
        <main className="relative flex min-h-dvh w-full items-center justify-center overflow-hidden px-4 py-8 sm:px-6">
            <div className="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_82%_10%,var(--color-accent-soft),transparent_28rem),radial-gradient(circle_at_10%_90%,var(--color-info-soft),transparent_24rem)]" />
            <div className="surface-shadow grid w-full min-w-0 max-w-5xl overflow-hidden rounded-[2rem] border border-line bg-surface/96 lg:grid-cols-[0.92fr_1.08fr]">
                <section className="relative hidden overflow-hidden bg-[#0b1220] p-10 text-white lg:flex lg:flex-col lg:justify-between">
                    <div className="absolute -start-24 -top-24 size-64 rounded-full border border-white/10" aria-hidden="true" />
                    <div className="relative">
                        <AppLogo theme="dark" />
                        <p className="mt-12 max-w-sm text-3xl font-bold leading-[1.65]">پروژه‌ها و سرورتان، در یک فضای روشن و ساده.</p>
                        <p className="mt-4 max-w-sm text-sm leading-7 text-slate-300">کد پروژه را آماده کنید، محیط را بشناسید و نتیجهٔ هر درخواست را مرحله‌به‌مرحله دنبال کنید.</p>
                    </div>
                    <ul className="relative mt-12 space-y-4 text-sm text-slate-200">
                        <AuthFeature icon={ServerCog}>پنل مدیریت تک‌میزبان و خودمیزبان</AuthFeature>
                        <AuthFeature icon={ShieldCheck}>مجوز سمت سرور و گزارش فعالیت</AuthFeature>
                        <AuthFeature icon={LockKeyhole}>دسترسی خصوصی برای مالک و اعضای تیم</AuthFeature>
                    </ul>
                </section>
                <section className="p-5 sm:p-8 lg:p-11">
                    <div className="mb-10 flex items-center justify-between gap-3 lg:justify-end">
                        <AppLogo className="lg:hidden" />
                        <ThemeToggle />
                    </div>
                    <div className="mx-auto max-w-md">
                        <p className="text-xs font-semibold text-accent">دسترسی امن</p>
                        <h1 className="mt-1 text-2xl font-bold sm:text-3xl">{title}</h1>
                        <PwaControls />
                        <div className="mt-7">{children}</div>
                    </div>
                </section>
            </div>
            <FlashToaster />
        </main>
    );
}

function AuthFeature({ icon: Icon, children }: { icon: LucideIcon; children: ReactNode }) {
    return (
        <li className="flex items-center gap-3">
            <span className="grid size-9 place-items-center rounded-xl bg-white/10" aria-hidden="true"><Icon className="size-4" /></span>
            {children}
        </li>
    );
}
