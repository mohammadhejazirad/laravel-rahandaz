import { CopyField } from '@/components/ui/copy-field';

export function ExecutablePath({ path, name }: { path: string; name: string }) {
    return <CopyField value={path} label={`مسیر اجرایی تأییدشدهٔ ${name}`} copyLabel="کپی مسیر" />;
}

