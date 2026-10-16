import type { PageMeta } from '@/components/ui/pagination';

export interface GitAccount {
    uuid: string;
    provider: 'github' | 'gitlab' | 'gitlabSelf';
    account_label: string;
    base_url: string;
    state: string;
    auth_method: string;
    repository_count: number;
    selected_count: number;
    last_synced_at: string | null;
    expires_at: string | null;
}
export interface GitEvents {
    data: { uuid: string; action: string; result: string; recorded_at: string; repository_name: string | null; repository_count: number | null; page: number | null; account_label: string | null; provider: string | null }[];
    meta: PageMeta;
}
export interface GitProviderConfig {
    provider: 'github' | 'gitlab' | 'gitlabSelf';
    configured: boolean;
    client_id: string;
    app_slug: string;
    callback_url: string;
    base_url: string;
}
