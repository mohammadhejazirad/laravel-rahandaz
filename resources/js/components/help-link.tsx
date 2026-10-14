import { Link, usePage } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import type { SharedProps } from '@/types';

export function HelpLink({ slug, label = 'راهنمای این بخش' }: { slug: string; label?: string }) {
    const { app } = usePage<SharedProps>().props;
    return <Link href={`${app.base_path}/tutorials/${slug}`} className="inline-flex min-h-11 items-center gap-2 text-sm text-accent"><BookOpen size={16} aria-hidden="true" />{label}</Link>;
}

export function ContextualHelp({ path }: { path: string }) {
    const guides: Record<string, string> = {
        dashboard: 'getting-started', setup: 'installation', servers: 'server-management',
        catalog: 'php-runtimes', mirrors: 'troubleshooting', projects: 'projects-environments',
        deployments: 'deployments', domains: 'domains-dns', certificates: 'ssl-certificates',
        databases: 'databases', backups: 'backups', monitoring: 'monitoring',
        jobs: 'jobs-logs', notifications: 'jobs-logs', audit: 'account-security', settings: 'account-security', git: 'github',
    };
    const area = path.split('/').filter(Boolean).find((part) => guides[part]);
    return area ? <HelpLink slug={guides[area]!} /> : null;
}
