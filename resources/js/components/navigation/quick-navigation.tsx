import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Modal, ModalContent, ModalHeader, ModalTitle, ModalDescription } from '@/components/ui/modal';
import type { PanelGroup } from '@/lib/panel-navigation';

function normalize(value: string) { return value.normalize('NFKC').replaceAll('ي', 'ی').replaceAll('ك', 'ک').replaceAll('\u200c', ' ').toLocaleLowerCase().trim(); }

export function QuickNavigation({ base, groups }: { base: string; groups: PanelGroup[] }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    useEffect(() => {
        const keydown = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault(); setOpen(previous => !previous);
            }
        };
        window.addEventListener('keydown', keydown);
        return () => window.removeEventListener('keydown', keydown);
    }, []);
    const results = groups.flatMap(group => group.destinations).filter(item => normalize(`${item.label} ${item.description}`).includes(normalize(query)));
    return <>
        <Button variant="secondary" onClick={() => { setQuery(''); setOpen(true); }} aria-label="جست‌وجوی بخش‌های پنل" aria-keyshortcuts="Control+k Meta+k" className="flex min-h-11 min-w-11 items-center gap-2 rounded-xl border border-line bg-surface px-3 text-sm text-muted hover:border-accent/40 hover:text-ink">
            <Search className="size-4" aria-hidden="true" /><span className="hidden xl:inline">کدام بخش را می‌خواهید؟</span><kbd dir="ltr" className="hidden rounded border border-line px-1.5 font-mono text-[10px] xl:inline">Ctrl K</kbd>
        </Button>
        <Modal open={open} onOpenChange={setOpen}><ModalContent className="max-h-[85dvh] overflow-y-auto">
            <ModalHeader><ModalTitle>رفتن سریع به بخش‌ها</ModalTitle><ModalDescription>نام بخش یا کاری که می‌خواهید انجام دهید را بنویسید.</ModalDescription></ModalHeader>
            <div className="mt-4"><Input label="جست‌وجوی بخش" name="panel-navigation-search" type="search" value={query} onChange={event => setQuery(event.target.value)} placeholder="مثلاً پروژه، گیت یا پشتیبان" /></div>
            <p className="sr-only" role="status">{results.length.toLocaleString('fa-IR')} بخش پیدا شد</p>
            <ul className="mt-4 space-y-1">{results.map(item => <li key={item.path}><Link href={`${base}/${item.path}`} onClick={() => setOpen(false)} className="flex items-center gap-3 rounded-xl p-3 hover:bg-surface-muted focus-visible:bg-surface-muted"><item.icon className="size-5 shrink-0 text-accent" aria-hidden="true" /><span><span className="block text-sm font-semibold">{item.label}</span><span className="mt-1 block text-xs text-muted">{item.description}</span></span></Link></li>)}</ul>
            {!results.length ? <p className="py-6 text-center text-sm text-muted">بخشی با این عبارت پیدا نشد. عبارت کوتاه‌تری بنویسید.</p> : null}
        </ModalContent></Modal>
    </>;
}
