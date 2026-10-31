const engines = ['mysql', 'mariadb'] as const;

export function secondDatabase(selected: string[], installed: string[]): 'mysql' | 'mariadb' | null {
    const picked = selected.filter((key): key is 'mysql' | 'mariadb' => engines.includes(key as 'mysql' | 'mariadb'));
    const present = installed.filter((key): key is 'mysql' | 'mariadb' => engines.includes(key as 'mysql' | 'mariadb'));
    const incoming = picked.filter(key => !present.includes(key));
    if (picked.length < 2 && present.length === 0) return null;
    if (incoming.length === 0) return null;
    if (present.length === 1 && !picked.includes(present[0]) && picked.length === 1) return picked[0];
    if (present.includes('mysql') && incoming.includes('mariadb')) return 'mariadb';
    if (present.includes('mariadb') && incoming.includes('mysql')) return 'mysql';
    if (picked.length === 2) return 'mariadb';
    return null;
}
