export interface SoftwarePackageResult {
    package: string; version: string | null; verified: boolean; changed: boolean;
    status: string; phase?: string; phase_label?: string; message?: string | null; executable_path?: string;
}
export interface SoftwareJob {
    uuid: string; status: string; operation?: string; message: string | null;
    packages: SoftwarePackageResult[];
    summary?: { total: number; succeeded: number; failed: number; skipped: number };
}
export interface CatalogItem {
    name: string; description: string; category?: string;
    product?: string | null; product_name?: string | null; product_description?: string | null; version_label?: string | null;
    version_rank?: number | null;
    versions?: { id: string; label: string; rank: number; packaged: boolean }[] | null;
    version?: string | null;
    upgradeable?: boolean; upgrade_note?: string | null;
}
export interface SoftwareState {
    available: boolean; can_request?: boolean;
    catalog: Record<string, CatalogItem>;
    categories?: Record<string, string>;
    inventory?: { checked_at: string; packages: Record<string, { state: 'installed' | 'not_installed' | 'unknown'; version: string | null }> } | null;
    installed?: SoftwarePackageResult[];
    job?: SoftwareJob | null; active?: SoftwareJob | null; request_key: string;
}
