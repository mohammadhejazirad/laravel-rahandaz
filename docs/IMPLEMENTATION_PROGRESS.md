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
- پوسته روشن/تیره/سیستم، Vazirmatn محلی، RTL، Lucide، React-Toastify برای flash واقعی، خطای فرم و شکست درخواست رابط.
- هویت بصری رسمی: مونوگرام هندسی اختصاصی، lockup فارسی، نسخه‌های روشن/تیره/تک‌رنگ، faviconهای SVG/ICO/PNG، Apple Touch Icon، تصویر Open Graph و کامپوننت‌های مشترک `AppLogo`/`AppLogoSymbol`. جزئیات در `docs/BRAND_GUIDELINES.md`.
- اسکیمای نسخه اول: میزبان، کاتالوگ، میرور، Git، پروژه و محیط، آرشیو منبع پروژه، deployment، دامنه و Nginx، دیتابیس کاربر، بکاپ، زمان‌بندی، متریک، اعلان پنل، تنظیم، setup، audit و دسترسی. جزئیات در `docs/DATABASE_SCHEMA.md`، `docs/DATABASE_ERD.md` و `docs/DATABASE_SECURITY.md`.
- گاردهای کوچک دامنه: ثبت deployment با قفل محیط، ثبت delivery وب‌هوک بدون payload، بررسی URL عمومی، زنجیره hash حسابرسی، و `CredentialStore::openFor()` با رویداد دسترسی.
- Seeder کاتالوگ بسته با lifecycle `untested` و میرور candidate. کاربر یا توکن نمونه ساخته نمی‌شود.
- ورود موفق `last_login_at` و `last_activity_at` را با `forceFill` می‌نویسد. این ستون‌ها fillable نیستند.
- مسیر `GET /rahandaz/projects/{project}` مدل را با `uuid` resolve می‌کند، `id` عددی را در JSON نمی‌گذارد، شناسه غیر v7 را ۴۰۴ می‌کند و عضویت workspace دیگر را ۴۰۳ می‌کند.
- Inertia کاربر و workspace فیلد `uuid` می‌فرستد. نوع عمومی TypeScript برند `PublicUuid` است.
- پوسته‌های واقعی پنل: داشبورد، راه‌اندازی فقط-خواندنی، فهرست سرور محلی، کاتالوگ، میرور، پروژه، آپلود ZIP و ثبت workflow استقرار دستی، عملیات و لاگ ذخیره‌شده، اعلان، گزارش فعالیت برای مالک و مدیر، و تنظیمات نمایه. جزئیات و مسیرهای ساخته‌نشده در `docs/UI_PAGE_INVENTORY.md`. هیچ موفقیت نصب، build یا فعال‌سازی release جعلی اضافه نشده است.
- بازطراحی foundation رابط: برند رسمی مشترک، سطح‌های soft معنایی، AuthLayout دوپنله در desktop، AppLayout با عنوان/شرح مشترک، skip link، Sheet دارای focus trap و بازگردانی فوکوس، breakpoint تبلت روی `lg`، جدول قابل پیمایش با keyboard، loading واقعی فرم و rollback پوسته در خطای ذخیره. shadcn/ui به‌صورت گزینشی با Primitiveهای مستقل Radix برای نیازهای واقعی نصب شد.

## هویت بصری — 2026-10-12

سه مسیر «زیرساخت هندسی»، «مونوگرام فارسی» و «جریان استقرار» مقایسه شدند. طرح نهایی ترکیب مونوگرام «ر» با مسیر کنترل/استقرار است؛ path ضخیم و فضای منفی محدود آن در اندازه‌های ۱۶ تا ۲۵۶ پیکسل بررسی شد. برند قبلی یک آیکون `Terminal` از Lucide بود و به‌عنوان placeholder حذف شد.

assetهای رسمی در `public/branding` هستند. تمام faviconها، Apple Touch Icon و `public/og-image.png` از همان Symbol تولید شده‌اند. `resources/views/app.blade.php` metadata روشن/تیره، Open Graph و fallbackهای مرورگر را دارد. README، layout ورود، سایدبار/Drawer، setup فقط‌خواندنی و صفحه خطا از برند رسمی استفاده می‌کنند. درخواست مستقیم خطا با `resources/views/errors/show.blade.php` و CSS عمومی بدون وابستگی به Vite/JavaScript پاسخ می‌گیرد؛ navigation اینرسی همچنان صفحه React دارد.

