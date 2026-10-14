import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { PersianDate } from '@/components/dates/persian-date';
import { TutorialContent } from '@/components/tutorial-content';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';
import type { TutorialSummary } from './index';

export default function TutorialShow({ tutorial, related }: { tutorial: TutorialSummary & { content: string }; related: { slug: string; title: string }[] }) {
    const { app } = usePage<SharedProps>().props;
    const form = useForm({ bookmarked: false });
    return <AppLayout title={tutorial.title} description={tutorial.summary} eyebrow={tutorial.category}>
        <Head title={tutorial.title} />
        <div className="flex flex-wrap items-center gap-3"><Button variant="secondary" asChild><Link href={`${app.base_path}/tutorials`}>بازگشت به آموزش‌ها</Link></Button><Button loading={form.processing} onClick={() => { form.transform(() => ({ bookmarked: !tutorial.bookmarked })); form.put(`${app.base_path}/tutorials/${tutorial.slug}/bookmark`, { preserveScroll: true }); }} aria-pressed={tutorial.bookmarked}>{tutorial.bookmarked ? 'حذف نشان' : 'نشان‌کردن آموزش'}</Button></div>
        <Card><div className="mb-6 flex flex-wrap gap-3 text-xs text-muted"><span>{tutorial.level}</span><span>حدود {tutorial.reading_minutes} دقیقه</span><span>به‌روزرسانی: <PersianDate iso={tutorial.updated_at} /></span></div><TutorialContent content={tutorial.content} /></Card>
        {related.length > 0 ? <Card><h2 className="mb-3 font-semibold">آموزش‌های مرتبط</h2><div className="grid gap-2 md:grid-cols-2">{related.map((row) => <Link key={row.slug} href={`${app.base_path}/tutorials/${row.slug}`} className="min-h-11 content-center text-accent">{row.title}</Link>)}</div></Card> : null}
    </AppLayout>;
}
