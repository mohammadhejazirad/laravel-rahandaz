import { destinationForPath } from '@/lib/panel-navigation';
import { Link, usePage } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import type { SharedProps } from '@/types';

export function HelpLink({ slug, label = 'راهنمای این بخش' }: { slug: string; label?: string }) {
    const { app } = usePage<SharedProps>().props;
    return <Link href={`${app.base_path}/tutorials/${slug}`} className="inline-flex min-h-11 items-center gap-2 text-sm text-accent"><BookOpen size={16} aria-hidden="true" />{label}</Link>;
}

export function ContextualHelp({ path }: { path: string }) {
    const { app } = usePage<SharedProps>().props;
    const item = destinationForPath(path, app.base_path);
    return item ? <HelpLink slug={item.guide} /> : null;
}