`assets/welcome-site/index.html` منبع قالب خوشامد است و کپی آن در `installer/templates/welcome/index.html` است. route فعلی `/` هنوز آن را سرو نمی‌کند. placeholderها می‌مانند تا healthcheck واقعی مقدار بدهد. وجود این فایل به‌معنی موفقیت نصب نیست. پروژه PWA نیست، بنابراین manifest، service worker و iconهای ۱۹۲/۵۱۲ اضافه نشده‌اند.

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
- reconciliation، سوکت root برای helper، فعال‌سازی واقعی systemd، Release امضاشدهٔ نصب‌کننده، و جلالی. درخت `installer/` و قالب unitها در مخزن است و به runner وصل نیست. نصب production تأیید نشده است.
- اندازه‌گیری RAM/CPU. هدف ۵۱۲MiB و ۰.۵ vCPU هنوز benchmark نشده است.
- PHPStan اجرا نشده است.

## منبع ZIP پروژه — 2026-10-13

صفحه جزئیات پروژه اکنون برای Owner/Admin/Operator یک dropzone دسترس‌پذیر، file picker، انتخاب environment، progress آپلود و تاریخچه آرشیوهای امن دارد. Viewer فقط پیام عدم مجوز می‌بیند. مسیر `POST /rahandaz/projects/{project}/environments/{environment}/source-archives` با UUIDv7، FormRequest، Policy، current workspace و scope پروژه/environment محافظت می‌شود.

`ArchiveInspector` بدون extraction فایل را بازبینی می‌کند و Zip Slip، absolute/Windows path، backslash، مسیر تکراری، symlink/reparse/special entry، entry رمزگذاری‌شده، سقف entry، حجم یک entry، مجموع expanded size و compression bomb را رد می‌کند. فایل معتبر با نام UUID در disk غیرعمومی `storage/app/project-archives` می‌ماند. path و BIGINT id در props اینرسی نیست.

جدول `project_source_archives` با BIGINT `id` و UUIDv7، FKهای عددی workspace/project/uploader، SHA-256 و metadata محدود اضافه شد. deployment اکنون `source_type=git|archive` و `source_archive_id` دارد؛ منبع آرشیوی `commit_sha` ندارد و digest آن به `artifact_digest` bind می‌شود. trigger پایگاه archive را به project همان environment محدود می‌کند. digest تکراری در یک پروژه فایل تازه نمی‌سازد.

آپلود موفق یک deployment دستی، `operation_jobs` از نوع `deploy.archive`، outbox و audit می‌سازد. اجرای میزبان همچنان `disabled` است؛ extraction/build/healthcheck/switch انجام نشده و UI موفقیت deployment نشان نمی‌دهد. جزئیات در `docs/PROJECT_ARCHIVE_UPLOADS.md` است.

## تصمیم

`docs/adr/0001-laravel-modular-monolith.md` و برای شناسه `docs/adr/0004-bigint-primary-key-uuidv7-public-identifier.md`. بند شناسه ADR 0001 منسوخ شده و متن تاریخی‌اش مانده است.

## آزمون واقعی — 2026-10-13

| فرمان | نتیجه |
| --- | --- |
| `php artisan test --compact` | خروج ۰؛ ۷۹ آزمون، ۷۸ موفق، یک skip و ۲۷۹۹ assertion روی `rahandaz_test`؛ شامل امنیت و workflow آپلود ZIP و بررسی extensionها در doctor |
| `node --experimental-strip-types --test tests/js/datetime.test.ts` | ۳ آزمون جلالی، از جمله مرز سال کبیسه ۱۴۰۳ |
| `npx tsc --noEmit` و `npm run lint` | خروج ۰ و بدون warning پس از تغییر layout و componentهای مشترک |
| `npm run build` | خروج ۰. chunk مشترک اصلی `373.88KiB` و gzip `116.68KiB`، AppLayout برابر `26.81KiB` و gzip `8.94KiB`، صفحه جزئیات پروژه/آپلود `14.15KiB` و gzip `4.78KiB`، CSS برابر `72.09KiB` و gzip `12.75KiB`، و فونت `111.15KiB` است |
| Chrome headless visual smoke | ورود واقعی در ۳۹۰، ۷۶۸، ۱۲۸۰ و ۱۴۴۰ بدون overflow؛ RTL و Vazirmatn تایید شد. Dropdown پوسته هر سه گزینه روشن، تیره و سیستم را با cursor pointer و انتخاب فعلی نشان داد؛ انتخاب تیره DOM و localStorage را تغییر داد و هیچ exception مرورگر ثبت نشد. صفحه‌های احراز هویت‌شده با نشست واقعی مرور نشدند چون credential معتبر در اختیار این اجرا نبود |
| `npm audit --omit=dev --audit-level=high` | صفر vulnerability |
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

