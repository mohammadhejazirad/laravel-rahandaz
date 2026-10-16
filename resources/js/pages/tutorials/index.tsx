import { Form } from '@/components/ui/form';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Bookmark, BookOpen } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Pagination, type PageMeta } from '@/components/ui/pagination';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';

export interface TutorialSummary {
    slug: string; title: string; summary: string; category: string; tags: string[];
    updated_at: string; reading_minutes: number; level: string; bookmarked: boolean;
}

export default function TutorialsIndex({ tutorials, categories, filters }: {
    tutorials: { data: TutorialSummary[]; meta: PageMeta };
    categories: string[]; filters: { q: string; category: string; bookmarked: boolean };
}) {
    const { app } = usePage<SharedProps>().props;
    const form = useForm({ q: filters.q, category: filters.category, bookmarked: filters.bookmarked ? '1' : '0' });
    return <AppLayout title="آموزش‌ها" description="راهنمای کاربردی قابلیت‌های موجود و محدودیت‌های واقعی راه‌انداز.">
        <Head title="آموزش‌ها" />
        <Card><Form onSubmit={(e) => { e.preventDefault(); form.get(`${app.base_path}/tutorials`, { preserveState: true }); }} className="grid gap-4 md:grid-cols-[2fr_1fr_auto]">
            <Input name="q" label="جست‌وجو در آموزش‌ها" placeholder="عنوان، متن، دسته‌بندی یا مشکل خود را جست‌وجو کنید" value={form.data.q} onChange={(e) => form.setData('q', e.target.value)} maxLength={120} error={form.errors.q} />
            <label className="block text-sm">دسته‌بندی<select name="category" className="mt-1.5 min-h-11 w-full rounded-xl border border-line bg-surface px-3 text-base" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)}><option value="">همه دسته‌ها</option>{categories.map((c) => <option key={c} value={c}>{c}</option>)}</select></label>
            <Button type="submit" loading={form.processing} className="self-end">جست‌وجو</Button>
            <label className="flex min-h-11 items-center gap-2 text-sm md:col-span-3"><input type="checkbox" checked={form.data.bookmarked === '1'} onChange={(e) => form.setData('bookmarked', e.target.checked ? '1' : '0')} />فقط آموزش‌های نشان‌شده</label>
        </Form></Card>
        <div className="flex flex-wrap items-center gap-3 text-sm text-muted"><BookOpen size={18} aria-hidden="true" />{tutorials.meta.total} آموزش در نتیجه<Link className="min-h-11 content-center text-accent" href={`${app.base_path}/tutorials?bookmarked=1`}>آموزش‌های نشان‌شده</Link><Link className="min-h-11 content-center text-accent" href={`${app.base_path}/tutorials`}>همه آموزش‌ها</Link></div>
        {tutorials.data.length === 0 ? <EmptyState title="آموزشی پیدا نشد">عبارت کوتاه‌تر یا دسته دیگری را انتخاب کنید.</EmptyState> : <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{tutorials.data.map((tutorial) => <Card key={tutorial.slug}>
            <div className="flex items-center justify-between gap-2 text-xs text-muted"><Link className="min-h-11 content-center" href={`${app.base_path}/tutorials?category=${encodeURIComponent(tutorial.category)}`}>{tutorial.category}</Link>{tutorial.bookmarked ? <Bookmark size={16} aria-label="نشان‌شده" /> : null}</div>
            <Link href={`${app.base_path}/tutorials/${tutorial.slug}`} className="block py-2 text-lg font-semibold text-accent">{tutorial.title}</Link><p className="text-sm text-muted">{tutorial.summary}</p><p className="mt-3 text-xs text-muted">حدود {tutorial.reading_minutes} دقیقه مطالعه</p>
        </Card>)}</div>}
        <Pagination meta={tutorials.meta} />
    </AppLayout>;
}
