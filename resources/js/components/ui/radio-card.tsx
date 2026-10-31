import { type InputHTMLAttributes, type ReactNode } from 'react';
import { cn } from '@/lib/cn';

type RadioCardProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'required' | 'type' | 'children'> & {
    isRequired?: boolean;
    children: ReactNode;
    layout?: 'row' | 'tile';
};

export function RadioCard({ children, className, checked, disabled, isRequired, layout = 'row', ...props }: RadioCardProps) {
    return <label className={cn(
        'flex min-w-0 cursor-pointer gap-2 rounded-xl border p-3 focus-within:ring-2 focus-within:ring-accent',
        layout === 'tile' ? 'flex-col items-center justify-center text-center' : 'items-start',
        checked ? 'border-accent bg-accent-soft' : 'border-line hover:border-accent',
        disabled && 'cursor-not-allowed opacity-70', className,
    )}>
        <input {...props} type="radio" checked={checked} disabled={disabled} aria-required={isRequired || undefined} className={layout === 'tile' ? 'sr-only' : 'mt-1 size-4 shrink-0 accent-accent'} />
        {children}
    </label>;
}
