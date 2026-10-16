import { Server } from 'lucide-react';
import { cn } from '@/lib/cn';
import type { GitProviderConfig } from './types';

export function GitProviderIcon({ provider, className }: { provider: GitProviderConfig['provider']; className?: string }) {
    return <span aria-hidden="true" className={cn('relative inline-flex size-5 shrink-0 text-ink', className)}>
        <span className="block size-full bg-current" style={{ mask: `url(/icons/git/${provider === 'github' ? 'github' : 'gitlab'}.svg) center / contain no-repeat` }} />
        {provider === 'gitlabSelf' ? <Server className="absolute -bottom-1 -left-1 size-3 rounded bg-surface text-ink" /> : null}
    </span>;
}
