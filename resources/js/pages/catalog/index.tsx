import { Head } from '@inertiajs/react';
import { SoftwareInstallationCard } from '@/components/catalog/software-installation-card';
import type { SoftwareState } from '@/components/catalog/software-picker';
import { AppLayout } from '@/layouts/app-layout';

export default function CatalogIndex({ software }: { software: SoftwareState }) {
    return <AppLayout title="نرم‌افزارها" description="نرم‌افزارها را بر اساس دسته‌بندی انتخاب کنید و نتیجهٔ واقعی نصب هر مورد را ببینید."><Head title="نرم‌افزارها" /><SoftwareInstallationCard software={software} /></AppLayout>;
}
