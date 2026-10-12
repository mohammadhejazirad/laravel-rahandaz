# وضعیت پیاده‌سازی

این فایل گزارش واقعیت مخزن است. نقشه در `.docs/ROADMAP.md` و مشخصات در `.docs/PROJECT_SPEC.md` است.

## مرحله فعلی

مرحله ۰ (هسته) پیاده‌سازی و تست شده است. بخش قابل آزمون مرحله ۱ (ورود، پوسته، probe فقط-خواندنی) هم ساخته شده است. صفحه ورود و راه‌اندازی در viewportهای مرجع و هر دو پوسته بازبینی شده‌اند، ولی visual regression همه صفحه‌های داده‌دار و Wizard عملیاتی هنوز وجود ندارد و بنابراین مرحله ۱ بسته نیست.

اسکیمای PostgreSQL نسخه اول روی پایگاه توسعه migrate شده و سپس قرارداد شناسه به کلید اصلی `bigint` و شناسه عمومی UUIDv7 تغییر کرده است. رفتار اجرایی مرحله‌های ۲ تا ۶ هنوز ساخته نشده است. `RAHANDAZ_EXECUTION_MODE` برابر `disabled` مانده و runner هنوز هیچ job یا deployment را `succeeded` نمی‌کند.

## انجام‌شده

- Laravel 13.35، Inertia 3، React 19، TypeScript سخت‌گیر، Tailwind 4، Vite.
- PostgreSQL برای حساب، نشست، عملیات و outbox. Redis صف و cache جدا.
- شناسه: `id` از نوع BIGINT برای کلید اصلی و رابطه داخلی، `uuid` از نوع native UUIDv7 برای API و URL. Trait `HasPublicUuid`، trigger `rahandaz_guard_public_uuid`، و cutover در `IdentityCutover`. جزئیات در `docs/adr/0004-bigint-primary-key-uuidv7-public-identifier.md`.
- مالک اولیه، workspace، policy، پاکت رمز، canary، doctor.
- موتور عملیات در حد fail-closed: ثبت، idempotency، outbox، lease بدون تصاحب lease منقضی. هیچ عملیاتی `succeeded` نمی‌شود.
- صفحات واقعی: ورود، تغییر گذرواژه، راه‌اندازی فقط-خواندنی، داشبورد شمارش عملیات، خطای Inertia.
- پوسته روشن/تیره/سیستم، Vazirmatn محلی، RTL، Lucide، Sonner فقط برای flash واقعی.
- هویت بصری رسمی: مونوگرام هندسی اختصاصی، lockup فارسی، نسخه‌های روشن/تیره/تک‌رنگ، faviconهای SVG/ICO/PNG، Apple Touch Icon، تصویر Open Graph و کامپوننت‌های مشترک `AppLogo`/`AppLogoSymbol`. جزئیات در `docs/BRAND_GUIDELINES.md`.
- اسکیمای نسخه اول: میزبان، کاتالوگ، میرور، Git، پروژه و محیط، deployment، دامنه و Nginx، دیتابیس کاربر، بکاپ، زمان‌بندی، متریک، اعلان پنل، تنظیم، setup، audit و دسترسی. جزئیات در `docs/DATABASE_SCHEMA.md`، `docs/DATABASE_ERD.md` و `docs/DATABASE_SECURITY.md`.
- گاردهای کوچک دامنه: ثبت deployment با قفل محیط، ثبت delivery وب‌هوک بدون payload، بررسی URL عمومی، زنجیره hash حسابرسی، و `CredentialStore::openFor()` با رویداد دسترسی.
- Seeder کاتالوگ بسته با lifecycle `untested` و میرور candidate. کاربر یا توکن نمونه ساخته نمی‌شود.
- ورود موفق `last_login_at` و `last_activity_at` را با `forceFill` می‌نویسد. این ستون‌ها fillable نیستند.
- مسیر `GET /rahandaz/projects/{project}` مدل را با `uuid` resolve می‌کند، `id` عددی را در JSON نمی‌گذارد، شناسه غیر v7 را ۴۰۴ می‌کند و عضویت workspace دیگر را ۴۰۳ می‌کند.
- Inertia کاربر و workspace فیلد `uuid` می‌فرستد. نوع عمومی TypeScript برند `PublicUuid` است.
- پوسته‌های واقعی پنل: داشبورد، راه‌اندازی فقط-خواندنی، فهرست سرور محلی، کاتالوگ، میرور، پروژه، عملیات و لاگ ذخیره‌شده، اعلان، گزارش فعالیت برای مالک و مدیر، و تنظیمات نمایه. جزئیات و مسیرهای ساخته‌نشده در `docs/UI_PAGE_INVENTORY.md`. هیچ دکمه نصب، اعمال میرور، استقرار یا لغو جعلی اضافه نشده است.
- بازطراحی foundation رابط: برند رسمی مشترک، سطح‌های soft معنایی، AuthLayout دوپنله در desktop، AppLayout با عنوان/شرح مشترک، skip link، Drawer دارای focus trap و بازگردانی فوکوس، breakpoint تبلت روی `lg`، جدول قابل پیمایش با keyboard، loading واقعی فرم و rollback پوسته در خطای ذخیره. پکیج تازه نصب نشد.

