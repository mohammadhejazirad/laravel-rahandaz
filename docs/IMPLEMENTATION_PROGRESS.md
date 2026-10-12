# وضعیت پیاده‌سازی

این فایل گزارش واقعیت مخزن است. نقشه در `.docs/ROADMAP.md` و مشخصات در `.docs/PROJECT_SPEC.md` است.

## مرحله فعلی

مرحله ۰ (هسته) پیاده‌سازی و تست شده است. بخش قابل آزمون مرحله ۱ (ورود، پوسته، probe فقط-خواندنی) هم ساخته شده، ولی بازبینی viewport در مرورگر انجام نشده و بنابراین مرحله ۱ بسته نیست.

## انجام‌شده

- Laravel 13.35، Inertia 3، React 19، TypeScript سخت‌گیر، Tailwind 4، Vite.
- PostgreSQL برای حساب، نشست، عملیات و outbox. Redis صف و cache جدا.
- UUIDv7، مالک اولیه، workspace، policy، پاکت رمز، canary، doctor.
- موتور عملیات در حد fail-closed: ثبت، idempotency، outbox، lease بدون تصاحب lease منقضی. هیچ عملیاتی `succeeded` نمی‌شود.
- صفحات واقعی: ورود، تغییر گذرواژه، راه‌اندازی فقط-خواندنی، داشبورد شمارش عملیات، خطای Inertia.
- پوسته روشن/تیره/سیستم، Vazirmatn محلی، RTL، Lucide، Sonner فقط برای flash واقعی.

## مانده

- بازبینی چهار viewport و کنتراست در مرورگر.
- قرارداد امن برای علامت‌زدن پایان راه‌اندازی. دکمه موفقیت ساختگی اضافه نشده است.
- reconciliation، helper ممتاز، systemd، installer، میرورها، جلالی.
- اندازه‌گیری RAM/CPU. هدف ۵۱۲MiB و ۰.۵ vCPU هنوز benchmark نشده است.
- PHPStan اجرا نشده است.

## تصمیم

`docs/adr/0001-laravel-modular-monolith.md`

## آزمون واقعی — 2026-10-12

| فرمان | نتیجه |
| --- | --- |
| `XDEBUG_MODE=off php artisan test --compact` | 31 passed، 193 assertions |
| `vendor/bin/pint` | اجرا شد و ۴ فایل را اصلاح کرد؛ بعد از آن تست دوباره سبز بود |
| `npx tsc --noEmit` | بدون خطا |
| `npm run lint` | بدون خطا |
| `npm run build` | موفق؛ CSS حدود ۱۵KiB و JS حدود ۴۳۱KiB |
| `php artisan migrate --force` | روی دیتابیس توسعه `rahandaz` انجام شد |
| `php artisan rahandaz:doctor` | خروج ۰؛ app key، master key، canary، postgres، هر دو redis و حالت `disabled` سالم بودند |

مرورگر در این جلسه در دسترس نبود. رفتار HTTP صفحه‌ها با آزمون Feature دیده شد، نه با کلیک در مرورگر.

## محدودیت محیط

PHP این ماشین داخل کانتینر Lerd/Podman است و `127.0.0.1` میزبان را نمی‌بیند. Compose پورت‌ها را روی `127.0.0.1` و `192.0.2.1` (رابط `lerd0`) منتشر می‌کند. `.env` محلی به `192.0.2.1` وصل است. فایل نمونه هنوز `127.0.0.1` را برای PHP روی خود میزبان نشان می‌دهد.

کلید اصلی محلی در `/home/mrhr/.config/rahandaz/master.key` با حالت `0600` است. مقدارش در git و در این گزارش نیست. دیسک پروژه chmod را نگه نمی‌دارد.

کانتینرهای متوقف قدیمی `rahandaz-dev-redis-1` و `rahandaz-dev-db-1` حذف نشده‌اند.

## کار بعدی

صفحه ورود تا راه‌اندازی را در viewportهای ۳۶۰، ۷۶۸، ۱۲۸۰ و ۱۴۴۰ و هر دو پوسته طی کن. بعد از آن، فقط یک انتقال صریح و قابل بازگشت برای `setup_completed_at` طراحی شود که هیچ probe را «سازگار» اعلام نکند.
