import { Link } from '@inertiajs/react';
import { destinationForPath, panelNavigation } from '@/lib/panel-navigation';

export function SectionNavigation({ path, base, canManage, dockerInstalled = false }: { path: string; base: string; canManage: boolean; dockerInstalled?: boolean }) {
    const current = destinationForPath(path, base);
    if (!current) return null;
    const group = panelNavigation.find(group => group.destinations.includes(current));
    if (!group) return null;
    const items = group.destinations.filter(item => (!item.managersOnly || canManage) && (!item.requiresDocker || dockerInstalled));
    if (items.length === 0) return null;
    return <nav aria-label="بخش‌های مرتبط" className="flex flex-wrap gap-1 rounded-xl border border-line bg-surface p-1.5">
        {items.map(item => <Link key={item.path} href={`${base}/${item.path}`} aria-current={current.path === item.path ? 'page' : undefined} className={`inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-xs font-medium sm:text-sm ${current.path === item.path ? 'bg-accent-soft text-accent' : 'text-muted hover:bg-surface-muted hover:text-ink'}`}><item.icon className="size-4 shrink-0" aria-hidden="true" />{item.label}</Link>)}
    </nav>;
}