لینک‌های README فقط به فایل‌های موجود می‌روند. در زمان این بازنویسی، سندهای نصب تولید، عملیات، مدل تهدید مستقل، استراتژی استقرار، کارایی، changelog محصول و `SECURITY.md` هنوز نبودند. گزارش بازنگری اسناد در `.docs/CHANGELOG_DOCUMENTATION.md` با changelog محصول یکی نیست. از ۲۰۲۶-۱۰-۱۲ سندهای وضعیت موتور در بخش «موتور عملیات Bash» اضافه شده‌اند. کارایی، changelog محصول و `SECURITY.md` هنوز نیستند.

ابزار markdownlint در محیط نصب نبود. بررسی محلی: یک H1، ۱۴ فنس زوج، لنگرهای فهرست موجود، و هیچ مسیر نسبی شکسته. آزمون تازه برای این ویرایش README اجرا نشد.

## واکنش‌گرایی و Drawer راست — 2026-10-12

منوی موبایل قبلاً با کلاس منطقی `end` در RTL از لبه فیزیکی چپ باز می‌شد. Sheet اکنون `right-0`، `dir="rtl"` و `data-side="right"` دارد و از لبه راست روی محتوا می‌آید. لینک‌ها همچنان یک `Sidebar` هستند. focus trap، Escape، scroll lock و بازگردانی فوکوس را Radix Dialog مدیریت می‌کند. پوسته از `max-w-[1600px]` خارج شد تا سایدبار دسکتاپ به لبه راست بچسبد. زیر ۱۰۲۴px سایدبار ثابت نیست؛ عبور از این عرض Sheet را می‌بندد.

جدول در قاب خودش اسکرول می‌شود. فیلدها ۱۶px هستند. هدف لمسی منو، صفحه‌بندی و دکمه حداقل ۴۴px است. React-Toastify حداکثر سه اعلان، فونت وزیرمتن، RTL و فاصله از Header دارد. نمودار، DatePicker و کامپوننت Dialog عمومی هنوز ساخته نشده‌اند. صفحه‌هایی که در مخزن نیستند، از جمله محیط مستقل، استقرار، Git، دیتابیس، دامنه، SSL، پشتیبان، کاربران و نقش‌ها، ساخته نشدند.

`docs/PROJECT_SPEC.md` و `docs/DEVELOPMENT.md` وجود ندارند. جمله پوسته و Drawer در `.docs/PROJECT_SPEC.md` و `.docs/DEVELOPMENT.md` به‌روز شد.

بازبینی Chrome headless روی بیلد `app-OyE2MnwH.js` و `app-BQrqy3Z8.css`: صفحه ورود واقعی در عرض‌های ۳۲۰ تا ۱۹۲۰ بدون اسکرول افقی بود و فونت فیلد ۱۶px. خطای واقعی ۴۰۴ و پاسخ متنی `/` هم اسکرول افقی نداشتند. گذرواژه فایل اولیه دیگر پذیرفته نمی‌شود، پس صفحه‌های داخل پنل با همان بسته تولید و props نمایشی سنجیده شدند، نه با نشست مالک. در ۳۹۰px کشوی بسته‌شده لبه راست `right=390` و `left=70` داشت؛ در ۳۲۰px عرض ۲۷۲px و چسبیده به راست بود. بستن با بیرون، دکمه، Escape و لینک، بازگشت فوکوس به همبرگر، قفل scroll، پوسته تیره `#111827`، و بسته‌شدن هنگام رسیدن به ۱۲۸۰px دیده شد. سایدبار دسکتاپ در ۱۲۸۰ و ۱۴۴۰ و ۱۹۲۰ به لبه viewport چسبیده بود. در ۱۰۲۴ به‌خاطر نوار اسکرول، لبه با عرض layout (۱۰۰۹) یکی بود. بزرگ‌نمایی ۲۰۰٪ در عرض ۱۲۸۰ اسکرول افقی صفحه نساخت. اندازه‌گیری Toast قدیمی Sonner با برش React-Toastify زیر جایگزین شده است.

