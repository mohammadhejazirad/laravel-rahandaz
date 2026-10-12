import { router, usePage } from '@inertiajs/react';
import { MonitorSmartphone, Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { SharedProps, ThemePreference } from '@/types';
import { applyTheme } from '@/lib/theme';

const options: Array<{ value: ThemePreference; label: string; icon: typeof Sun }> = [
    { value: 'light', label: 'روشن', icon: Sun },
    { value: 'dark', label: 'تیره', icon: Moon },
    { value: 'system', label: 'سیستم', icon: MonitorSmartphone },
];

export function ThemeToggle() {
    const page = usePage<SharedProps>();
    const saved = page.props.auth.user?.theme;
    const [current, setCurrent] = useState<ThemePreference>(saved ?? readGuestPreference());

    useEffect(() => {
        if (saved) {
            setCurrent(saved);
        }
    }, [saved]);

    function select(next: ThemePreference) {
        setCurrent(next);
        applyTheme(next);

        if (page.props.auth.user) {
            router.patch(`${page.props.app.base_path}/theme`, { theme: next }, { preserveScroll: true, preserveState: true });
            return;
        }

        localStorage.setItem('rahandaz-theme', next);
    }

    return (
        <div className="inline-flex rounded-lg border border-line bg-surface p-1" role="group" aria-label="پوسته">
            {options.map((option) => {
                const Icon = option.icon;
                const selected = current === option.value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        aria-pressed={selected}
                        className={selected ? 'rounded-md bg-surface-muted px-2.5 py-1 text-ink' : 'rounded-md px-2.5 py-1 text-muted'}
                        onClick={() => select(option.value)}
                    >
                        <Icon className="inline size-4" aria-hidden="true" />
                        <span className="ms-1 text-sm">{option.label}</span>
                    </button>
                );
            })}
        </div>
    );
}

function readGuestPreference(): ThemePreference {
    const value = document.documentElement.getAttribute('data-theme-preference');

    if (value === 'light' || value === 'dark' || value === 'system') {
        return value;
    }

    return 'system';
}
