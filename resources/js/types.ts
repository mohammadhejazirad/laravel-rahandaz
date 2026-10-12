import type { PageProps } from "@inertiajs/core";

export type ThemePreference = "light" | "dark" | "system";

declare const publicUuidBrand: unique symbol;

export type PublicUuid = string & { readonly [publicUuidBrand]: true };

export interface AuthUser {
    uuid: PublicUuid;
    username: string;
    display_name: string | null;
    role: string | null;
    theme: ThemePreference;
    locale: string;
    timezone: string;
    force_password_change: boolean;
}

export type SharedProps = PageProps & {
    auth: {
        user: AuthUser | null;
    };
    flash: {
        status: string | null;
        error: string | null;
    };
    execution: {
        mode: string;
    };
    app: {
        name: string;
        base_path: string;
    };
    notifications: {
        unread: number;
    };
};

export interface HostFacts {
    observed_at: string;
    compatibility: "untested";
    compatibility_reason: string;
    hostname: string;
    kernel: string;
    architecture: string;
    php: string;
    os: {
        name: string | null;
        version: string | null;
        id: string | null;
        version_codename: string | null;
    } | null;
    memory: {
        total_kib: number | null;
        available_kib: number | null;
    };
    disk: {
        path: string;
        free_bytes: number | null;
        total_bytes: number | null;
    };
    execution_mode: string;
}
