import { createContext } from 'react';

export interface PwaState {
    installed: boolean;
    offline: boolean;
    secure: boolean;
    supported: boolean;
    canPrompt: boolean;
    updateAvailable: boolean;
    busy: boolean;
    error: string | null;
    install: () => Promise<void>;
    update: () => void;
}

export const PwaContext = createContext<PwaState | null>(null);
