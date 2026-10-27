import { Activity, ArchiveRestore, Bell, BookOpen, Database, FolderGit2, Globe2, LayoutDashboard, ListChecks, Package, RefreshCw, Rocket, ScrollText, Server, Settings, Shield, type LucideIcon } from 'lucide-react';

export interface PanelDestination {
    path: string;
    label: string;
    description: string;
    icon: LucideIcon;
    guide: string;
    managersOnly?: boolean;
}
export interface PanelGroup { label: string; destinations: PanelDestination[] }

export const panelNavigation: PanelGroup[] = [
    { label: 'فضای کاری', destinations: [
        { path: 'dashboard', label: 'داشبورد', description: 'خلاصهٔ فعالیت‌ها و مسیر شروع', icon: LayoutDashboard, guide: 'getting-started' },
        { path: 'projects', label: 'پروژه‌ها', description: 'منبع کد، محیط‌ها و نسخه‌های پروژه', icon: FolderGit2, guide: 'projects-environments' },
        { path: 'git', label: 'حساب‌های گیت', description: 'اتصال حساب و انتخاب مخزن‌های مجاز', icon: FolderGit2, guide: 'github' },
        { path: 'deployments', label: 'استقرارها', description: 'درخواست‌ها و نتیجهٔ استقرار پروژه‌ها', icon: Rocket, guide: 'deployments' },
    ] },
    { label: 'میزبان و سرویس‌ها', destinations: [
        { path: 'servers', label: 'میزبان محلی', description: 'مشخصات میزبان همین نصب', icon: Server, guide: 'server-management' },
        { path: 'catalog', label: 'نرم‌افزارها', description: 'کاتالوگ موتورهای اجرا و سرویس‌ها', icon: Package, guide: 'php-runtimes' },
        { path: 'catalog/installed', label: 'نصب‌شده‌ها', description: 'نسخهٔ نصب‌شده و به‌روزرسانی همان شاخه', icon: Package, guide: 'php-runtimes' },
        { path: 'domains', label: 'دامنه‌ها', description: 'دامنهٔ محیط‌ها و وضعیت گواهی', icon: Globe2, guide: 'domains-dns' },
        { path: 'databases', label: 'دیتابیس‌ها', description: 'دیتابیس و کاربر مستقل پروژه', icon: Database, guide: 'databases' },
        { path: 'monitoring', label: 'پایش', description: 'منابع میزبان و سلامت مشاهده‌شده', icon: Activity, guide: 'monitoring' },
    ] },
    { label: 'عملیات و بازیابی', destinations: [
        { path: 'jobs', label: 'عملیات و لاگ‌ها', description: 'وضعیت درخواست و خروجی مرحله‌ها', icon: ListChecks, guide: 'jobs-logs' },
        { path: 'backups', label: 'پشتیبان‌گیری', description: 'نسخه‌های پشتیبان و تاریخچهٔ بازیابی', icon: ArchiveRestore, guide: 'backups' },
        { path: 'audit', label: 'گزارش فعالیت', description: 'رخدادهای حسابرسی فضای کاری', icon: ScrollText, guide: 'account-security', managersOnly: true },
        { path: 'notifications', label: 'اعلان‌ها', description: 'نتیجه‌ها و پیام‌های حساب شما', icon: Bell, guide: 'jobs-logs' },
    ] },
    { label: 'تنظیم و راهنما', destinations: [
        { path: 'setup', label: 'راه‌اندازی', description: 'مشخصات اولیه و مسیر شروع استفاده', icon: Rocket, guide: 'installation' },
        { path: 'mirrors', label: 'منابع دریافت بسته', description: 'نشانی‌ها و وضعیت اعتماد میرورها', icon: Shield, guide: 'troubleshooting' },
        { path: 'settings/updates', label: 'به‌روزرسانی', description: 'نسخهٔ راه‌انداز و تاریخچهٔ بررسی', icon: RefreshCw, guide: 'updates' },
        { path: 'settings/profile', label: 'حساب کاربری', description: 'پوسته، منطقهٔ زمانی و گذرواژه', icon: Settings, guide: 'account-security' },
        { path: 'tutorials', label: 'راهنما و آموزش', description: 'آموزش گام‌به‌گام و رفع مشکل', icon: BookOpen, guide: 'getting-started' },
    ] },
];

export function destinationForPath(path: string, base: string): PanelDestination | undefined {
    const relative = path.split('?')[0]?.replace(/\/$/, '');
    return panelNavigation.flatMap(group => group.destinations)
        .filter(item => relative === `${base}/${item.path}` || relative?.startsWith(`${base}/${item.path}/`))
        .sort((left, right) => right.path.length - left.path.length)[0];
}

export function visibleNavigation(canManage: boolean): PanelGroup[] {
    return panelNavigation.map(group => ({ ...group, destinations: group.destinations.filter(item => !item.managersOnly || canManage) }));
}