## بازطراحی رابط و shadcn/ui گزینشی — 2026-10-13

`components.json` با RTL و سبک `radix-nova` اضافه شد. فقط Primitiveهای دارای مصرف واقعی نصب شدند: Avatar، Dialog برای Sheet و Modal، Dropdown Menu، Separator، Slot و Tooltip. بسته تجمیعی `radix-ui`، Skeleton بدون مصرف، Toast شادسی‌ان، Select و کتابخانه فرم اضافه نشدند. React-Toastify تنها سیستم Toast باقی ماند.

AppLayout اکنون سایدبار گروه‌بندی‌شده، Sheet موبایل، منوی حساب، Avatar، Header فشرده و عرض محتوای کنترل‌شده دارد. ThemeToggle یک trigger آیکونی با Dropdown روشن، تیره و سیستم است؛ ترجیح `system` تغییرات دستگاه را دنبال می‌کند و شکست ذخیره سمت سرور DOM و localStorage را rollback می‌کند. Breadcrumb مشترک در جزئیات سرور، پروژه و عملیات استفاده می‌شود. اعلان‌ها loading واقعی، تمایز خوانده‌نشده و اقدام مشترک دارند؛ setup و profile نیز با داده موجود بازآرایی شدند. نمودار، داده، فیلتر و عملیات ساختگی اضافه نشد.

خطای runtime صفحه پروفایل از ترکیب `Button asChild` با child تهی loading بود. شاخه `asChild` اکنون فقط همان React element را به Radix Slot می‌دهد و SSR مستقیم لینک تغییر گذرواژه بدون exception تایید شد. cursor تعاملی برای لینک‌ها، دکمه‌ها، triggerها و آیتم‌های منو به‌صورت سراسری و در primitiveها اعمال شد.

Modal عمومی با Root، Trigger، Content، Header، Title، Description، Footer و Close اضافه شد. خروج Header دیگر مستقیم درخواست نمی‌فرستد؛ ابتدا تأیید «بله، خارج شو / خیر، بمان» نمایش داده می‌شود. تأیید دارای loading، جلوگیری از ارسال تکراری و Toast شکست است و endpoint یا رفتار backend تغییر نکرد.

## اعلان Toast با React-Toastify — 2026-10-12

Sonner حذف و React-Toastify 11.1.0 نصب شد. `notify` تنها مسیر ارسال Toast است و حالت‌های default، info، warning، success، error، queued، loading/update، dismiss و خطای فرم را پوشش می‌دهد. `FlashToaster` چهار کانال `status`، `error`، `warning` و `info` نشست را می‌خواند. شناسه deterministic مانع نمایش دوباره همان flash زیر React Strict Mode است.

ورود نامعتبر و خطای تغییر گذرواژه، در کنار خطای زیر فیلد، Toast نشان می‌دهند. ورود، خروج، تغییر گذرواژه و خواندن اعلان از flash واقعی backend استفاده می‌کنند. شکست ذخیره پوسته، خروج و خواندن اعلان نیز Toast خطا دارد. پیام `queued` صریحاً موفقیت عملیات میزبان را رد می‌کند.

CSS کتابخانه یک‌بار از `app.css` وارد می‌شود و entrypoint بدون تزریق runtime یعنی `react-toastify/unstyled` مصرف می‌شود. ظاهر از tokenهای معنایی روشن/تیره، وزیرمتن، RTL، border منطقی، progress، close button فارسی ۴۴px، عرض واکنش‌گرا و reduced motion استفاده می‌کند. smoke واقعی Chrome در viewport ۳۹۰px خطای ورود را با `role="alert"`، عرض ۳۶۶px از x=12 تا x=378، فونت Vazirmatn، جهت RTL، دکمه «بستن پیام» ۴۴px و تغییر رنگ زنده با پوسته تیره تایید کرد.

`npm run lint`، `npx tsc --noEmit` و `npm run build` خروج صفر داشتند. build نهایی: chunk اصلی `350.97 KiB` خام و `110.05 KiB` gzip، chunk مشترک Toast/پوسته `21.30 KiB` خام و `8.63 KiB` gzip، CSS برابر `52.31 KiB` خام و `10.21 KiB` gzip، و فونت `111.15 KiB`. آزمون‌های مرتبط `PanelHttpTest` و `PanelPagesTest` با ۲۰ آزمون و ۲۹۶ assertion گذشتند؛ سپس کل suite با ۶۵ آزمون، ۶۴ موفق، یک skip و ۲۶۲۵ assertion خروج صفر داشت.

