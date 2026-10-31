import { GitConnectionSteps } from '@/components/git/connection-steps';
import { Head, Link, usePage } from "@inertiajs/react";
import { Plus, GitFork } from "lucide-react";
import { useState } from "react";
import { GitProviderCard } from '@/components/git/provider-card';
import { GitProviderIcon } from '@/components/git/provider-icon';
import { GitEventLog } from "@/components/git/event-log";
import type {
    GitAccount,
    GitEvents,
    GitProviderConfig,
} from "@/components/git/types";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { AppLayout } from "@/layouts/app-layout";
import type { SharedProps } from "@/types";

import { Modal, ModalContent, ModalHeader, ModalTitle, ModalDescription } from '@/components/ui/modal';

export default function GitIndex({ connections, providers, can_manage, events }: {
    connections: GitAccount[]; providers: GitProviderConfig[]; can_manage: boolean; events: GitEvents | null;
}) {
    const page = usePage<SharedProps>();
    const base = page.props.app.base_path;
    const [adding, setAdding] = useState(false);
    const [chosen, setChosen] = useState<GitProviderConfig['provider'] | null>(null);
    const config = providers.find(provider => provider.provider === chosen);
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
            {!config ? <div className="mt-5 grid gap-3 sm:grid-cols-3">{providers.map(provider => <Button variant="secondary" key={provider.provider} className="flex min-h-32 flex-col items-center justify-center gap-3 rounded-xl border border-line p-4 hover:border-accent hover:bg-surface-muted focus-visible:outline-2 focus-visible:outline-accent" onClick={() => setChosen(provider.provider)}><GitProviderIcon provider={provider.provider} className="size-8" /><span className="font-semibold">{provider.provider === 'github' ? 'GitHub' : provider.provider === 'gitlabSelf' ? 'GitLab شخصی' : 'GitLab.com'}</span><span className="text-xs text-muted">{provider.provider === 'gitlabSelf' ? 'روی سرور خودتان' : 'حساب شخصی یا سازمانی'}</span></Button>)}</div> : <div className="mt-5"><Button variant="secondary" onClick={() => setChosen(null)}>تغییر سرویس</Button><GitProviderCard key={config.provider} config={config} base={base} /></div>}
        </ModalContent></Modal>
    </AppLayout>;
}
