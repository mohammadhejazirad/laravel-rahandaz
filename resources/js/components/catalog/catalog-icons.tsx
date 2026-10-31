import {
    Activity, AppWindow, Archive, Braces, CodeXml, Container, Database, DatabaseBackup,
    DatabaseZap, FileCode2, Film, Globe, Grid2X2, Hexagon, Layers,
    Leaf, MemoryStick, Network, Package, Shield, ShieldCheck, ShieldPlus,
    Workflow, Wrench, Zap, type LucideIcon,
} from 'lucide-react';

const categoryIcons: Record<string, LucideIcon> = {
    all: Grid2X2,
    web: Globe,
    runtime: CodeXml,
    database: Database,
    cache: MemoryStick,
    utility: Wrench,
    antivirus: ShieldCheck,
    security: Shield,
    process: Workflow,
};

const softwareIcons: Record<string, LucideIcon> = {
    htop: Activity,
    jq: Braces,
    ffmpeg: Film,
    unzip: Archive,
    nginx: Network,
    php: FileCode2,
    php82: FileCode2,
    php83: FileCode2,
    php85: FileCode2,
    python: CodeXml,
    postgresql: DatabaseZap,
    mysql: Database,
    mariadb: DatabaseBackup,
    redis: Zap,
    memcached: MemoryStick,
    clamav: ShieldCheck,
    'clamav-freshclam': ShieldPlus,
    supervisor: Workflow,
    node: Hexagon,
    docker: Container,
    mongodb: Leaf,
    'google-chrome': AppWindow,
};

/** Decorative SVGs inherit theme colors; the adjacent text provides the accessible name. */
export function CatalogCategoryIcon({ category, className = 'size-4 shrink-0' }: { category: string; className?: string }) {
    const Icon = categoryIcons[category] ?? Layers;
    return <Icon className={className} aria-hidden="true" focusable="false" />;
}

export function CatalogSoftwareIcon({ software, category, className = 'size-5 shrink-0' }: { software: string; category?: string; className?: string }) {
    const Icon = softwareIcons[software] ?? categoryIcons[category ?? ''] ?? Package;
    return <Icon className={className} aria-hidden="true" focusable="false" />;
}