## هویت بصری — 2026-10-12

سه مسیر «زیرساخت هندسی»، «مونوگرام فارسی» و «جریان استقرار» مقایسه شدند. طرح نهایی ترکیب مونوگرام «ر» با مسیر کنترل/استقرار است؛ path ضخیم و فضای منفی محدود آن در اندازه‌های ۱۶ تا ۲۵۶ پیکسل بررسی شد. برند قبلی یک آیکون `Terminal` از Lucide بود و به‌عنوان placeholder حذف شد.

assetهای رسمی در `public/branding` هستند. تمام faviconها، Apple Touch Icon و `public/og-image.png` از همان Symbol تولید شده‌اند. `resources/views/app.blade.php` metadata روشن/تیره، Open Graph و fallbackهای مرورگر را دارد. README، layout ورود، سایدبار/Drawer، setup فقط‌خواندنی و صفحه خطا از برند رسمی استفاده می‌کنند. درخواست مستقیم خطا با `resources/views/errors/show.blade.php` و CSS عمومی بدون وابستگی به Vite/JavaScript پاسخ می‌گیرد؛ navigation اینرسی همچنان صفحه React دارد.

`assets/welcome-site/index.html` فقط منبع template installer آینده است و route یا publish نشده است. متن وضعیت و URL آن placeholder می‌مانند تا installer واقعی پس از موفقیت setup مقداردهی کند؛ وجود این فایل به‌معنی موفقیت نصب نیست. پروژه PWA نیست، بنابراین manifest، service worker و iconهای ۱۹۲/۵۱۲ اضافه نشده‌اند.

`tests/Feature/BrandAssetsTest.php` وجود و غیرخالی‌بودن خروجی‌ها، parsing امن XML، نبود script/event handler/منبع remote، ابعاد PNG، ساختار ICO، metadata Blade و placeholderهای welcome را پوشش می‌دهد. هیچ dependency frontend تازه‌ای نصب نشد.

## شناسه‌ها — 2026-10-12

پایگاه توسعه `rahandaz` پیش از تبدیل فقط ۱۴ ردیف در `migrations` داشت. `users` و `credentials` صفر بودند. migrationهای ایجاد جدول در فایل بازنویسی شدند تا نصب تازه و `rahandaz_test` مستقیم BIGINT بسازند. تاریخچه git amend نشد. `migrate:fresh` روی `rahandaz` اجرا نشد.

`php artisan migrate --force` فقط `2026_10_12_120000` و `2026_10_12_120100` را اعمال کرد. بعد از آن: ۷۲ جدول، ۱۶ ردیف migration، `users.id` برابر `int8`، `users.uuid` برابر native `uuid` و NOT NULL، `sessions.user_id` برابر `int8`، `audit_events.resource_id` هنوز `uuid`، `failed_jobs.uuid` هنوز `varchar`، ۶۴ trigger نگهبان، تابع `rahandaz_guard_public_uuid`، ۱۲۶ کلید خارجی، و همچنان فقط `migrations` غیرخالی. UUID دامنه وجود نداشت که regenerate شود. `down()` این تبدیل استثنا می‌دهد.

