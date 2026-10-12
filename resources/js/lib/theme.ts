import type { ThemePreference } from '@/types';

export function resolveTheme(preference: ThemePreference): 'light' | 'dark' {
    if (preference === 'light' || preference === 'dark') {
        return preference;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function applyTheme(preference: ThemePreference): void {
    const root = document.documentElement;
    const resolved = resolveTheme(preference);
    root.setAttribute('data-theme-preference', preference);
    root.setAttribute('data-theme', resolved);
    root.style.colorScheme = resolved;
}
