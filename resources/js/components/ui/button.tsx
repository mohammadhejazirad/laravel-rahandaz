import { cva, type VariantProps } from 'class-variance-authority';
import type { ButtonHTMLAttributes } from 'react';
import { cn } from '@/lib/cn';

const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition disabled:pointer-events-none disabled:opacity-50',
    {
        variants: {
            variant: {
                primary: 'bg-accent text-accent-foreground hover:opacity-90',
                secondary: 'border border-line bg-surface text-ink hover:bg-surface-muted',
                ghost: 'text-muted hover:bg-surface-muted',
            },
        },
        defaultVariants: {
            variant: 'primary',
        },
    },
);

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & VariantProps<typeof buttonVariants>;

export function Button({ className, variant, type = 'button', ...props }: ButtonProps) {
    return <button type={type} className={cn(buttonVariants({ variant }), className)} {...props} />;
}
