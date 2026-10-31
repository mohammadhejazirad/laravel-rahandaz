import { RepositorySelection, type Repository } from '@/components/git/repository-selection';
import { ReloadButton } from '@/components/ui/request-button';
import { Input } from '@/components/ui/input';
import { GitConnectionSteps } from '@/components/git/connection-steps';
import { Form } from '@/components/ui/form';
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { GitProviderIcon } from '@/components/git/provider-icon';
import { TestConnectionButton } from '@/components/git/test-connection-button';
import { GitEventLog } from "@/components/git/event-log";
import type { GitAccount, GitEvents } from "@/components/git/types";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import {
    Modal,
    ModalContent,
    ModalDescription,
    ModalFooter,
    ModalHeader,
    ModalTitle,
} from "@/components/ui/modal";
import { Pagination, type PageMeta } from "@/components/ui/pagination";
import { useVisiblePoll } from "@/hooks/use-visible-poll";
import { AppLayout } from "@/layouts/app-layout";
import type { SharedProps } from "@/types";

export default function GitShow({
    connection,
    repositories,
    events,
    can_manage,
    manage_url,
    filters,
}: {
    connection: GitAccount;
    repositories: { data: Repository[]; meta: PageMeta };
    events: GitEvents;
    can_manage: boolean;
    manage_url: string;
    filters: { search: string };
}) {
    const page = usePage<SharedProps>();
    const base = page.props.app.base_path;
    const url = `${base}/git/connections/${connection.uuid}`;
    const [confirm, setConfirm] = useState(false);
    const [disconnecting, setDisconnecting] = useState(false);
    const [syncing, setSyncing] = useState(false);
    const [watchUntil, setWatchUntil] = useState(0);
    useVisiblePoll(connection.sync_pending || watchUntil > Date.now(), "events,connection", 5000);
    useEffect(() => {
        if (watchUntil <= Date.now()) return;
        const timer = window.setTimeout(() => setWatchUntil(0), watchUntil - Date.now());
        return () => window.clearTimeout(timer);
    }, [watchUntil]);
    const lastSync = useRef(connection.last_synced_at);
    useEffect(() => {
        if (lastSync.current !== connection.last_synced_at) {
            lastSync.current = connection.last_synced_at;
            router.reload({ only: ["repositories"] });
        }
    }, [connection.last_synced_at]);
    const search = useForm({ search: filters.search });
    const revoked = connection.state === "revoked";
    return (
        <AppLayout
            title="مخزن‌ها و دسترسی حساب"
            description="مخزن‌های موردنیاز را پیدا کنید، دسترسی‌ها را ذخیره و اتصال را تست کنید."
        >
            <Head title={`حساب ${connection.account_label}`} />
            <Link className="text-sm text-accent" href={`${base}/git`}>
                بازگشت به حساب‌های گیت
            </Link>
            <GitConnectionSteps current={2} />
            <Card>
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="min-w-0">
                        <p className="flex items-center gap-2 text-sm text-muted">
                            <GitProviderIcon provider={connection.provider} />
                            {connection.provider === "github"
                                ? "GitHub"
                                : connection.provider === "gitlabSelf"
                                  ? "GitLab شخصی"
                                  : "GitLab"}
                        </p>
                        <h2
                            dir="ltr"
                            className="mt-2 break-all font-mono text-lg font-semibold"
                        >
                            {connection.account_label}
                        </h2>
                    </div>
                    <span className="text-sm">
                        {revoked
                            ? "دسترسی پنل قطع شده"
                            : `${connection.selected_count.toLocaleString("fa-IR")} مخزن مجاز`}
                    </span>
                </div>
                {can_manage ? (
                    <div className="mt-5 flex flex-wrap gap-3">
                        <TestConnectionButton url={`${url}/test`} disabled={revoked} />
                        <Button
                            loading={syncing || connection.sync_pending}
                            loadingLabel="در حال دریافت و بررسی مخزن‌ها…"
                            disabled={revoked}
                            onClick={() => {
                                setSyncing(true);
                                setWatchUntil(Date.now() + 180_000);
                                router.post(
                                    `${url}/sync`,
                                    {},
                                    {
                                        preserveScroll: true,
                                        onFinish: () => setSyncing(false),
                                    },
                                );
                            }}
                        >
                            دریافت دوبارهٔ مخزن‌ها
                        </Button>
                        <Button asChild variant="secondary">
                            <a
                                href={manage_url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                مدیریت مجوز در سرویس
                            </a>
                        </Button>
                        <Button
                            variant="danger"
                            disabled={revoked}
                            onClick={() => setConfirm(true)}
                        >
                            قطع دسترسی پنل
                        </Button>
                    </div>
                ) : null}
            </Card>
            {connection.provider === "gitlabSelf" ? (
                <p dir="ltr" className="break-all font-mono text-sm text-muted">
                    {connection.base_url}
                </p>
            ) : null}
            <Alert tone="info">
                انتخاب‌های زیر مجوز استفاده در پنل را تعیین می‌کنند. برای
                محدودکردن دسترسی واقعی GitHub App از «مدیریت مجوز در سرویس»
                استفاده کنید. در GitLab دامنهٔ مجوز OAuth مستقل از انتخاب‌های
                پنل است.
            </Alert>
            <Card>
                <Form className="flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={event => { event.preventDefault(); search.get(url, { preserveState: true, preserveScroll: true }); }}>
                    <div className="min-w-0 flex-1"><Input label="جست‌وجوی مخزن" name="search" maxLength={200} dir="ltr" className="font-mono" placeholder="team / repository" value={search.data.search} onChange={event => search.setData('search', event.target.value)} error={search.errors.search} /></div>
                    <Button type="submit" variant="secondary" loading={search.processing}>جست‌وجو</Button>
                    {filters.search ? <Button asChild variant="secondary"><Link href={url}>پاک‌کردن جست‌وجو</Link></Button> : null}
                </Form>
                <p className="mt-3 text-xs text-muted">پیش از جست‌وجو یا تغییر صفحه، انتخاب‌های فعلی را ذخیره کنید.</p>
            </Card>
            <RepositorySelection
                key={`${filters.search}-${repositories.meta.current_page}-${connection.last_synced_at}-${connection.state}`}
                repositories={repositories.data}
                canManage={can_manage && !revoked}
                url={url}
            />
            <Pagination meta={repositories.meta} />
            <ReloadButton only={["repositories", "connection"]}>تازه‌سازی نمایش مخزن‌ها</ReloadButton>
            {!revoked && connection.selected_count > 0 ? <Button asChild><Link href={`${base}/projects`}>ادامه به پروژه‌ها</Link></Button> : null}
            <details className="rounded-2xl border border-line p-4" open={Boolean(new URLSearchParams(page.url.split('?')[1]).get('events_page'))}><summary className="min-h-11 cursor-pointer font-semibold">تاریخچهٔ مرحله‌ها و نتیجهٔ تست اتصال</summary><GitEventLog events={events} /></details>
            <Modal open={confirm} onOpenChange={setConfirm}>
                <ModalContent>
                    <ModalHeader>
                        <ModalTitle>قطع دسترسی این حساب؟</ModalTitle>
                        <ModalDescription>
                            همهٔ انتخاب‌های مخزن این حساب در پنل غیرفعال
                            می‌شوند. مجوز برنامه در GitHub یا GitLab باید
                            جداگانه از سایت سرویس لغو شود. اتصال دوباره از صفحهٔ
                            حساب‌های گیت ممکن است.
                        </ModalDescription>
                    </ModalHeader>
                    <ModalFooter>
                        <Button
                            variant="secondary"
                            disabled={disconnecting}
                            onClick={() => setConfirm(false)}
                        >
                            انصراف
                        </Button>
                        <Button
                            variant="danger"
                            loading={disconnecting}
                            onClick={() => {
                                setDisconnecting(true);
                                router.delete(url, {
                                    data: { confirm: true },
                                    preserveScroll: true,
                                    onSuccess: () => setConfirm(false),
                                    onFinish: () => setDisconnecting(false),
                                });
                            }}
                        >
                            قطع دسترسی
                        </Button>
                    </ModalFooter>
                </ModalContent>
            </Modal>
        </AppLayout>
    );
}