## برچسب فارسی Enum — 2026-10-12

هر Enum زیر `app/Domain/**/Enums` از `HasEnumLabel` استفاده می‌کند و `label()` مقدار ذخیره‌شده را برای نمایش فارسی می‌کند. مقدار ستون پایگاه و propهای Inertia همان توکن اصلی است (`queued`، `owner`، `light`). فرهنگ لغت مشترک `EnumLabelCatalog` از همین متدها ساخته می‌شود و با `enum_labels` به رابط می‌رسد. `StatusBadge`، نقش کاربر، پوسته ذخیره‌شده، نوع پروژه و گونه سرویس از همین فرهنگ می‌خوانند. اگر یک توکن در چند Enum باشد، متن فارسی آن یکی است تا جستجو با مقدار ذخیره‌شده دو معنی ندهد. نام محصول‌ها، مثل Laravel و PostgreSQL، به فارسی برگردانده نشده‌اند. بیلد رابط `app-DJLPUJ7o.js` است و CSS همان `app-BQrqy3Z8.css` مانده؛ اندازه chunk اصلی و CSS تغییر نکرد.

## موتور عملیات Bash — 2026-10-12

`infrastructure/operations/` نسخه `1.0.0` دارد و ۱۲۱ عملیات در `contracts/registry.json` ثبت شده‌اند. wrapperها نازک هستند و کتابخانه‌های مشترک اعتبارسنجی، قفل، sandbox، JSON و رد secret را انجام می‌دهند. apply زنده بدون sandbox زیر دایرکتوری موقت `LIVE_MUTATION_BLOCKED` است. کاتالوگ Ubuntu برای 22.04 و 24.04 و 26.04 نوشته شده و `verification` آن `untested` است، پس نصب بسته موفق گزارش نمی‌شود.

حساب `rahandaz` در sandbox با `nologin`، بدون sudo، و گذرواژه تصادفی در فایل `0600` ساخته می‌شود و سپس قفل می‌گردد. ورود تعاملی این حساب ساخته نشده است. مشخصات گذرواژه را از ویزارد نمی‌پرسد؛ این برش همان راز را فقط داخل apply سندباکس می‌سازد و در اتوماسیون از آن استفاده نمی‌کند.

`infrastructure/privilege_helper/bin/rahandaz-helper` قرارداد CLI است. سوکت root نصب نشده و فایل sudoers ساخته نشده است. اسکریپت سفارشی از این helper رد می‌شود.

`app/Infrastructure/Operations/` کاتالوگ، گارد ورودی، تجزیه نتیجه و `LocalOperationProcess` را دارد. `OperationDispatcher` پیش از ساخت فرآیند `HostExecutionDenied` با کد `EXECUTION_DISABLED` می‌دهد. `OperationRunner` و `ExecutionGate` تغییر نکرده‌اند. job همچنان موفق نمی‌شود.

آزمون `bash infrastructure/operations/tests/run.sh` خروج ۰ بود: `bash -n`، fixtureهای jammy و noble و resolute با سازگاری untested، رد جهش زنده و کلید خصوصی و reboot و بسته نیازموده، حساب sandbox، Nginx، symlink استقرار، اسکریپت غیر root، و آداپتر PHP روی `/usr/bin/php`. `php artisan test tests/Unit/OperationsEngineTest.php` شش آزمون را گذراند و facts را skip کرد چون runtime لاراول bash ندارد. `shellcheck`، `shfmt`، `bats` و VM ایزوله اجرا نشدند. هیچ عملیات نصبی production-ready اعلام نمی‌شود.

سندهای وضعیت: `docs/OPERATIONS.md`، `docs/OPERATIONS_CATALOG.md`، `docs/BASH_SCRIPT_STANDARDS.md`، `docs/PRIVILEGED_HELPER_CONTRACT.md`، `docs/OPERATIONS_SECURITY.md`، `docs/OPERATIONS_TEST_MATRIX.md`، `docs/CUSTOM_SCRIPT_EXECUTION.md`، `docs/INSTALLATION.md`، `docs/SECURITY_MODEL.md` و `docs/DEPLOYMENT_STRATEGIES.md`. این‌ها جایگزین مشخصات `.docs/PROJECT_SPEC.md` نیستند.

