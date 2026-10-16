import { useForm } from '@inertiajs/react';
import { Form } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import type { GitProviderConfig } from './types';

export function ManualGitConnection({ config, base }: { config: GitProviderConfig; base: string }) {
    const form = useForm({ access_token: '', base_url: config.base_url, mode: 'manual' });
    return <details className="mt-4 rounded-xl border border-line p-3">
        <summary className="min-h-11 cursor-pointer text-sm font-medium">اتصال دستی با توکن</summary>
        <p className="my-3 text-sm leading-7 text-muted">توکن محدود با مجوز خواندن هویت و مخزن‌ها وارد کنید. تست، توکن را ذخیره نمی‌کند؛ هنگام اتصال، توکن رمزگذاری می‌شود. برای GitHub توکن Fine-grained با مخزن‌های انتخابی ترجیح دارد.</p>
        <Form className="space-y-4" onSubmit={event => {
            event.preventDefault();
            const save = (event.nativeEvent as SubmitEvent).submitter?.getAttribute('value') === 'save';
            form.post(`${base}/git/providers/${config.provider}/${save ? 'manual' : 'test'}`, { preserveScroll: true, onSuccess: () => { if (save) form.reset('access_token'); }, onError: () => form.reset('access_token') });
        }}>
            {config.provider === 'gitlabSelf' ? <Input label="نشانی سرور GitLab شخصی" name={`${config.provider}-manual-url`} type="url" isRequired dir="ltr" className="font-mono" value={form.data.base_url} onChange={e => form.setData('base_url', e.target.value)} error={form.errors.base_url} /> : null}
            <Input label="توکن دسترسی" name={`${config.provider}-access-token`} type="password" autoComplete="off" isRequired minLength={8} maxLength={255} dir="ltr" className="font-mono" value={form.data.access_token} onChange={e => form.setData('access_token', e.target.value)} error={form.errors.access_token} />
            <div className="flex flex-wrap gap-2"><Button type="submit" value="test" variant="secondary" disabled={form.processing}>تست اتصال دستی</Button><Button type="submit" value="save" loading={form.processing}>اتصال و ذخیرهٔ توکن</Button></div>
        </Form>
    </details>;
}
