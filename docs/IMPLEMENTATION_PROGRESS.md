# وضعیت پیاده‌سازی

این فایل گزارش واقعیت مخزن است. نقشه در `.docs/ROADMAP.md` و مشخصات در `.docs/PROJECT_SPEC.md` است.

## مرحله فعلی

مرحله ۰ (هسته) پیاده‌سازی و تست شده است. بخش قابل آزمون مرحله ۱ (ورود، پوسته، probe فقط-خواندنی) هم ساخته شده، ولی بازبینی viewport در مرورگر انجام نشده و بنابراین مرحله ۱ بسته نیست.

اسکیمای PostgreSQL نسخه اول اضافه و روی پایگاه آزمایشی و پایگاه توسعه migrate شده است. رفتار اجرایی مرحله‌های ۲ تا ۶ هنوز ساخته نشده است. `RAHANDAZ_EXECUTION_MODE` برابر `disabled` مانده و runner هنوز هیچ job یا deployment را `succeeded` نمی‌کند.

## انجام‌شده

- Laravel 13.35، Inertia 3، React 19، TypeScript سخت‌گیر، Tailwind 4، Vite.
- PostgreSQL برای حساب، نشست، عملیات و outbox. Redis صف و cache جدا.
- UUIDv7، مالک اولیه، workspace، policy، پاکت رمز، canary، doctor.
- موتور عملیات در حد fail-closed: ثبت، idempotency، outbox، lease بدون تصاحب lease منقضی. هیچ عملیاتی `succeeded` نمی‌شود.
- صفحات واقعی: ورود، تغییر گذرواژه، راه‌اندازی فقط-خواندنی، داشبورد شمارش عملیات، خطای Inertia.
- پوسته روشن/تیره/سیستم، Vazirmatn محلی، RTL، Lucide، Sonner فقط برای flash واقعی.
- اسکیمای نسخه اول: میزبان، کاتالوگ، میرور، Git، پروژه و محیط، deployment، دامنه و Nginx، دیتابیس کاربر، بکاپ، زمان‌بندی، متریک، اعلان پنل، تنظیم، setup، audit و دسترسی. جزئیات در `docs/DATABASE_SCHEMA.md`، `docs/DATABASE_ERD.md` و `docs/DATABASE_SECURITY.md`.
- گاردهای کوچک دامنه: ثبت deployment با قفل محیط، ثبت delivery وب‌هوک بدون payload، بررسی URL عمومی، زنجیره hash حسابرسی، و `CredentialStore::openFor()` با رویداد دسترسی.
- Seeder کاتالوگ بسته با lifecycle `untested` و میرور candidate. کاربر یا توکن نمونه ساخته نمی‌شود.
- ورود موفق `last_login_at` و `last_activity_at` را با `forceFill` می‌نویسد. این ستون‌ها fillable نیستند.

## مانده

- Factoryهای ثانویه هنوز `definition()` خالی دارند. گراف اصلی میزبان، پروژه، محیط، Git، deployment، credential، بسته و میرور factory واقعی دارد.
- job نگهداری متریک و لاگ، چرخش کلید credential، نقش جدا برای migration، RLS، reconciliation لیز، کنترلر webhook، و probe زنده میرور.
- `manage_secrets` برای operator در اسکیما هست و `openFor()` هنوز فقط owner و admin را راه می‌دهد.
- DNS rebinding و SSRF بعد از resolve برای GitLab خودمیزبان.
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
| `XDEBUG_MODE=off php artisan test --compact` | 44 passed، 321 assertions، بعد از اسکیمای نسخه اول |
| `php artisan migrate --force` | forward روی `rahandaz`؛ جدول‌های پایه از ۱۸ به ۷۲ رسید، شامل `migrations`. `migrate:fresh` روی این پایگاه اجرا نشد |
| `vendor/bin/pint` | روی مدل‌ها، دامنه، factory، seeder و تست‌های جدید بدون خطای سبک اجرا شد |
| `php artisan rahandaz:doctor` | در آزمون قبلی هسته خروج ۰ بود؛ بعد از migration دوباره اجرا نشد |

آزمون‌های قبلی پوسته (`tsc`، lint، build) بعد از این تغییر دیتابیس دوباره اجرا نشدند چون UI عوض نشده است. مرورگر در دسترس نبود.

`EXPLAIN (ANALYZE, BUFFERS)`، PHPStan، benchmark رم و CPU، و بازبینی viewport اجرا نشده‌اند.

## محدودیت محیط

PHP این ماشین داخل کانتینر Lerd/Podman است و `127.0.0.1` میزبان را نمی‌بیند. Compose پورت‌ها را روی `127.0.0.1` و `192.0.2.1` (رابط `lerd0`) منتشر می‌کند. `.env` محلی به `192.0.2.1` وصل است. فایل نمونه هنوز `127.0.0.1` را برای PHP روی خود میزبان نشان می‌دهد.

کلید اصلی محلی در `/home/mrhr/.config/rahandaz/master.key` با حالت `0600` است. مقدارش در git و در این گزارش نیست. دیسک پروژه chmod را نگه نمی‌دارد.

کانتینرهای متوقف قدیمی `rahandaz-dev-redis-1` و `rahandaz-dev-db-1` حذف نشده‌اند.

## کار بعدی

صفحه ورود تا راه‌اندازی را در viewportهای ۳۶۰، ۷۶۸، ۱۲۸۰ و ۱۴۴۰ و هر دو پوسته طی کن. بعد از آن، فقط یک انتقال صریح و قابل بازگشت برای `setup_completed_at` طراحی شود که هیچ probe را «سازگار» اعلام نکند.
