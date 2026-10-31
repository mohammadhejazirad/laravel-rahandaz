import { ChoiceButton } from '@/components/ui/choice-button';
import { RequestButton } from '@/components/ui/request-button';
import { useRequestAction } from '@/hooks/use-request-action';
import { Form } from '@/components/ui/form';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { GitProviderIcon } from '@/components/git/provider-icon';
import type { GitAccount } from '@/components/git/types';
import { FrameworkPicker } from '@/components/projects/framework-picker';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { useVisiblePoll } from '@/hooks/use-visible-poll';
import { AppLayout } from '@/layouts/app-layout';
import { cn } from '@/lib/cn';
import type { SharedProps } from '@/types';

interface HostOption { uuid: string; name: string; slug: string }
interface RepoOption { uuid: string; full_name: string; default_branch: string; is_selected: boolean }
interface BranchOption { name: string; head_sha: string; is_default: boolean }
interface ChosenRepo { uuid: string; full_name: string; default_branch: string; suggested_name: string }

export default function ProjectCreate({
    accounts, hosts, connection, repositories, repository, branches, filters, types, detection, execution_mode,
}: {
    accounts: Pick<GitAccount, 'uuid' | 'provider' | 'account_label' | 'base_url' | 'repository_count' | 'sync_pending'>[];
    hosts: HostOption[];
    connection: string | null;
    repositories: { data: RepoOption[]; truncated: boolean };
    repository: ChosenRepo | null;
    branches: BranchOption[];
    filters: { repo_q: string };
    types: { value: string; label: string }[];
    detection: { type: string | null; label: string | null } | null;
    execution_mode: string;
}) {
    const base = usePage<SharedProps>().props.app.base_path;
    const account = accounts.find(item => item.uuid === connection) ?? null;
    const [branch, setBranch] = useState(branches.find(item => item.is_default)?.name ?? branches[0]?.name ?? '');
    const [loadingRepo, setLoadingRepo] = useState<string | null>(null);
    const [loadingAccount, setLoadingAccount] = useState<string | null>(null);
    const sourceAction = useRequestAction();
    const search = useForm({ connection: connection ?? '', repo_q: filters.repo_q });
    const form = useForm({
        repository: repository?.uuid ?? '',
        branch,
        name: repository?.suggested_name ?? '',
        declared_type: detection?.type ?? '',
        host: hosts.length === 1 ? hosts[0].uuid : '',
    });
    useEffect(() => {
        form.setData('declared_type', detection?.type ?? '');
        // The hint belongs to the repository that was just selected.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [repository?.uuid]);
    const waiting = account !== null && account.repository_count === 0 && repositories.data.length === 0 && filters.repo_q === '';
    useVisiblePoll(waiting || account?.sync_pending === true, 'accounts,repositories', 5000);

    return <AppLayout title="پروژه جدید" description="حساب گیت، مخزن و شاخه را انتخاب کنید. درخواست استقرار ثبت می‌شود و تا فعال‌شدن موتور عملیات روی میزبان اجرا نمی‌شود.">
        <Head title="پروژه جدید" />
        <div className="mb-2 flex flex-wrap items-center justify-between gap-3">
            <ol className="flex flex-wrap gap-2 text-sm">
                {['حساب', 'مخزن', 'شاخه', 'ثبت'].map((label, index) => {
                    const done = index === 0 ? account !== null : index === 1 ? repository !== null : index === 2 ? branch !== '' && repository !== null : false;
                    return <li key={label} className={cn('rounded-full border px-3 py-1', done ? 'border-accent bg-accent-soft text-accent' : 'border-line text-muted')}>{label}</li>;
                })}
            </ol>
            <Button asChild variant="secondary"><Link href={`${base}/projects`}>بازگشت</Link></Button>
        </div>
        {execution_mode === 'disabled' ? <Alert tone="warning">موتور عملیات میزبان خاموش است. با تأیید، فقط پروژه، محیط و درخواست استقرار ذخیره می‌شود.</Alert> : null}

        <Card>
            <h2 className="font-semibold">انتخاب حساب</h2>
            <p className="mt-2 text-sm leading-7 text-muted">چند حساب GitHub، GitLab.com یا GitLab شخصی می‌توانند هم‌زمان وصل باشند. اول حساب را انتخاب کنید.</p>
            {accounts.length === 0 ? <div className="mt-4"><p className="text-sm text-muted">هنوز حسابی وصل نشده است.</p><Button asChild className="mt-3"><Link href={`${base}/git`}>افزودن حساب</Link></Button></div> : <div className="mt-4 flex flex-wrap gap-2">{accounts.map(item => <ChoiceButton key={item.uuid} selected={item.uuid === connection} loading={sourceAction.pending && loadingAccount === item.uuid} loadingLabel="در حال دریافت حساب…" disabled={sourceAction.pending} className={cn('inline-flex min-h-11 max-w-full items-center gap-2 rounded-full border px-3 text-sm', item.uuid === connection ? 'border-accent bg-accent-soft' : 'border-line hover:border-accent')} onClick={() => sourceAction.run(finish => { setLoadingAccount(item.uuid); router.get(`${base}/projects/new`, { connection: item.uuid }, { preserveScroll: true, onFinish: () => { setLoadingAccount(null); finish(); } }); })}><GitProviderIcon provider={item.provider} className="size-4" /><span dir="ltr" className="truncate font-mono">{item.account_label}</span><span className="text-xs text-muted">{item.provider === 'github' ? 'GitHub' : item.provider === 'gitlabSelf' ? 'GitLab شخصی' : 'GitLab'}</span></ChoiceButton>)}<Button asChild variant="secondary"><Link href={`${base}/git`}>افزودن حساب</Link></Button></div>}
        </Card>

        {account ? <Card>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div><h2 className="font-semibold">انتخاب مخزن</h2><p className="mt-2 text-sm leading-7 text-muted">مخزن‌های قابل‌دیدن این حساب. اگر فهرست خالی است، دریافت را بزنید و نتیجه را در تاریخچهٔ حساب ببینید.</p></div>
                <RequestButton variant="secondary" loading={account.sync_pending} loadingLabel="در حال دریافت و بررسی مخزن‌ها…" request={finish => router.post(`${base}/git/connections/${account.uuid}/sync`, {}, { onFinish: finish })}>دریافت مخزن‌ها</RequestButton>
            </div>
            <Form className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={event => { event.preventDefault(); search.transform(data => ({ ...data, connection: connection ?? '' })); search.get(`${base}/projects/new`, { preserveState: true, preserveScroll: true }); }}>
                <div className="min-w-0 flex-1"><Input name="repo_q" label="جست‌وجوی مخزن" dir="ltr" className="font-mono" maxLength={200} value={search.data.repo_q} onChange={event => search.setData('repo_q', event.target.value)} error={search.errors.repo_q} /></div>
                <Button type="submit" variant="secondary" loading={search.processing}>جست‌وجو</Button>
            </Form>
            {repositories.truncated ? <p className="mt-3 text-sm text-muted">بیش از ۲۰۰ مخزن با این جست‌وجو هست. عبارت را دقیق‌تر کنید.</p> : null}
            {repositories.data.length === 0 ? <p className="mt-4 text-sm text-muted">{waiting ? 'فهرست هنوز نرسیده است. دریافت را بزنید؛ این صفحه خودش تازه‌سازی می‌شود.' : 'مخزنی برای نمایش نیست.'}</p> : <div className="mt-4 flex flex-wrap gap-2">{repositories.data.map(item => <ChoiceButton key={item.uuid} selected={item.uuid === repository?.uuid} loading={sourceAction.pending && loadingRepo === item.uuid} loadingLabel="در حال دریافت شاخه‌ها…" disabled={sourceAction.pending} className={cn('inline-flex min-h-11 max-w-full items-center rounded-full border px-3 font-mono text-sm', item.uuid === repository?.uuid ? 'border-accent bg-accent-soft' : 'border-line hover:border-accent')} dir="ltr" onClick={() => sourceAction.run(finish => { setLoadingRepo(item.uuid); router.post(`${base}/projects/sources/${item.uuid}/branches`, {}, { preserveScroll: true, onFinish: () => { setLoadingRepo(null); finish(); } }); })}>{item.full_name}</ChoiceButton>)}</div>}
        </Card> : null}

        {repository && branches.length ? <Card>
            <h2 className="font-semibold">انتخاب شاخه</h2>
            <p dir="ltr" className="mt-2 break-all font-mono text-sm text-muted">{repository.full_name}</p>
            <div className="mt-4 flex flex-wrap gap-2">{branches.map(item => <ChoiceButton key={item.name} selected={branch === item.name} className={cn('inline-flex min-h-11 items-center rounded-full border px-3 font-mono text-sm', branch === item.name ? 'border-accent bg-accent-soft' : 'border-line hover:border-accent')} dir="ltr" onClick={() => { setBranch(item.name); form.setData('branch', item.name); }}>{item.name}</ChoiceButton>)}</div>
            <Form className="mt-6 grid gap-4" onSubmit={event => { event.preventDefault(); form.transform(data => ({ ...data, branch })); form.post(`${base}/projects`); }}>
                <Input name="name" label="نام پروژه" isRequired maxLength={120} value={form.data.name} onChange={event => form.setData('name', event.target.value)} error={form.errors.name} />
                <FrameworkPicker name="declared_type" label="نوع برنامه" isRequired suggested={detection?.type ?? undefined} value={form.data.declared_type} allowed={types.map(type => type.value)} onChange={value => form.setData('declared_type', value)} error={form.errors.declared_type} />
                {detection?.type ? <p className="text-sm leading-7 text-muted">از فایل‌های ریشهٔ شاخهٔ پیش‌فرض، <bdi dir="ltr" className="font-mono">{detection.label}</bdi> پیشنهاد شد. اگر درست نیست، نوع دیگری را انتخاب کنید. زیرپوشه‌ها در این تشخیص دیده نمی‌شوند.</p> : <p className="text-sm leading-7 text-muted">از فایل‌های ریشهٔ شاخهٔ پیش‌فرض نوعی تشخیص داده نشد. خودتان انتخاب کنید.</p>}
                {hosts.length === 0 ? <Alert tone="warning">در این فضای کاری سروری ثبت نشده است. تا وجود یک سرور، پروژه ذخیره نمی‌شود.</Alert> : hosts.length === 1 ? <p className="text-sm text-muted">سرور: <span dir="ltr" className="font-mono">{hosts[0].name}</span></p> : <Select searchable name="host" label="سرور" isRequired value={form.data.host} onChange={event => form.setData('host', event.target.value)} error={form.errors.host}><option value="">انتخاب سرور</option>{hosts.map(host => <option key={host.uuid} value={host.uuid}>{host.name}</option>)}</Select>}
                <input type="hidden" name="repository" value={repository.uuid} />
                <input type="hidden" name="branch" value={branch} />
                {hosts.length === 1 ? <input type="hidden" name="host" value={hosts[0].uuid} /> : null}
                <div className="flex flex-wrap gap-3"><Button type="submit" loading={form.processing} disabled={hosts.length === 0 || branch === ''}>ثبت درخواست استقرار</Button></div>
                {form.errors.repository || form.errors.branch || form.errors.host ? <p role="alert" className="text-sm text-danger">{form.errors.repository || form.errors.branch || form.errors.host}</p> : null}
            </Form>
        </Card> : repository ? <Card><h2 className="font-semibold">انتخاب شاخه</h2><p className="mt-2 text-sm text-muted">شاخه‌ای از این مخزن ذخیره نشده است. مخزن را دوباره انتخاب کنید.</p></Card> : null}
    </AppLayout>;
}