آزمون PHPUnit مسیر cutover را اجرا نمی‌کند، چون `RefreshDatabase` اسکیما را از migrationهای بازنویسی‌شده می‌سازد و `120000` آنجا بی‌اثر است. یک dry-run قبلی داخل transaction روی `rahandaz` برگشت داده شد و پیش از migrate واقعی اسکیما را uuid باقی گذاشت. خود migrate واقعی جدا اجرا شد.

AAD پاکت credential از id دهدهی workspace استفاده می‌کند. این تغییر فقط به این دلیل امن بود که شمارش `credentials` پیش از migrate صفر بود.

## مانده

- Factoryهای ثانویه هنوز `definition()` خالی دارند. گراف اصلی میزبان، پروژه، محیط، Git، deployment، credential، بسته و میرور factory واقعی دارد.
- job نگهداری متریک و لاگ، چرخش کلید credential، نقش جدا برای migration، RLS، reconciliation لیز، کنترلر webhook، و probe زنده میرور.
- `manage_secrets` برای operator در اسکیما هست و `openFor()` هنوز فقط owner و admin را راه می‌دهد.
- DNS rebinding و SSRF بعد از resolve برای GitLab خودمیزبان.
- بازبینی تصویری همه صفحه‌های داده‌دار و مسیرهای empty/error در چهار viewport. ورود و راه‌اندازی در ۳۶۰، ۷۶۸، ۱۲۸۰ و ۱۴۴۰ روشن/تیره بازبینی شده‌اند؛ کنتراست به‌صورت اندازه‌گیری‌شده با ابزار WCAG ثبت نشده است.
- Wizard چندمرحله‌ای، Git، دامنه، دیتابیس، پشتیبان، نمودار مانیتورینگ، کاربران و کنسول فقط-خواندنی. این‌ها در منو «آماده نیست» هستند.
- قرارداد امن برای علامت‌زدن پایان راه‌اندازی. دکمه موفقیت ساختگی اضافه نشده است.
- reconciliation، helper ممتاز، systemd، installer، میرورها، جلالی.
- اندازه‌گیری RAM/CPU. هدف ۵۱۲MiB و ۰.۵ vCPU هنوز benchmark نشده است.
- PHPStan اجرا نشده است.

## تصمیم

`docs/adr/0001-laravel-modular-monolith.md` و برای شناسه `docs/adr/0004-bigint-primary-key-uuidv7-public-identifier.md`. بند شناسه ADR 0001 منسوخ شده و متن تاریخی‌اش مانده است.

## آزمون واقعی — 2026-10-12

| فرمان | نتیجه |
| --- | --- |
| `XDEBUG_MODE=off php artisan test --compact --display-warnings` | 55 passed، 979 assertions، روی `rahandaz_test`، شامل آزمون امنیت و ابعاد assetهای برند و fallback خطای بدون JavaScript |
| `node --experimental-strip-types --test tests/js/datetime.test.ts` | ۳ آزمون جلالی، از جمله مرز سال کبیسه ۱۴۰۳ |
| `npx tsc --noEmit` و `npm run lint` | خروج ۰ پس از تغییر layout و componentهای مشترک |
| `npm run build` | خروج ۰. chunk اصلی `app` برابر `350.98KiB` و gzip برابر `110.06KiB`. chunk لوگو `32.57KiB` و gzip `10.92KiB`، فونت وزیرمتن `111.15KiB`، و CSS برابر `33.31KiB` و gzip `6.97KiB`. صفحات جدا code-split شده‌اند |
| Chrome headless visual smoke | ورود و راه‌اندازی واقعی در ۳۶۰، ۷۶۸، ۱۲۸۰ و ۱۴۴۰؛ روشن و تیره، پوسته `system` در ترجیح روشن/تیره، و fallback مستقیم ۴۰۴ در ۳۶۰ روشن و ۱۲۸۰ تیره. overflow، تصویر شکسته یا درخواست ناموفق asset برند مشاهده نشد |
| `php artisan migrate --force` | forward روی `rahandaz`؛ اول جدول‌ها از ۱۸ به ۷۲ رسیدند، بعد `120000` و `120100` همان ۷۲ جدول را به کلید `bigint` بردند. `migrations` اکنون ۱۶ ردیف است. `migrate:fresh` روی این پایگاه اجرا نشد |
| `vendor/bin/pint` | روی مدل‌ها، دامنه، HTTP، migration و تست‌های پنل بدون خطای باقی‌مانده |
| `php artisan rahandaz:doctor` | در آزمون قبلی هسته خروج ۰ بود؛ بعد از migration و cutover دوباره اجرا نشد |

