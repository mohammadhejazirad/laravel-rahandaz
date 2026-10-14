# گزارش آزمون واقعی — ۲۰۲۶-۱۰-۱۴

## محیط و محدوده

PHP توسعه 8.5.11 با pdo_pgsql/Redis/Sodium در محیط Lerd موجود؛ PostgreSQL 16 و دو Redis توسعه. suite فقط `rahandaz_test` را هدف گرفت. DB جدا و تازه `rahandaz_v1_ui_20261014` برای Chrome از migrationهای پروژه ساخته شد؛ به دیتابیس کاربر migrate:fresh زده نشد. اجرای میزبان disabled و هیچ سایت، سرویس خارجی، Git repository یا runtime کاربر تغییر نکرد. این محیط گواهی PHP 8.4/Ubuntu VM نیست.

| آزمون | نتیجه واقعی | محدوده |
| --- | --- | --- |
| `php artisan test --compact` | ۱۱۴ passed، ۱ skipped، ۰ failed؛ ۳۲۳۵ assertion، ۵٫۸۰ ثانیه | Unit/Feature واقعی PG/Redis؛ یک facts تست به‌علت نبود bash در PHP container skip شد |
| `npm run lint` | خروج ۰ | همه TSXهای رابط |
| `npx tsc --noEmit` | خروج ۰ | TypeScript strict |
| `npm run build` | خروج ۰ | CSS 72.66kB / 12.89 gzip؛ بزرگ‌ترین shared JS 373.88kB / 116.68 gzip |
| `vendor/bin/pint --dirty` | خروج ۰، formatting اصلاح شد | PHP تغییرکرده، شامل فایل‌های جدید |
| Node datetime suite | ۱ test file passed | تاریخ فارسی/zone؛ assertionهای داخلی فایل |
| operations Bash harness | خروج ۰ | syntax/input/secret/protected/root/SSH/reboot/Nginx rollback/sandbox؛ نه میزبان واقعی |
| installer Bash harness | ۶۹ passed، ۰ failed | fixture/plan/idempotency/private keys/resume/signature |
| updater زیرمجموعه harness | ۲۱ passed، ۰ failed | در شمار installer دوباره مستقل جمع نشود؛ live DB recovery ندارد |
| CLI `--help/version/status` | خروج ۰ | `channel development` و `php_binary not_installed` درست نمایش داده شد |
| Chrome E2E | ۳۷۸ viewport/theme checks passed، ۰ runtime exception | ۲۱ مسیر authenticated، search/detail/bookmark/refresh/list، راست‌بودن drawer و Escape |
| `npm audit --audit-level=high` | found 0 vulnerabilities | پاسخ advisory آنلاین؛ snapshot همان روز |
| `composer audit --locked` | blocked، خروج ۱۰۰ | timeout Packagist؛ نتیجه پاک اعلام نمی‌شود |
| repository pattern scan | بدون high-confidence match | ۲۰ commit محلی reachable و ۸۴۶ فایل در scan نهایی؛ ignored/remote/unreachable و secret عمومی ناشناخته خارج پوشش |
| GitHub CI | not_run | workflow PHP 8.4 توسعه یافت، اجرای remote تأیید نشده |

## اصلاحات حاصل از آزمون

- NotificationLevel مقدار `danger` می‌خواهد؛ `error` باعث rollback تراکنش worker و پنج شکست suite بود. اصلاح و full suite دوباره گذشت.
- Header با عنوان بلند سرور در 320px کنترل‌ها را از viewport بیرون می‌برد؛ flex sizing مشترک اصلاح و ماتریس کامل تکرار شد.
- یک credential فعال با fingerprint برابر، duplicate password را رد می‌کند؛ خطای آن قبلاً به‌اشتباه duplicate database name معرفی می‌شد. خطا اکنون روی password و بدون secret است. تست‌های چهار engine با رمز مستقل اجرا شدند.
- approval restore قبلاً metadata را artifact_verified می‌نامید؛ اکنون تا بررسی فایل و plan/Recovery بسته است. fixture موفق بدون فایل واقعی نمی‌تواند approval یا Job ایجاد کند؛ آزمون آن گذشت.
- polling و transform با قرارداد Inertia/TypeScript اصلاح شدند؛ رمز فعال دیتابیس بدون تغییر واقعی میزبان overwrite نمی‌شود و rollback ناسازگار قابل اقدام نیست.

## E2E و تصاویر

اسکریپت‌های `tests/e2e/{seed.php,server.php,responsive.mjs}` Backend واقعی را استفاده می‌کنند؛ screenshotها mock props نیستند. built assets مستقل از public/hot کاربر مصرف شد. مرورگر test-only روی loopback به سرور ایزوله متصل بود. تصاویر هفت صفحه در `docs/screenshots/` پس از بازبینی بدون secret به WebP تبدیل شدند. summary در `docs/evidence/v1-browser.json` است. widths: 320/360/390/430/768/1024/1280/1440/1920، light/dark؛ drawer.right=390 در عرض 390، عرض حدود 335.39. نبود اسکرول افقی جای contrast/focus/full accessibility audit نیست.

سناریوی ۱۱ آموزش از login تا bookmark با HTTP/PG واقعی گذشت؛ حذف نشان نیز Feature test دارد. سناریوی ۱ نصب تا Owner در VM، سناریوهای ۲ تا ۱۰ runtime/provider/deploy/SSL/DB/restore/alert/update/reboot **not_run/requires_vm** هستند. درخواست Job و شکست fail-closed گواهی پایان آن سناریوها نیست. E2E فرم database/domain و جزئیات غیرخالی آن‌ها در Chrome هنوز پوشش کامل ندارد.

## Ubuntu / VM

آزمون‌های تاریخی container/plan در `UBUNTU_COMPATIBILITY.md` و ماتریس‌های installer/operations موجودند؛ در این برش نصب واقعی بسته، ACME، provider زنده، محدودیت شبکه ایران یا VM boot اجرا نشد. برای هر سه 22.04/24.04/26.04: نصب کامل/Update/Boot/Recovery = requires_vm؛ supported تولید اعلام نمی‌شود. reboot/shutdown روی میزبان توسعه انجام نشد.

## Benchmark

زمان suite و حجم bundle اندازه‌گیری ابزارند، نه benchmark VPS. ۳۰ دقیقه idle، ۱۵ دقیقه polling، PSS/RSS، CPU average، p95، deploy/backup peak و disk رسمی **not_run**؛ هدف ≤512MiB / ≤0.5vCPU تأیید نشده است.

Composer validate خروج صفر با warning نبود LICENSE داشت. platform check در PHP توسعه با pdo_pgsql/redis/sodium/zip موفق بود؛ git داخل container warning ownership داد، safe.directory سراسری تغییر نکرد و platform output خروج صفر داشت. نبود PHP 8.4 با platform check 8.5 پوشانده نمی‌شود.
