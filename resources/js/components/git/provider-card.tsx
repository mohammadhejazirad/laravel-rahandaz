import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ExternalLink } from 'lucide-react';
import { ManualGitConnection } from './manual-connection';
import { TestConnectionButton } from './test-connection-button';
import { GitProviderIcon } from './provider-icon';
import type { GitProviderConfig } from './types';
import { Form } from '@/components/ui/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export function GitProviderCard({
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
