import { Link } from '@inertiajs/react';
import { PersianDate } from '@/components/dates/persian-date';
import { EmptyState } from '@/components/ui/empty-state';
import { StatusBadge } from '@/components/ui/status-badge';
import { TableFrame } from '@/components/ui/table-frame';
export interface ListColumn { key: string; label: string; kind?: 'status' | 'date' | 'technical'; }
export type ListRow = { uuid: string } & Record<string, string | number | boolean | null>;
export function ResourceList({ rows, columns, detailBase, label }: { rows: ListRow[]; columns: ListColumn[]; detailBase?: string; label: string }) {
    if (!rows.length) return <EmptyState title="هنوز موردی ثبت نشده است">پس از ثبت داده واقعی، نتیجه اینجا نمایش داده می‌شود.</EmptyState>;
    return <TableFrame label={label}><thead className="bg-surface-muted text-muted"><tr>{columns.map(c => <th key={c.key} className="px-3 py-2 text-start font-medium">{c.label}</th>)}</tr></thead><tbody>{rows.map(row => <tr key={row.uuid} className="border-t border-line">{columns.map((c, i) => {
        const value = row[c.key];
        const text = value === null || value === undefined ? '—' : String(value);
        return <td key={c.key} className="px-3 py-3">{c.kind === 'status' ? <StatusBadge value={text} /> : c.kind === 'date' ? <PersianDate iso={typeof value === 'string' ? value : null} /> : i === 0 && detailBase ? <Link className="inline-flex min-h-11 items-center text-accent" dir={c.kind === 'technical' ? 'ltr' : undefined} href={`${detailBase}/${row.uuid}`}>{text}</Link> : <span dir={c.kind === 'technical' ? 'ltr' : undefined} className={c.kind === 'technical' ? 'font-mono text-xs' : ''}>{text}</span>}</td>;
    })}</tr>)}</tbody></TableFrame>;
}
