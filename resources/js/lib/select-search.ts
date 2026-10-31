/** Match Persian/Arabic spelling variants and multiple search words. */
export function matchesSelectSearch(label: string, query: string): boolean {
    const normalize = (value: string) => value.normalize('NFKC').toLocaleLowerCase('fa').replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/[\u064b-\u065f\u0670\u0640]/g, '').replace(/\u200c/g, ' ');
    const text = normalize(label);
    return normalize(query).trim().split(/\s+/).every(word => text.includes(word));
}
