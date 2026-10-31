import { Button, type ButtonProps } from './button';
import { cn } from '@/lib/cn';

export function ChoiceButton({ selected, className, ...props }: Omit<ButtonProps, 'asChild'> & { selected: boolean }) {
    return <Button variant="secondary" {...props} type="button" aria-pressed={selected} className={cn(
        'max-w-full rounded-full shadow-none',
        selected && 'border-accent bg-accent-soft text-accent',
        className,
    )} />;
}
