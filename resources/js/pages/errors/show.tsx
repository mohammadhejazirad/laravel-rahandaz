import { Head, usePage } from "@inertiajs/react";
import { AlertTriangle } from "lucide-react";
import { AppLogo } from "@/components/branding/app-logo";
import type { SharedProps } from "@/types";

const messages: Record<number, string> = {
    403: "به این بخش دسترسی ندارید.",
    404: "این نشانی پیدا نشد.",
    419: "نشست منقضی شده است. صفحه را تازه کنید.",
    500: "خطای داخلی رخ داد. شناسه درخواست را در لاگ سرور جستجو کنید.",
    503: "سرویس موقتاً در دسترس نیست.",
};

export default function ErrorPage({ status }: { status: number }) {
    const props = usePage().props as Partial<SharedProps>;
    const base = props.app?.base_path ?? "/rahandaz";

    return (
        <main className="flex min-h-screen items-center justify-center px-4 py-8">
            <Head title="خطا" />
            <section className="surface-shadow w-full max-w-lg rounded-[1.75rem] border border-line bg-surface/95 p-6 sm:p-8">
                <AppLogo />
                <span className="mt-10 grid size-12 place-items-center rounded-2xl bg-danger-soft text-danger" aria-hidden="true">
                    <AlertTriangle className="size-6" />
                </span>
                <p className="technical-value mt-5 text-sm font-semibold text-danger">HTTP {status}</p>
                <h1 className="mt-2 text-2xl font-bold sm:text-3xl">
                    {messages[status] ?? "درخواست انجام نشد."}
                </h1>
                <p className="mt-3 text-sm leading-7 text-muted">اگر این خطا ادامه داشت، زمان رخداد را همراه شناسه درخواست در لاگ داخلی بررسی کنید.</p>
                {props.auth?.user && status !== 419 ? <a href={`${base}/dashboard`} className="mt-5 inline-flex min-h-11 items-center text-sm font-semibold text-accent">بازگشت به داشبورد</a> : null}
                <a className="mt-7 inline-flex min-h-11 items-center justify-center rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-accent-foreground shadow-sm" href={`${base}/login`}>
                    بازگشت به ورود
                </a>
            </section>
        </main>
    );
}