visual regression خودکار و بازبینی همه صفحه‌ها انجام نشده است. `EXPLAIN (ANALYZE, BUFFERS)` اجرا نشد چون ردیف دامنه صفر بود؛ بهبود سرعت ادعا نمی‌شود. PHPStan و benchmark رم و CPU اجرا نشده‌اند.

## محدودیت محیط

PHP این ماشین داخل کانتینر Lerd/Podman است و `127.0.0.1` میزبان را نمی‌بیند. Compose پورت‌ها را روی `127.0.0.1` و `192.0.2.1` (رابط `lerd0`) منتشر می‌کند. `.env` محلی به `192.0.2.1` وصل است. فایل نمونه هنوز `127.0.0.1` را برای PHP روی خود میزبان نشان می‌دهد.

کلید اصلی محلی در `/home/mrhr/.config/rahandaz/master.key` با حالت `0600` است. مقدارش در git و در این گزارش نیست. دیسک پروژه chmod را نگه نمی‌دارد.

کانتینرهای متوقف قدیمی `rahandaz-dev-redis-1` و `rahandaz-dev-db-1` حذف نشده‌اند.

## README — 2026-10-12

`README.md` بازنویسی شد. معرفی، جدول وضعیت قابلیت، معماری هدف، سازگاری Ubuntu، مسیر توسعه، استقرار به‌عنوان طرح، شناسه، امنیت، backup، عیب‌یابی، نقشه راه و مجوز را پوشش می‌دهد. فرمان نصب تولید و نشان License، Release و CI عمداً نیستند: `LICENSE`، `SECURITY.md`، `CHANGELOG.md`، `CONTRIBUTING.md`، `.github`، `scripts/install.sh` و helper ممتاز وجود ندارند. API عمومی Release برای origin گیت `404` برگرداند، پس URL انتشار حدس زده نشد.

در `.docs/DEVELOPMENT.md` دو جمله‌ای که `rahandaz:keys:init` و `rahandaz:bootstrap-owner` را هنوز نساخته می‌دانست، با فرمان‌های واقعی عوض شد.

لینک‌های README فقط به فایل‌های موجود می‌روند. سندهایی که هنوز نیستند و در README لینک نشده‌اند: نصب تولید، عملیات، مدل تهدید مستقل، استراتژی استقرار، کارایی، changelog محصول و `SECURITY.md`. گزارش بازنگری اسناد در `.docs/CHANGELOG_DOCUMENTATION.md` با changelog محصول یکی نیست.

ابزار markdownlint در محیط نصب نبود. بررسی محلی: یک H1، ۱۴ فنس زوج، لنگرهای فهرست موجود، و هیچ مسیر نسبی شکسته. آزمون تازه برای این ویرایش README اجرا نشد.

## واکنش‌گرایی و Drawer راست — 2026-10-12

منوی موبایل قبلاً با کلاس منطقی `end` در RTL از لبه فیزیکی چپ باز می‌شد. Drawer اکنون `right-0`، `dir="rtl"` و `data-side="right"` دارد و از لبه راست روی محتوا می‌آید. لینک‌ها همچنان یک `Sidebar` هستند. Sheet و Radix نصب نشد. پوسته از `max-w-[1600px]` خارج شد تا سایدبار دسکتاپ به لبه راست بچسبد. زیر ۱۰۲۴px سایدبار ثابت نیست؛ عبور از این عرض Drawer را می‌بندد.

