export interface FrameworkGroup {
    id: string;
    label: string;
}

export interface FrameworkOption {
    id: string;
    name: string;
    description: string;
    icon: string;
    group: string;
}

export const frameworkGroups: readonly FrameworkGroup[] = [
    { id: 'php', label: 'PHP' },
    { id: 'node', label: 'Node.js' },
    { id: 'static', label: 'Static' },
    { id: 'python', label: 'Python' },
    { id: 'wordpress', label: 'WordPress' },
];

/**
 * Stored ids match App\Domain\Projects\Enums\ProjectType.
 * Names stay English. Descriptions stay short Persian copy.
 */
export const frameworkCatalog: readonly FrameworkOption[] = [
    { id: 'php', group: 'php', icon: 'php', name: 'PHP', description: 'برنامهٔ PHP بدون فریمورک' },
    { id: 'laravel', group: 'php', icon: 'laravel', name: 'Laravel', description: 'فریمورک وب PHP' },
    { id: 'node', group: 'node', icon: 'node', name: 'Node.js', description: 'جاوااسکریپت سمت سرور' },
    { id: 'nuxt', group: 'node', icon: 'nuxt', name: 'Nuxt.js', description: 'Vue با رندر سرور' },
    { id: 'next', group: 'node', icon: 'next', name: 'Next.js', description: 'React با رندر سرور' },
    { id: 'vue', group: 'node', icon: 'vue', name: 'Vue.js', description: 'رابط کاربری Vue' },
    { id: 'react', group: 'node', icon: 'react', name: 'React.js', description: 'رابط کاربری React' },
    { id: 'adonis', group: 'node', icon: 'adonis', name: 'AdonisJS', description: 'فریمورک سرور Node' },
    { id: 'express', group: 'node', icon: 'express', name: 'Express.js', description: 'سرور HTTP سبک' },
    { id: 'nest', group: 'node', icon: 'nest', name: 'NestJS', description: 'فریمورک ماژولار Node' },
    { id: 'static', group: 'static', icon: 'html5', name: 'HTML', description: 'فایل‌های ایستا' },
    { id: 'static-react', group: 'static', icon: 'react', name: 'React', description: 'خروجی ایستای React' },
    { id: 'static-vue', group: 'static', icon: 'vue', name: 'Vue', description: 'خروجی ایستای Vue' },
    { id: 'python', group: 'python', icon: 'python', name: 'Python', description: 'برنامهٔ Python' },
    { id: 'django', group: 'python', icon: 'django', name: 'Django', description: 'فریمورک وب Python' },
    { id: 'flask', group: 'python', icon: 'flask', name: 'Flask', description: 'فریمورک سبک Python' },
    { id: 'fastapi', group: 'python', icon: 'fastapi', name: 'FastAPI', description: 'API سریع Python' },
    { id: 'wordpress', group: 'wordpress', icon: 'wordpress', name: 'WordPress', description: 'سایت با وردپرس' },
];
