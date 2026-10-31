import { useId, useState } from 'react';
import { Search } from 'lucide-react';
import { CatalogCategoryIcon } from './catalog-icons';
import { SoftwareVersionCard } from './software-version-card';
import type { CatalogItem, SoftwareState, SoftwarePackageResult } from './software-types';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/cn';

export type { CatalogItem, SoftwareJob, SoftwarePackageResult, SoftwareState } from './software-types';
export { ExecutablePath } from './executable-path';
export { SoftwareProgress } from './software-progress';

const defaultCategories: Record<string, string> = { web: 'وب‌سرورها', runtime: 'زبان‌ها و runtimeها', database: 'دیتابیس‌ها', cache: 'کش', utility: 'ابزارهای کاربردی', antivirus: 'آنتی‌ویروس‌ها', security: 'ابزارهای امنیتی', process: 'مدیریت پردازش' };
export function SoftwarePicker({ catalog, categories = defaultCategories, selected, onChange, disabled, inventory, installed = [], initialCategory }: {
    catalog: SoftwareState['catalog']; categories?: Record<string, string>; selected: string[]; onChange: (keys: string[]) => void; disabled?: boolean;
    inventory?: SoftwareState['inventory']; installed?: SoftwarePackageResult[]; initialCategory?: string;
}) {
    const id = useId();
    const [category, setCategory] = useState(initialCategory && initialCategory in categories ? initialCategory : 'all');
    const [search, setSearch] = useState('');
    const groups = Object.entries(categories).filter(([key]) => Object.values(catalog).some(item => (item.category ?? 'utility') === key));
    const rows = rowsFor(Object.entries(catalog).filter(([, item]) => (category === 'all' || (item.category ?? 'utility') === category) && `${item.name} ${item.product_name ?? ''} ${item.description}`.toLowerCase().includes(search.trim().toLowerCase())));
    const tabs = [['all', 'همهٔ نرم‌افزارها'], ...groups];
    return <div className="space-y-5">
        <div className="flex flex-wrap gap-2 rounded-2xl bg-surface-muted p-2" role="tablist" aria-label="دسته‌بندی نرم‌افزارها">
            {tabs.map(([key, label], index) => <Button variant="ghost" key={key} id={`${id}-tab-${key}`} type="button" role="tab" aria-selected={category === key} aria-controls={`${id}-panel`} tabIndex={category === key ? 0 : -1} onClick={() => setCategory(key)} onKeyDown={event => {
                const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : event.key === 'ArrowLeft' ? (index + 1) % tabs.length : event.key === 'ArrowRight' ? (index - 1 + tabs.length) % tabs.length : null;
                if (next === null) return;
                event.preventDefault(); setCategory(tabs[next][0]); document.getElementById(`${id}-tab-${tabs[next][0]}`)?.focus();
            }} className={cn('inline-flex min-h-11 items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium transition-colors', category === key ? 'bg-surface text-accent shadow-sm' : 'text-muted hover:bg-surface/60 hover:text-ink')}><CatalogCategoryIcon category={key} />{label}</Button>)}
        </div>
        <div className="relative max-w-md"><Search aria-hidden="true" className="pointer-events-none absolute start-3 top-10 size-4 text-muted" /><Input label="جست‌وجوی نرم‌افزار" id={`${id}-search`} value={search} onChange={event => setSearch(event.target.value)} className="ps-10" onKeyDown={event => { if (event.key === 'Enter') event.preventDefault(); }} placeholder="نام نرم‌افزار یا کاربرد آن…" /></div>
        <div id={`${id}-panel`} role="tabpanel" aria-labelledby={`${id}-tab-${category}`} tabIndex={0} className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {rows.map(row => <SoftwareVersionCard key={row.product} items={row.items} selected={selected} onChange={onChange} disabled={disabled} inventory={inventory} installed={installed} />)}
            {rows.length === 0 && <p className="col-span-full rounded-xl border border-dashed border-line p-8 text-center text-sm text-muted">نرم‌افزاری با این جست‌وجو پیدا نشد.</p>}
        </div>
    </div>;
}
function rowsFor(items: [string, CatalogItem][]) {
    const rows = new Map<string, [string, CatalogItem][]>();
    for (const [key, item] of items) {
        const product = item.product ?? key;
        rows.set(product, [...(rows.get(product) ?? []), [key, item]]);
    }
    return Array.from(rows, ([product, items]) => ({ product, items }));
}
