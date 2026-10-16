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

interface Repository {
    uuid: string;
    full_name: string;
    default_branch: string;
    visibility: string;
    archived: boolean;
    disabled: boolean;
    is_selected: boolean;
}
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
    useVisiblePoll(true, "events,connection", 5000);
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
            description="انتخاب مخزن‌های قابل استفاده در پنل و مشاهدهٔ تاریخچهٔ مرحله‌به‌مرحله."
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
                            loading={syncing}
                            disabled={revoked}
                            onClick={() => {
                                setSyncing(true);
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
            <Button
                variant="secondary"
                onClick={() =>
                    router.reload({ only: ["repositories", "connection"] })
                }
            >
                تازه‌سازی نمایش مخزن‌ها
            </Button>
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

function RepositorySelection({
    repositories,
    canManage,
    url,
}: {
    repositories: Repository[];
    canManage: boolean;
    url: string;
}) {
    const form = useForm({
        visible_uuids: repositories.map((r) => r.uuid),
        selected_uuids: repositories
            .filter((r) => r.is_selected)
            .map((r) => r.uuid),
    });
    return (
        <Card>
            <h2 className="font-semibold">انتخاب مخزن‌های مجاز</h2>
            <p className="mt-2 text-sm leading-7 text-muted">
                مخزن‌های تازه به‌صورت پیش‌فرض مجاز نیستند. تغییرات هر صفحه را
                پیش از رفتن به صفحهٔ بعد ذخیره کنید. پس از تکمیل دریافت، فهرست
                به‌صورت خودکار تازه می‌شود.
            </p>
            {repositories.length ? (
                <Form
                    className="mt-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(`${url}/repositories`, {
                            preserveScroll: true,
                        });
                    }}
                >
                    {canManage ? <div className="mb-4 flex flex-wrap items-center gap-3"><Button variant="secondary" disabled={form.processing} onClick={() => form.setData('selected_uuids', repositories.filter(r => !r.disabled && !r.archived).map(r => r.uuid))}>انتخاب همهٔ مخزن‌های این صفحه</Button><Button variant="secondary" disabled={form.processing} onClick={() => form.setData('selected_uuids', [])}>پاک‌کردن انتخاب‌های این صفحه</Button><span className="text-xs text-muted">{form.data.selected_uuids.length.toLocaleString('fa-IR')} انتخاب</span></div> : null}
                    <div className="space-y-2">
                        {repositories.map((repository) => (
                            <label
                                key={repository.uuid}
                                className="flex min-h-16 min-w-0 cursor-pointer items-center gap-3 rounded-xl border border-line p-3 hover:bg-surface-muted"
                            >
                                <input
                                    type="checkbox"
                                    className="size-5 shrink-0 accent-accent"
                                    disabled={
                                        !canManage ||
                                        repository.archived ||
                                        repository.disabled ||
                                        form.processing
                                    }
                                    checked={form.data.selected_uuids.includes(
                                        repository.uuid,
                                    )}
                                    onChange={(e) =>
                                        form.setData(
                                            "selected_uuids",
                                            e.target.checked
                                                ? [
                                                      ...form.data
                                                          .selected_uuids,
                                                      repository.uuid,
                                                  ]
                                                : form.data.selected_uuids.filter(
                                                      (uuid) =>
                                                          uuid !==
                                                          repository.uuid,
                                                  ),
                                        )
                                    }
                                />
                                <span className="min-w-0 flex-1">
                                    <span
                                        dir="ltr"
                                        className="block break-all font-mono text-sm"
                                    >
                                        {repository.full_name}
                                    </span>
                                    <span className="mt-1 block text-xs text-muted">
                                        {repository.visibility === "private"
                                            ? "خصوصی"
                                            : repository.visibility ===
                                                "internal"
                                              ? "داخلی"
                                              : "عمومی"}{" "}
                                        · شاخهٔ{" "}
                                        <bdi className="font-mono" dir="ltr">
                                            {repository.default_branch}
                                        </bdi>
                                        {repository.archived
                                            ? " · بایگانی‌شده"
                                            : ""}
                                        {repository.disabled
                                            ? " · خارج از دسترسی فعلی سرویس"
                                            : ""}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </div>
                    {Object.values(form.errors).map((error, i) => (
                        <p
                            key={i}
                            role="alert"
                            className="mt-3 text-sm text-danger"
                        >
                            {error}
                        </p>
                    ))}
                    {canManage ? (
                        <div className="sticky bottom-3 mt-4 flex flex-wrap items-center gap-3 rounded-xl border border-line bg-surface-raised p-3"><Button type="submit" loading={form.processing}>ذخیرهٔ دسترسی‌های این صفحه</Button><span role="status" className="text-xs text-muted">{form.isDirty ? 'تغییرات هنوز ذخیره نشده‌اند' : 'انتخاب‌های ذخیره‌شده'}</span></div>
                    ) : null}
                </Form>
            ) : (
                <p className="py-8 text-center text-sm text-muted">
                    هنوز مخزنی دریافت نشده است. وضعیت دریافت را در تاریخچه
                    ببینید؛ در صورت شکست دوباره تلاش کنید.
                </p>
            )}
        </Card>
    );
}
