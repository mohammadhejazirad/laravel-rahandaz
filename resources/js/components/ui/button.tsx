import { cva, type VariantProps } from 'class-variance-authority';
import { LoaderCircle } from 'lucide-react';
import type { ButtonHTMLAttributes } from 'react';
import { cn } from '@/lib/cn';

const buttonVariants = cva(
    'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-[color,background-color,border-color,box-shadow,opacity] disabled:pointer-events-none disabled:opacity-55',
    {
        variants: {
            variant: {
                primary: 'bg-accent text-accent-foreground shadow-sm hover:brightness-95',
                secondary: 'border border-line bg-surface text-ink shadow-sm hover:border-accent/35 hover:bg-surface-muted',
                ghost: 'text-muted hover:bg-surface-muted hover:text-ink',
            },
        },
        defaultVariants: {
            variant: 'primary',
        },
    },
);

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & VariantProps<typeof buttonVariants> & {
    loading?: boolean;
    loadingLabel?: string;
};

export function Button({ children, className, variant, type = 'button', loading = false, loadingLabel = 'در حال انجام', disabled, ...props }: ButtonProps) {
    return (
        <button
            type={type}
            className={cn(buttonVariants({ variant }), className)}
            disabled={disabled || loading}
            aria-busy={loading || undefined}
            {...props}
        >
            {loading ? <LoaderCircle className="size-4 animate-spin" aria-hidden="true" /> : null}
            {loading ? loadingLabel : children}
        </button>
    );
}
