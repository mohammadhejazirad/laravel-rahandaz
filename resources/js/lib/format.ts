export function formatBytes(bytes: number | null): string {
    if (bytes === null || !Number.isFinite(bytes) || bytes < 0) return '—';
    const units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    const exponent = bytes === 0 ? 0 : Math.max(0, Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1));
    return `${new Intl.NumberFormat('fa-IR', { maximumFractionDigits: exponent === 0 ? 0 : 1 }).format(bytes / 1024 ** exponent)} ${units[exponent]}`;
}
