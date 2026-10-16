import { Form } from '@/components/ui/form';
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { ExternalLink, Plus, GitFork } from "lucide-react";
import { useState } from "react";
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

import { Modal, ModalContent, ModalHeader, ModalTitle, ModalDescription } from '@/components/ui/modal';
import { GitConnectionSteps } from '@/components/git/connection-steps';

export default function GitIndex({ connections, providers, can_manage, events }: {
    connections: GitAccount[]; providers: GitProviderConfig[]; can_manage: boolean; events: GitEvents | null;
}) {
    const page = usePage<SharedProps>();
    const base = page.props.app.base_path;
    const [adding, setAdding] = useState(false);
    const [chosen, setChosen] = useState<GitProviderConfig['provider'] | null>(null);
    const config = providers.find(provider => provider.provider === chosen);
    useVisiblePoll(true, "events,connections", 8000);
    return <AppLayout title="حساب‌های گیت" description="حساب خود را وصل کنید و مخزن‌هایی را که می‌خواهید در راه‌انداز استفاده کنید انتخاب کنید.">
        <Head title="حساب‌های گیت" />
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div><h2 className="font-semibold">حساب‌های متصل</h2><p className="mt-2 text-sm text-muted">هر حساب را یک‌بار متصل کنید؛ دسترسی مخزن‌ها از همین‌جا قابل مدیریت است.</p></div>
                {can_manage ? <Button onClick={() => { setChosen(null); setAdding(true); }}><Plus className="size-4" aria-hidden="true" />افزودن حساب</Button> : null}
            </div>
            {connections.length ? <ul className="mt-5 grid gap-3 lg:grid-cols-2">{connections.map(connection => <li key={connection.uuid} className="min-w-0 rounded-xl border border-line p-4">
                <div className="flex items-center gap-3"><GitProviderIcon provider={connection.provider} /><div className="min-w-0 flex-1"><h3 dir="ltr" className="break-all font-mono font-semibold">{connection.account_label}</h3><p className="mt-1 text-xs text-muted">{connection.provider === 'github' ? 'GitHub' : connection.provider === 'gitlabSelf' ? 'GitLab شخصی' : 'GitLab.com'}</p></div><span className="text-xs">{connection.state === 'revoked' ? 'قطع‌شده' : connection.state === 'error' ? 'نیازمند بررسی' : 'متصل'}</span></div>
                {connection.provider === 'gitlabSelf' ? <p dir="ltr" className="mt-3 break-all font-mono text-xs text-muted">{connection.base_url}</p> : null}
                <div className="mt-4 flex flex-wrap items-center justify-between gap-3"><p className="text-sm text-muted">{connection.selected_count.toLocaleString('fa-IR')} مخزن مجاز از {connection.repository_count.toLocaleString('fa-IR')}</p><Button asChild variant="secondary"><Link href={`${base}/git/connections/${connection.uuid}`}>انتخاب مخزن‌ها و تست اتصال</Link></Button></div>
            </li>)}</ul> : <div className="mx-auto max-w-md py-10 text-center"><GitFork className="mx-auto mb-4 size-8 text-accent" aria-hidden="true" /><h3 className="font-semibold">کدهای پروژه را به راه‌انداز وصل کنید</h3><p className="mt-3 text-sm leading-7 text-muted">GitHub، GitLab یا GitLab شخصی را انتخاب کنید. پس از تأیید حساب، فقط مخزن‌های موردنیاز را مجاز کنید.</p>{can_manage ? <Button className="mt-5" onClick={() => { setChosen(null); setAdding(true); }}>اتصال اولین حساب</Button> : null}</div>}
        </Card>
        {events ? <details className="rounded-2xl border border-line p-4" open={Boolean(new URLSearchParams(page.url.split('?')[1]).get('events_page'))}><summary className="min-h-11 cursor-pointer font-semibold">تاریخچهٔ فعالیت‌ها · زمان و نتیجهٔ تمام مرحله‌ها</summary><GitEventLog events={events} /></details> : null}
        <Modal open={adding} onOpenChange={setAdding}><ModalContent className="max-h-[90dvh] max-w-2xl overflow-y-auto">
            <ModalHeader><ModalTitle>افزودن حساب گیت</ModalTitle><ModalDescription>سرویس را انتخاب کنید، حساب را تأیید کنید و سپس به انتخاب مخزن‌ها بروید.</ModalDescription></ModalHeader>
            <GitConnectionSteps current={chosen ? 1 : 0} />
            {!config ? <div className="mt-5 grid gap-3 sm:grid-cols-3">{providers.map(provider => <button key={provider.provider} type="button" className="flex min-h-32 flex-col items-center justify-center gap-3 rounded-xl border border-line p-4 hover:border-accent hover:bg-surface-muted focus-visible:outline-2 focus-visible:outline-accent" onClick={() => setChosen(provider.provider)}><GitProviderIcon provider={provider.provider} className="size-8" /><span className="font-semibold">{provider.provider === 'github' ? 'GitHub' : provider.provider === 'gitlabSelf' ? 'GitLab شخصی' : 'GitLab.com'}</span><span className="text-xs text-muted">{provider.provider === 'gitlabSelf' ? 'روی سرور خودتان' : 'حساب شخصی یا سازمانی'}</span></button>)}</div> : <div className="mt-5"><Button variant="secondary" onClick={() => setChosen(null)}>تغییر سرویس</Button><ProviderCard key={config.provider} config={config} base={base} /></div>}
        </ModalContent></Modal>
    </AppLayout>;
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
        <div className="mt-5">
            <div className="flex items-center gap-2">
                <GitProviderIcon provider={config.provider} />
                <h2 className="font-semibold">اتصال به {name}</h2>
            </div>
            <p className="mt-3 text-sm leading-7 text-muted">
                {self
                    ? "حساب سرور GitLab خودتان را متصل کنید و مخزن‌های مجاز را انتخاب کنید."
                    : config.configured || github ? `با زدن دکمه، ${name} در مرورگر باز می‌شود؛ حساب را انتخاب و درخواست دسترسی را تأیید کنید.` : "حساب GitLab.com را با توکن یا برنامهٔ اتصال متصل کنید."}
            </p>
            {config.configured || github ? <>            <Button
                className="mt-4 w-full"
                loading={connecting}
                onClick={() => {
                    if (!config.configured && github) {
                        setConnecting(true);
                        router.post(`${base}/git/github/register`, {}, { onFinish: () => setConnecting(false) });
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
                {github && !config.configured ? "اتصال با تأیید در GitHub" : `ادامه در ${name}`}
            </Button>
            <p className="mt-2 text-xs leading-6 text-muted">
                {config.configured
                    ? "آمادهٔ اتصال؛ نیازی به واردکردن توکن حساب نیست."
                    : github ? "برنامه با مجوز فقط‌خواندنی ساخته می‌شود؛ نیازی به کپی‌کردن کلید نیست." : ""}
            </p>
            {config.configured ? <div className="mt-3"><TestConnectionButton url={`${base}/git/providers/${config.provider}/test`} automatic /></div> : null}
</> : <p className="mt-3 text-sm leading-7 text-muted">برای شروع سریع، توکن فقط‌خواندنی وارد کنید. برای ورود با مرورگر، مدیر این نصب باید برنامهٔ اتصال را فقط یک‌بار در تنظیمات پیشرفته ثبت کند.</p>}
            <ManualGitConnection config={config} base={base} expanded={!config.configured && !github} />
            <details
                className="mt-4 rounded-xl border border-line p-3"
            >
                <summary className="min-h-11 cursor-pointer text-sm font-medium">
                    تنظیمات پیشرفتهٔ اتصال با مرورگر
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

        </div>
    );
}
