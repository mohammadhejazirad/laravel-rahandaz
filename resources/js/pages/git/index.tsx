import { Form } from '@/components/ui/form';
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { ExternalLink, ShieldCheck } from "lucide-react";
import { useRef, useState } from "react";
import { ManualGitConnection } from '@/components/git/manual-connection';
import { TestConnectionButton } from '@/components/git/test-connection-button';
import { GitProviderIcon } from '@/components/git/provider-icon';
import { GitEventLog } from "@/components/git/event-log";
import type {
    GitAccount,
    GitEvents,
    GitProviderConfig,
} from "@/components/git/types";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { useVisiblePoll } from "@/hooks/use-visible-poll";
import { AppLayout } from "@/layouts/app-layout";
import type { SharedProps } from "@/types";

export default function GitIndex({
    connections,
    providers,
    can_manage,
    events,
}: {
    connections: GitAccount[];
    providers: GitProviderConfig[];
    can_manage: boolean;
    events: GitEvents | null;
}) {
    const base = usePage<SharedProps>().props.app.base_path;
    useVisiblePoll(true, "events,connections", 8000);
    return (
        <AppLayout
            title="حساب‌های گیت"
            description="اتصال به GitHub، GitLab و GitLab شخصی، انتخاب مخزن‌های مجاز و پیگیری همهٔ مرحله‌ها."
        >
            <Head title="حساب‌های گیت" />
            <Card>
                <div className="flex items-start gap-3">
                    <ShieldCheck
                        className="mt-1 size-5 shrink-0 text-accent"
                        aria-hidden="true"
                    />
                    <p className="text-sm leading-7 text-muted">
                        ابتدا حساب را در سرویس تأیید کنید، سپس مخزن‌های مجاز را
                        در پنل انتخاب کنید. در GitHub هنگام نصب برنامه، گزینهٔ
                        Only select repositories را برای محدودکردن مجوز واقعی
                        انتخاب کنید. در GitLab، انتخاب پنل دسترسی خود توکن OAuth
                        را محدود نمی‌کند.
                    </p>
                </div>
            </Card>
            {can_manage ? (
                <div className="grid gap-4 xl:grid-cols-3">
                    {providers.map((provider) => (
                        <ProviderCard
                            key={provider.provider}
                            config={provider}
                            base={base}
                        />
                    ))}
                </div>
            ) : null}
            <Card>
                <h2 className="font-semibold">حساب‌های متصل</h2>
                {connections.length ? (
                    <ul className="mt-4 grid gap-3 md:grid-cols-2">
                        {connections.map((connection) => (
                            <li
                                key={connection.uuid}
                                className="min-w-0 rounded-xl border border-line p-4"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <span className="text-sm text-muted">
                                        {connection.provider === "github"
                                            ? "GitHub"
                                            : connection.provider ===
                                                "gitlabSelf"
                                              ? "GitLab شخصی"
                                              : "GitLab"}
                                    </span>
                                    <span className="text-xs">
                                        {connection.state === "revoked"
                                            ? "قطع‌شده"
                                            : connection.state === "error"
                                              ? "نیازمند بررسی"
                                              : "متصل"}
                                    </span>
                                </div>
                                <p
                                    className="my-3 break-all font-mono"
                                    dir="ltr"
                                >
                                    {connection.account_label}
                                </p>
                                {connection.provider === "gitlabSelf" ? (
                                    <p
                                        className="mb-3 break-all font-mono text-xs text-muted"
                                        dir="ltr"
                                    >
                                        {connection.base_url}
                                    </p>
                                ) : null}
                                <p className="mb-4 text-sm text-muted">
                                    {connection.selected_count.toLocaleString(
                                        "fa-IR",
                                    )}{" "}
                                    مخزن مجاز از{" "}
                                    {connection.repository_count.toLocaleString(
                                        "fa-IR",
                                    )}{" "}
                                    مخزن دریافت‌شده
                                </p>
                                <Button asChild variant="secondary">
                                    <Link
                                        href={`${base}/git/connections/${connection.uuid}`}
                                    >
                                        مخزن‌ها و تاریخچه
                                    </Link>
                                </Button>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="py-8 text-center text-sm text-muted">
                        هنوز حساب قابل نمایشی متصل نشده است.
                    </p>
                )}
            </Card>
            {events ? <GitEventLog events={events} /> : null}
        </AppLayout>
    );
}

function ProviderCard({
    config,
    base,
}: {
    config: GitProviderConfig;
    base: string;
}) {
    const github = config.provider === "github";
    const self = config.provider === "gitlabSelf";
    const name = github ? "GitHub" : self ? "GitLab شخصی" : "GitLab";
    const details = useRef<HTMLDetailsElement>(null);
    const [connecting, setConnecting] = useState(false);
    const form = useForm({
        client_id: config.client_id,
        client_secret: "",
        app_slug: config.app_slug,
        base_url: config.base_url,
    });
    let registrationUrl: string | null = github
        ? "https://github.com/settings/apps/new"
        : "https://gitlab.com/-/user_settings/applications";
    if (self) {
        try {
            const origin = new URL(form.data.base_url);
            registrationUrl =
                origin.protocol === "https:" &&
                !origin.username &&
                !origin.password &&
                !origin.port &&
                origin.pathname === "/" &&
                !origin.search &&
                !origin.hash
                    ? `${origin.origin}/-/user_settings/applications`
                    : null;
        } catch {
            registrationUrl = null;
        }
    }
    return (
        <Card>
            <div className="flex items-center gap-2">
                <GitProviderIcon provider={config.provider} />
                <h2 className="font-semibold">اتصال به {name}</h2>
            </div>
            <p className="mt-3 text-sm leading-7 text-muted">
                {self
                    ? "حساب سرور GitLab خودتان را متصل کنید و مخزن‌های مجاز را انتخاب کنید."
                    : `با زدن دکمه، ${name} در مرورگر باز می‌شود؛ حساب را انتخاب و درخواست دسترسی را تأیید کنید.`}
            </p>
            <Button
                className="mt-4 w-full"
                loading={connecting}
                onClick={() => {
                    if (!config.configured) {
                        if (details.current) {
                            details.current.open = true;
                            details.current
                                .querySelector<HTMLInputElement>("form input")
                                ?.focus();
                        }
                        return;
                    }
                    setConnecting(true);
                    router.post(
                        `${base}/git/authorize/${config.provider}`,
                        {},
                        { onFinish: () => setConnecting(false) },
                    );
                }}
            >
                <GitProviderIcon provider={config.provider} className="size-4 text-current" />
                اتصال به {name}
            </Button>
            <p className="mt-2 text-xs leading-6 text-muted">
                {config.configured
                    ? "آمادهٔ اتصال؛ نیازی به واردکردن توکن حساب نیست."
                    : "راه‌اندازی اولیه لازم است؛ تنظیم برنامه را یک‌بار انجام دهید."}
            </p>
            <div className="mt-3"><TestConnectionButton url={`${base}/git/providers/${config.provider}/test`} automatic /></div>
            <details
                ref={details}
                className="mt-4 rounded-xl border border-line p-3"
            >
                <summary className="min-h-11 cursor-pointer text-sm font-medium">
                    {config.configured
                        ? "ویرایش تنظیمات برنامه"
                        : "تنظیم برنامهٔ اتصال"}
                </summary>
                <p className="mt-3 text-sm leading-7 text-muted">
                    {github
                        ? "یک GitHub App با Contents و Metadata فقط‌خواندنی بسازید. Callback و Setup URL را برابر نشانی زیر قرار دهید. Request user authorization during installation خاموش و Redirect on update روشن باشد."
                        : `در ${self ? "سرور GitLab خودتان" : "GitLab.com"} یک OAuth Application محرمانه با read_user، read_api و read_repository بسازید و Redirect URI زیر را ثبت کنید.`}
                </p>

                <Input
                    label="نشانی بازگشت برای ثبت در سرویس"
                    name={`${config.provider}-callback`}
                    value={config.callback_url}
                    readOnly
                    dir="ltr"
                    className="mt-2 font-mono text-sm"
                    onFocus={(e) => e.target.select()}
                />
                <Form
                    className="mt-3 space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(`${base}/git/providers/${config.provider}`, {
                            preserveScroll: true,
                            onSuccess: () => form.reset("client_secret"),
                            onError: () => form.reset("client_secret"),
                        });
                    }}
                >
                    {self ? (
                        <Input
                            label="نشانی سرور GitLab شخصی"
                            name="gitlab-self-url"
                            type="url"
                            dir="ltr"
                            className="font-mono"
                            placeholder="https://git.example.com"
                            isRequired
                            value={form.data.base_url}
                            onChange={(e) =>
                                form.setData("base_url", e.target.value)
                            }
                            error={form.errors.base_url}
                            help="HTTPS و دامنهٔ عمومی؛ نشانی داخلی، پورت سفارشی و مسیر اضافه پذیرفته نمی‌شود."
                        />
                    ) : null}
                    {registrationUrl ? (
                        <Button className="mt-3" asChild variant="secondary">
                            <a
                                href={registrationUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                ساخت برنامه در {name}
                                <ExternalLink
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </a>
                        </Button>
                    ) : null}
                    <Input
                        label="Client ID"
                        name={`${config.provider}-client-id`}
                        dir="ltr"
                        className="font-mono"
                        isRequired
                        value={form.data.client_id}
                        onChange={(e) =>
                            form.setData("client_id", e.target.value)
                        }
                        error={form.errors.client_id}
                    />
                    {github ? (
                        <Input
                            label="نام برنامه در نشانی GitHub (App slug)"
                            name="github-app-slug"
                            dir="ltr"
                            className="font-mono"
                            isRequired
                            value={form.data.app_slug}
                            onChange={(e) =>
                                form.setData("app_slug", e.target.value)
                            }
                            error={form.errors.app_slug}
                        />
                    ) : null}
                    <Input
                        label="Client Secret"
                        name={`${config.provider}-client-secret`}
                        type="password"
                        dir="ltr"
                        autoComplete="new-password"
                        isRequired={!config.configured}
                        value={form.data.client_secret}
                        onChange={(e) =>
                            form.setData("client_secret", e.target.value)
                        }
                        error={form.errors.client_secret}
                        help={
                            config.configured
                                ? "خالی بگذارید تا راز قبلی حفظ شود."
                                : "فقط هنگام ذخیره ارسال و در سرور رمزگذاری می‌شود."
                        }
                    />
                    <Button
                        type="submit"
                        loading={form.processing}
                        variant="secondary"
                    >
                        ذخیرهٔ تنظیمات
                    </Button>
                </Form>
            </details>
            <ManualGitConnection config={config} base={base} />
        </Card>
    );
}
