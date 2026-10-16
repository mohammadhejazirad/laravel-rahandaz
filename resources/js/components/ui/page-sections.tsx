import { useEffect, useState, type ReactNode } from 'react';

export interface PageSection { id: string; label: string; content: ReactNode }
/** Real in-page links support history, direct links and keyboards, without extra requests. */
export function PageSections({ sections, label }: { sections: PageSection[]; label: string }) {
    const [active, setActive] = useState(sections[0]?.id ?? '');
    useEffect(() => {
        const sync = () => { const id = window.location.hash.slice(1); if (sections.some(section => section.id === id)) setActive(id); };
        sync(); window.addEventListener('hashchange', sync);
        return () => window.removeEventListener('hashchange', sync);
    }, [sections]);
    return <div className="space-y-5"><nav aria-label={label} className="flex flex-wrap gap-2 border-b border-line pb-3">{sections.map(section => <a key={section.id} href={`#${section.id}`} aria-current={active === section.id ? 'location' : undefined} onClick={() => setActive(section.id)} className={`inline-flex min-h-11 items-center rounded-xl px-4 text-sm font-medium ${active === section.id ? 'bg-accent-soft text-accent' : 'text-muted hover:bg-surface-muted'}`}>{section.label}</a>)}</nav>{sections.map(section => <section key={section.id} id={section.id} hidden={active !== section.id} aria-label={section.label} className="scroll-mt-24 space-y-5">{section.content}</section>)}</div>;
}
