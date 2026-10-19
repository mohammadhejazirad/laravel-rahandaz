import { cn } from '@/lib/cn';

export function FrameworkIcon({ icon, className }: { icon: string; className?: string }) {
    const mask = `url(/icons/frameworks/${icon}.svg) center / contain no-repeat`;

    return (
        <span aria-hidden="true" className={cn('inline-flex size-8 shrink-0 text-ink', className)}>
            <span className="block size-full bg-current" style={{ mask, WebkitMask: mask }} />
        </span>
    );
}
