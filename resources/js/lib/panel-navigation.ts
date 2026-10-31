import { Activity, ArchiveRestore, Bell, BookOpen, Box, Container, Database, FileCode2, FolderGit2, Globe2, LayoutDashboard, ListChecks, Package, RefreshCw, Rocket, ScrollText, Server, Settings, Shield, Users, type LucideIcon } from 'lucide-react';

export interface PanelDestination {
    path: string;
    label: string;
    description: string;
    icon: LucideIcon;
    guide: string;
    managersOnly?: boolean;
    requiresDocker?: boolean;
    section?: string;
}
export interface PanelGroup { label: string; destinations: PanelDestination[] }

export const panelNavigation: PanelGroup[] = [
    { label: 'فضای کاری', destinations: [
        { path: 'dashboard', label: 'داشبورد', description: 'خلاصهٔ فعالیت‌ها و مسیر شروع', icon: LayoutDashboard, guide: 'getting-started', section: 'dashboard' },
        { path: 'projects', label: 'پروژه‌ها', description: 'منبع کد، محیط‌ها و نسخه‌های پروژه', icon: FolderGit2, guide: 'projects-environments', section: 'projects' },
        { path: 'git', label: 'حساب‌های گیت', description: 'اتصال حساب و انتخاب مخزن‌های مجاز', icon: FolderGit2, guide: 'github', section: 'git' },
        { path: 'deployments', label: 'استقرارها', description: 'درخواست‌ها و نتیجهٔ استقرار پروژه‌ها', icon: Rocket, guide: 'deployments', section: 'deployments' },
        { path: 'access', label: 'اعضا و فضای کاری', description: 'ساخت فضای کاری، کاربر و دسترسی بخش‌ها', icon: Users, guide: 'account-security', section: 'members' },
    ] },
    { label: 'میزبان و سرویس‌ها', destinations: [
        { path: 'servers', label: 'میزبان محلی', description: 'مشخصات میزبان همین نصب', icon: Server, guide: 'server-management', section: 'servers' },
        { path: 'servers/php', label: 'PHP سرور', description: 'اندازهٔ آپلود و تنظیم‌های پرکاربرد php.ini', icon: Server, guide: 'php-runtimes', section: 'php' },
        { path: 'catalog', label: 'نرم‌افزارها', description: 'کاتالوگ موتورهای اجرا و سرویس‌ها', icon: Package, guide: 'php-runtimes', section: 'catalog' },
        { path: 'catalog/installed', label: 'نصب‌شده‌ها', description: 'نسخهٔ نصب‌شده و به‌روزرسانی همان شاخه', icon: Package, guide: 'php-runtimes', section: 'catalog' },
        { path: 'domains', label: 'دامنه‌ها', description: 'دامنهٔ محیط‌ها و وضعیت گواهی', icon: Globe2, guide: 'domains-dns', section: 'domains' },
        { path: 'databases', label: 'دیتابیس‌ها', description: 'دیتابیس و کاربر مستقل پروژه', icon: Database, guide: 'databases', section: 'databases' },
        { path: 'monitoring', label: 'پایش', description: 'منابع میزبان و سلامت مشاهده‌شده', icon: Activity, guide: 'monitoring', section: 'monitoring' },
    ] },
    { label: 'عملیات و بازیابی', destinations: [
        { path: 'jobs', label: 'عملیات و لاگ‌ها', description: 'وضعیت درخواست و خروجی مرحله‌ها', icon: ListChecks, guide: 'jobs-logs', section: 'jobs' },
        { path: 'backups', label: 'پشتیبان‌گیری', description: 'نسخه‌های پشتیبان و تاریخچهٔ بازیابی', icon: ArchiveRestore, guide: 'backups', section: 'backups' },
        { path: 'audit', label: 'گزارش فعالیت', description: 'رخدادهای حسابرسی فضای کاری', icon: ScrollText, guide: 'account-security', managersOnly: true, section: 'audit' },
        { path: 'notifications', label: 'اعلان‌ها', description: 'نتیجه‌ها و پیام‌های حساب شما', icon: Bell, guide: 'jobs-logs', section: 'notifications' },
    ] },
    { label: 'تنظیم و راهنما', destinations: [
        { path: 'setup', label: 'راه‌اندازی', description: 'مشخصات اولیه و مسیر شروع استفاده', icon: Rocket, guide: 'installation' },
        { path: 'mirrors', label: 'منابع دریافت بسته', description: 'نشانی‌ها و وضعیت اعتماد میرورها', icon: Shield, guide: 'troubleshooting', section: 'mirrors' },
        { path: 'settings/updates', label: 'به‌روزرسانی', description: 'نسخهٔ راه‌انداز و تاریخچهٔ بررسی', icon: RefreshCw, guide: 'updates', section: 'updates' },
        { path: 'settings/profile', label: 'حساب کاربری', description: 'پوسته، منطقهٔ زمانی و گذرواژه', icon: Settings, guide: 'account-security' },
        { path: 'tutorials', label: 'راهنما و آموزش', description: 'آموزش گام‌به‌گام و رفع مشکل', icon: BookOpen, guide: 'getting-started' },
    ] },
    { label: 'مدیریت Docker', destinations: [
        { path: 'docker', label: 'نمای Docker', description: 'ورود به مدیریت کانتینرها بعد از نصب', icon: Container, guide: 'php-runtimes', requiresDocker: true, section: 'docker' },
        { path: 'docker/apps', label: 'برنامه‌های آماده', description: 'قالب‌هایی مثل RabbitMQ، n8n و Metabase', icon: Box, guide: 'php-runtimes', requiresDocker: true, section: 'docker' },
        { path: 'docker/compose', label: 'Compose و env', description: 'فایل Compose خودتان و متغیرهای env', icon: FileCode2, guide: 'php-runtimes', requiresDocker: true, section: 'docker' },
    ] },
];

export function destinationForPath(path: string, base: string): PanelDestination | undefined {
    const relative = path.split('?')[0]?.replace(/\/$/, '');
    return panelNavigation.flatMap(group => group.destinations)
        .filter(item => relative === `${base}/${item.path}` || relative?.startsWith(`${base}/${item.path}/`))
        .sort((left, right) => right.path.length - left.path.length)[0];
}

export function visibleNavigation(canManage: boolean, dockerInstalled = false, sections: string[] | null = null): PanelGroup[] {
    return panelNavigation.map(group => ({
        ...group,
        destinations: group.destinations.filter(item => (!item.managersOnly || canManage) && (!item.requiresDocker || dockerInstalled) && (!item.section || sections === null || sections.includes(item.section))),
    })).filter(group => group.destinations.length > 0);
}