جدول در قاب خودش اسکرول می‌شود. فیلدها ۱۶px هستند. هدف لمسی منو، صفحه‌بندی و دکمه حداقل ۴۴px است. Sonner حداکثر سه اعلان، فونت وزیرمتن و فاصله از Header دارد. نمودار، DatePicker، Dialog و React-Toastify هنوز ساخته یا نصب نشده‌اند. صفحه‌هایی که در مخزن نیستند، از جمله محیط مستقل، استقرار، Git، دیتابیس، دامنه، SSL، پشتیبان، کاربران و نقش‌ها، ساخته نشدند.

`docs/PROJECT_SPEC.md` و `docs/DEVELOPMENT.md` وجود ندارند. جمله پوسته و Drawer در `.docs/PROJECT_SPEC.md` و `.docs/DEVELOPMENT.md` به‌روز شد.

بازبینی Chrome headless روی بیلد `app-OyE2MnwH.js` و `app-BQrqy3Z8.css`: صفحه ورود واقعی در عرض‌های ۳۲۰ تا ۱۹۲۰ بدون اسکرول افقی بود و فونت فیلد ۱۶px. خطای واقعی ۴۰۴ و پاسخ متنی `/` هم اسکرول افقی نداشتند. گذرواژه فایل اولیه دیگر پذیرفته نمی‌شود، پس صفحه‌های داخل پنل با همان بسته تولید و props نمایشی سنجیده شدند، نه با نشست مالک. در ۳۹۰px کشوی بسته‌شده لبه راست `right=390` و `left=70` داشت؛ در ۳۲۰px عرض ۲۷۲px و چسبیده به راست بود. بستن با بیرون، دکمه، Escape و لینک، بازگشت فوکوس به همبرگر، قفل scroll، پوسته تیره `#111827`، و بسته‌شدن هنگام رسیدن به ۱۲۸۰px دیده شد. سایدبار دسکتاپ در ۱۲۸۰ و ۱۴۴۰ و ۱۹۲۰ به لبه viewport چسبیده بود. در ۱۰۲۴ به‌خاطر نوار اسکرول، لبه با عرض layout (۱۰۰۹) یکی بود. بزرگ‌نمایی ۲۰۰٪ در عرض ۱۲۸۰ اسکرول افقی صفحه نساخت. اعلان Sonner پس از پایان انیمیشن در ۳۲۰px از x=۱۲ تا x=۳۰۸ ماند.

## برچسب فارسی Enum — 2026-10-12

هر Enum زیر `app/Domain/**/Enums` از `HasEnumLabel` استفاده می‌کند و `label()` مقدار ذخیره‌شده را برای نمایش فارسی می‌کند. مقدار ستون پایگاه و propهای Inertia همان توکن اصلی است (`queued`، `owner`، `light`). فرهنگ لغت مشترک `EnumLabelCatalog` از همین متدها ساخته می‌شود و با `enum_labels` به رابط می‌رسد. `StatusBadge`، نقش کاربر، پوسته ذخیره‌شده، نوع پروژه و گونه سرویس از همین فرهنگ می‌خوانند. اگر یک توکن در چند Enum باشد، متن فارسی آن یکی است تا جستجو با مقدار ذخیره‌شده دو معنی ندهد. نام محصول‌ها، مثل Laravel و PostgreSQL، به فارسی برگردانده نشده‌اند. بیلد رابط `app-DJLPUJ7o.js` است و CSS همان `app-BQrqy3Z8.css` مانده؛ اندازه chunk اصلی و CSS تغییر نکرد.

## کار بعدی

صفحه‌های فهرست و جزئیات داده‌دار را با fixture معتبر در viewportهای مرجع و حالت‌های empty/error مرور کن. بعد از آن، فقط یک انتقال صریح و قابل بازگشت برای `setup_completed_at` طراحی شود که هیچ probe را «سازگار» اعلام نکند.
