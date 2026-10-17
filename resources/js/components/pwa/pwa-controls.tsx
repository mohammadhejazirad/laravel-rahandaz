import { useContext, useState } from 'react';
import { Download, RefreshCw } from 'lucide-react';
import { PwaContext } from './pwa-context';
import { Button } from '@/components/ui/button';
import { Modal, ModalContent, ModalDescription, ModalFooter, ModalHeader, ModalTitle } from '@/components/ui/modal';

export function PwaControls() {
    const pwa = useContext(PwaContext);
    const [dialog, setDialog] = useState<'install' | 'update' | null>(null);
    if (!pwa || (pwa.installed && !pwa.updateAvailable && !pwa.offline && !pwa.error && !pwa.busy)) return null;

    return <section aria-label="نصب و وضعیت برنامه" className="my-3 min-w-0 space-y-2 text-sm">
        <div className="flex flex-wrap items-center gap-2">
            {!pwa.installed && <Button variant="ghost" size="sm" disabled={pwa.busy} onClick={() => { if (pwa.canPrompt) void pwa.install(); else setDialog('install'); }}><Download className="size-4" aria-hidden="true" />نصب برنامه</Button>}
            {pwa.updateAvailable && <Button variant="secondary" size="sm" disabled={pwa.busy} onClick={() => setDialog('update')}><RefreshCw className="size-4" aria-hidden="true" />نسخهٔ تازه آماده است</Button>}
            {pwa.busy && <span role="status" className="text-muted">در حال آماده‌سازی…</span>}
        </div>
        {pwa.offline && <p role="status" className="rounded-lg border border-line bg-surface p-3 text-muted">اتصال شبکه قطع شده است؛ عملیات پنل به ارتباط زنده با سرور نیاز دارند.</p>}
        {pwa.error && <p role="status" className="text-muted">{pwa.error}</p>}
        <Modal open={dialog !== null} onOpenChange={(open) => { if (!open) setDialog(null); }}>
            <ModalContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
                <ModalHeader>
                    <ModalTitle>{dialog === 'update' ? 'به‌روزرسانی برنامه' : 'نصب راه‌انداز روی دستگاه'}</ModalTitle>
                    <ModalDescription>{dialog === 'update' ? 'صفحه دوباره بارگذاری می‌شود. ابتدا تغییرات فرم‌های باز را ذخیره کنید. این کار، به‌روزرسانی نرم‌افزار سرور را اجرا نمی‌کند.' : 'در مرورگرهای پشتیبان، پنل را مانند یک برنامه با پنجرهٔ مستقل باز کنید. ورود امن و اتصال به سرور همچنان لازم است.'}</ModalDescription>
                </ModalHeader>
                {dialog === 'install' && <div className="space-y-3 text-sm leading-7 text-ink">
                    {!pwa.secure ? <p>نصب روی سرور به HTTPS با گواهی معتبر نیاز دارد. اتصال HTTP به IP عمومی برای نصب امن کافی نیست.</p> : !pwa.supported ? <p>این مرورگر از قابلیت‌های لازم پشتیبانی نمی‌کند. از مرورگر به‌روز مانند Chrome، Edge یا Safari استفاده کنید.</p> : <>
                        <p><strong className="text-ink">iPhone و iPad:</strong> در Safari منوی اشتراک‌گذاری را باز کنید و «افزودن به صفحهٔ اصلی» را انتخاب کنید.</p>
                        <p><strong className="text-ink">Android:</strong> در Chrome گزینهٔ «نصب برنامه» یا «افزودن به صفحهٔ اصلی» در منوی مرورگر را بررسی کنید.</p>
                        <p><strong className="text-ink">دسکتاپ:</strong> در Chrome یا Edge از نشان نصب در نوار آدرس یا گزینهٔ نصب در منو استفاده کنید. در نسخه‌های پشتیبان Safari مک، «افزودن به Dock» در دسترس است.</p>
                        <p>اگر گزینهٔ نصب وجود ندارد، مرورگر یا محیط فعلی ممکن است نصب مستقل را پشتیبانی نکند یا برنامه قبلاً نصب شده باشد.</p>
                    </>}
                    <p>اطلاعات خصوصی پنل آفلاین ذخیره نمی‌شوند؛ نصب برنامه امکان اجرای عملیات بدون اتصال را ایجاد نمی‌کند.</p>
                </div>}
                <ModalFooter>
                    <Button variant="secondary" onClick={() => setDialog(null)}>بستن</Button>
                    {dialog === 'update' && <Button disabled={pwa.busy || pwa.offline} onClick={() => { setDialog(null); pwa.update(); }}>ذخیره کرده‌ام؛ بارگذاری دوباره</Button>}
                </ModalFooter>
            </ModalContent>
        </Modal>
    </section>;
}
