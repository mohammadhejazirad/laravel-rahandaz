import { Children, cloneElement, isValidElement, type ReactElement, type ReactNode, type HTMLAttributes } from 'react';

type ElementProps = HTMLAttributes<HTMLElement> & { children?: ReactNode; 'data-label'?: string };
function element(node: ReactNode): node is ReactElement<ElementProps> { return isValidElement<ElementProps>(node); }

export function TableFrame({ children, label = 'جدول داده' }: { children: ReactNode; label?: string }) {
    const parts = Children.toArray(children);
    const head = parts.find(node => element(node) && node.type === 'thead');
    const headers = element(head) ? Children.toArray(head.props.children).flatMap(row => element(row) ? Children.toArray(row.props.children).map(cell => element(cell) ? cell.props.children : null) : []) : [];
    const rows = parts.map(part => {
        if (!element(part) || part.type !== 'tbody') return part;
        return cloneElement(part, { role: 'rowgroup' }, Children.map(part.props.children, row => {
            if (!element(row)) return row;
            return cloneElement(row, { role: 'row' }, Children.map(row.props.children, (cell, index) => {
                if (!element(cell)) return cell;
                return cloneElement(cell, { role: 'cell' }, <><span className="mobile-cell-label" aria-hidden="true">{headers[index]}</span><div className="table-cell-value">{cell.props.children}</div></>);
            }));
        }));
    });
    return <div data-slot="table-container" className="resource-table w-full min-w-0 max-w-full rounded-2xl border border-line bg-surface">
        <table role="table" aria-label={label} data-slot="table" className="w-full border-collapse text-sm"><caption className="sr-only">{label}</caption>{rows}</table>
    </div>;
}
