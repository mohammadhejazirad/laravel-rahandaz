import { Head, usePage } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
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
        <main className="flex min-h-screen items-center justify-center px-4">
            <Head title="خطا" />
            <section className="w-full max-w-md rounded-xl border border-line bg-surface p-6">
                <p className="font-mono text-sm text-muted" dir="ltr">
                    {status}
                </p>
                <h1 className="mt-2 text-2xl font-semibold">
                    {messages[status] ?? "درخواست انجام نشد."}
                </h1>
                <a className="mt-6 inline-block" href={`${base}/login`}>
                    <Button>بازگشت به ورود</Button>
                </a>
            </section>
        </main>
    );
}