## آزمایشگاه Ubuntu — 2026-10-13

`tests/infrastructure/ubuntu-lab/` موتور `infrastructure/operations/bin/rahandaz-ops` را داخل ایمیج رسمی `ubuntu:22.04`، `ubuntu:24.04` و `ubuntu:26.04` اجرا کرد. production همچنان native است. `RAHANDAZ_OPS_LAB=1` به‌تنهایی روی میزبان توسعه کافی نیست. کاتالوگ lab مقدار `container-lab` دارد و خانه‌های `docs/OPERATIONS_TEST_MATRIX.md` همچنان `untested` هستند.

خروج هر سه `lab-test.sh` برابر ۰ بود. 22.04: ۷۶ passed، ۰ failed، ۸ skipped، ۱۰ unsupported، ۷ requires_vm، ۳ blocked. 24.04 و 26.04: ۷۷ passed، ۰ failed، ۸ skipped، ۹ unsupported، ۷ requires_vm، ۳ blocked. تفاوت 22.04 این است که PHP توزیع 8.1 زیر نیاز پنل 8.3 است و `PHP_TOO_OLD` ثبت شد. 24.04 برابر PHP 8.3 و 26.04 برابر PHP 8.5 است. پنل Laravel و reconciliation صف اجرا نشدند. workflow گیت‌هاب اجرا نشد.

اصلاح موتور که این اجرا نشان داد: کد خروج فرمان در حالت lab دیگر دور ریخته نمی‌شود؛ خطای کاتالوگ پشت «بسته در کاتالوگ نیست» پنهان نمی‌شود؛ مسیر خانهٔ حساب و ریشهٔ deploy وقتی sandbox برابر `/` است اسلش اضافه نمی‌گیرد؛ postgres فقط با traverse روی `/run/rahandaz-lab` به SQL می‌رسد؛ کلید مشاهدهٔ تغییر نقش `material_logged` است تا اسکنر secret نتیجه را رد نکند. کاتالوگ lab به‌خاطر حالت `0777` روی دیسک میزبان، داخل کانتینر با حالت `0644` کپی می‌شود و بررسی world-writable حذف نشده است.

## نصب‌کننده — 2026-10-13

درخت `installer/` اضافه شد. پیاده‌سازی مرحله‌ها در `lib/engine.sh` است. پنل به `OperationRunner` وصل نشد و `RAHANDAZ_EXECUTION_MODE` برابر `disabled` ماند.

`bash installer/tests/run.sh` خروج ۰ داشت: ۶۳ مورد و ۰ شکست. apply آفلاین زیر `/tmp` خروج ۱۰ و `incomplete` بود. کلید اصلی ۳۲ بایت ماند، اجرای دوم همان هش را نگه داشت، خوشامد نوشته نشد، و لاگ هش کلید را نداشت.

`bash installer/tests/container-plan.sh` روی کانتینرهای موجود آزمایشگاه: 22.04 خروج ۱ با `PHP_BASELINE_UNAVAILABLE`؛ 24.04 و 26.04 خروج ۰ با `RESULT plan`. هر سه تصویر تعارض nginx، postgresql، redis-server، php-fpm و php-cli را چاپ کردند و سرویس را حذف نکردند. pid 1 برابر `sleep` بود. بسته‌ای نصب نشد، `/up` زده نشد، مالک ساخته نشد، و صف و scheduler فعال نشدند.

علت طرح اولیهٔ ناموفق این بود که `/etc/os-release` در Ubuntu symlink به `/usr/lib/os-release` است. نصب‌کننده فقط همین هدف را می‌پذیرد. الحاق مسیر وقتی ریشه `/` است دیگر `//usr/lib/os-release` نمی‌سازد.

PHPUnit فیلتر `test_installer_master_key_is_created_once_and_not_printed` اجرا شد و پیش از assertion با رد اتصال به `192.0.2.1:5432` پایگاه `rahandaz_test` ایستاد. وضعیت آن `not_run` است. `migrate:fresh` اجرا نشد.

Mirrorهای APT که InRelease آن‌ها با keyring اوبونتو تأیید شد در `docs/IRANIAN_MIRRORS.md` هستند. Runflare، Megan Hub و `mirror.arvancloud.com` انتخاب نشدند. آزمون ایران فقط fixture بود.

