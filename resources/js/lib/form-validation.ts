export interface FieldRules { value: string; isRequired?: boolean; type?: string; minLength?: number; maxLength?: number; pattern?: string }
export function validateField({ value, isRequired, type, minLength, maxLength, pattern }: FieldRules): string | undefined {
    if (!value.trim()) return isRequired ? 'این فیلد را تکمیل کنید.' : undefined;
    if (type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'نشانی ایمیل معتبر وارد کنید.';
    if (type === 'url') {
        try { const url = new URL(value); if (!['https:', 'http:'].includes(url.protocol)) return 'نشانی معتبر وارد کنید.'; } catch { return 'نشانی معتبر وارد کنید.'; }
    }
    if (minLength && minLength > 0 && value.length < minLength) return `حداقل ${minLength.toLocaleString('fa-IR')} نویسه وارد کنید.`;
    if (maxLength && maxLength > 0 && value.length > maxLength) return `حداکثر ${maxLength.toLocaleString('fa-IR')} نویسه مجاز است.`;
    if (pattern && !new RegExp(`^(?:${pattern})$`).test(value)) return 'مقدار واردشده با قالب مجاز مطابقت ندارد.';
    return undefined;
}
