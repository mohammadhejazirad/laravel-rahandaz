# مشارکت در راه‌انداز

مرجع الزام‌ها `.docs/PROJECT_SPEC.md` و `AGENTS.md` است؛ وضعیت پیاده‌سازی را در `docs/IMPLEMENTATION_PROGRESS.md` و `docs/V1_FEATURE_INVENTORY.md` بخوانید. مجوز OSI هنوز با تصمیم مالک تعیین نشده؛ مشارکت و انتشار عمومی باید این تصمیم را روشن کند.

تغییر را به یک برش قابل آزمون با مجوز، validation، transaction، audit، UI و مسیر شکست محدود کنید. کد سالم یا تغییرات ثبت‌نشده همکار را حذف نکنید. دامنه از BIGINT داخلی و UUIDv7 عمومی استفاده می‌کند؛ استثناها ADR می‌خواهند.

Host mutation فقط Operations Engine و helper محدود؛ HTTP فرمان طولانی اجرا نمی‌کند. اجرای پیش‌فرض disabled و fail-closed می‌ماند. تست destructive روی Docker Host/سرور واقعی ممنوع؛ Docker test فقط آزمایشگاه تعیین‌شده و production بدون Docker است. Script/installer/systemd جدید قرارداد ایمنی و ماتریس Ubuntu/VM لازم دارد.

پیش از PR:

```bash
php artisan test
vendor/bin/pint --test
npm run lint
npx tsc --noEmit
npm run build
node --experimental-strip-types --test tests/js/datetime.test.ts
bash infrastructure/operations/tests/run.sh
bash installer/tests/run.sh
node tests/security/repository-audit.mjs
```

suite پایگاه فقط `rahandaz_test` را هدف می‌گیرد؛ هرگز روی پایگاه واقعی `migrate:fresh` نزنید. UI فارسی، RTL، روشن/تیره و mobile-first، دارایی برند رسمی و لینک راهنمای واقعی می‌خواهد. صفحه/وابستگی تازه در inventoryها ثبت شود. test اجرا‌نشده را passed معرفی نکنید و screenshot ساختگی را screenshot واقعی ننامید.

گزارش PR باید محدوده کامل/ناقص، دستورهای واقعاً اجراشده، محدودیت محیط، evidence و blockerها را بگوید. گواهی VM، provider یا عملکرد از روی fixture صادر نمی‌شود. گزارش امنیتی حساس را طبق `SECURITY.md` ارسال کنید.