## PHP 8.4 و به‌روزرسانی خود راه‌انداز — 2026-10-14

خود راه‌انداز به PHP 8.4 پین شد. محدودیت Composer از `^8.3` به `^8.4` رسید و فقط `content-hash` در `composer.lock` عوض شد. نسخه بسته‌ها تغییر نکرد. `composer update --lock` به‌خاطر timeout بسته‌های Packagist متوقف شد و هش با `Composer\Package\Locker::getContentHash` نوشته شد. `composer check-platform-reqs` روی `/usr/bin/php` و PHP لرد، هر دو 8.5.11، خروج ۰ داشت. این بررسی `pdo_pgsql` را نمی‌بیند چون در `composer.json` نیست. میزبان `pdo_pgsql` ندارد و PHP لرد دارد. باینری `php8.4` روی میزبان توسعه نیست. `--ignore-platform-reqs` و `config.platform.php` استفاده نشد.

مخزن Ubuntu بسته `php8.4-*` ندارد. pin پذیرفته‌شده فقط `packages.sury.org` با اثرانگشت `15058500A0235D97F5D10063B188E2B695BD4743` است. این کلید، کلید انتشار راه‌انداز نیست. `installer/trust/rahandaz-release.pub` هنوز غایب است. PHP-FPM پنل فایل و سوکت خودش را دارد. `update-alternatives` در طرح نیست.

`bash installer/tests/run.sh` خروج ۰ بود: ۶۹ passed و ۰ failed. داخل آن `installer/tests/update.sh` ۲۱ passed و ۰ failed بود. apply آفلاین خروج ۱۰ و `incomplete` ماند و `current` را عوض نکرد. rollback برنامه فقط symlink است. `--approve-database-recovery` پایگاه را برنمی‌گرداند و `manual_recovery_required` می‌دهد. `pg_dump` در آزمون `not_run` ماند.

کانتینرهای نام‌دار آزمایشگاه Exited بودند. `docker start` به‌خاطر marker انحصاری entrypoint تصویر شکست خورد. منبع entrypoint اصلاح شد تا marker معمولی موجود را نگه دارد، ولی ایمیج دوباره ساخته نشد. طرح روی کانتینر موقت از همان ایمیج‌ها برای 22.04 و 24.04 و 26.04 خروج ۰ و `RESULT plan` داد. آن کانتینرها حذف شدند. بسته‌ای نصب نشد. این گواهی VM نیست. خانه‌های `docs/OPERATIONS_TEST_MATRIX.md` همچنان `untested` هستند. `shellcheck` نصب نبود.

بررسی نسخه در `app/Infrastructure/Updates` است. زمان‌بند `rahandaz:updates:check` هر شش ساعت است. مخزن GitHub خالی یعنی هیچ درخواست HTTP نیست. Login این بررسی را صدا نمی‌زند. جدول `release_checks` migrate نشده است. `tests/Unit/UpdateSystemTest.php` با PHP 8.5.11 میزبان ۱۶ آزمون و ۵۱ assertion را گذراند. درخواست زنده GitHub نبود. آزمون‌های Feature که به `192.0.2.1` وصل می‌شوند `not_run` ماندند. Postgres روشن نشد.

رابط داشبورد و `settings/updates` دستور را نشان می‌دهد و اجرا نمی‌کند. `npm run lint`، `npx tsc --noEmit` و `npm run build` خروج ۰ داشتند. مرورگر باز نشد. پکیج UI تازه نصب نشد.

انتشار عمومی آماده نیست. workflowها اجرا نشدند.

workflowهای `installer-test.yml` و `release-build.yml` اجرا نشدند. کلید اعتماد Release در مخزن نیست. آمادگی انتشار عمومی: آماده نیست.

`docs/PROJECT_SPEC.md`، `docs/ARCHITECTURE.md`، `docs/DEVELOPMENT.md` و `docs/ROADMAP.md` نیستند. نسخهٔ معتبر زیر `.docs/` است.

## کار بعدی

صفحه‌های فهرست و جزئیات داده‌دار را با fixture معتبر در viewportهای مرجع و حالت‌های empty/error مرور کن. بعد از آن، فقط یک انتقال صریح و قابل بازگشت برای `setup_completed_at` طراحی شود که هیچ probe را «سازگار» اعلام نکند. نصب عمومی منتظر کلید اعتماد، artifact امضاشده، healthcheck واقعی و آزمون VM است.
