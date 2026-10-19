# وضعیت جاری بازچینی صفحه‌ها — ۲۰۲۶-۱۰-۱۶

قرارداد همهٔ مسیرهای فعلی و حالت‌ها در [UX_SYSTEM.md](UX_SYSTEM.md) آمده است؛ بندهای پایین گزارش تاریخی‌اند. همهٔ صفحات پنل پوستهٔ راست ثابت، navigation واحد، جست‌وجوی بخش، راهنمای زمینه‌ای و ناوبری مرتبط دارند. همهٔ جدول‌های TableFrame/ResourceList به کارت موبایل تبدیل می‌شوند. صفحات ورود/گذرواژه پوستهٔ ورود مشترک را دارند؛ صفحهٔ خطا مسیر بازیابی متناسب دارد.

داشبورد دادهٔ واقعی اخیر دارد. پروژه‌ها جست‌وجوی Backend و کارت دارند؛ جزئیات پروژه/حساب/نسخه بخش‌بندی شده‌اند. ZIP قبل از ارسال بازبینی دارد. فرم‌های دامنه/دیتابیس/استقرار/پشتیبان جمع‌شونده‌اند؛ لاگ Job فعال polling دارد؛ اعلان‌ها فیلتر همان صفحه و پایش واحد خوانا دارد. مسیر جدید یا dependency تازه برای این برش اضافه نشد. آزمون بصری با مرورگر متصل و آزمون کامل تمام حالت‌ها هنوز گواهی نشده‌اند.

# موجودی صفحات

پیشوند واقعی پنل `/rahandaz` است، نه مسیرهای با اسلش پایانی فصل ۱۶. `docs/PROJECT_SPEC.md` در ریشه `docs/` وجود ندارد. مشخصات در `.docs/PROJECT_SPEC.md` است.

صفحه عملیاتی فقط جایی است که داده یا عمل از backend واقعی می‌آید و نتیجه جعلی نشان نمی‌دهد. «آماده نیست» در منو لینک مرده است.

| مسیر سند | مسیر واقعی | صفحه | Layout | وضعیت |
| --- | --- | --- | --- | --- |
| `/` | `/` | پاسخ متنی که ورود پنل را نشان می‌دهد | هیچ | template برندشده در `assets/welcome-site/index.html` وجود دارد ولی تا installer و setup موفق publish یا route نشده است |
| `/auth/login/` | `GET/POST /rahandaz/login` | `pages/auth/login.tsx` | Auth | واقعی. ثبت‌نام عمومی نیست |
| تغییر گذرواژه اول | `GET/PUT /rahandaz/password` | `pages/auth/password.tsx` | Auth | واقعی |
| خطا | رندر خطای اینرسی یا Blade مستقیم | `pages/errors/show.tsx` و `views/errors/show.blade.php` | مستقل | ۴۰۳، ۴۰۴، ۴۱۹، ۵۰۰، ۵۰۳؛ پاسخ مستقیم بدون JavaScript قابل استفاده است |
| `/setup/` | `GET /rahandaz/setup` | `pages/setup/index.tsx` | App | فقط probe خواندنی برای مالک. stepper و نصب نیست |
| `/setup/progress/` | — | — | — | آماده نیست |
| `/dashboard/` | `GET /rahandaz/dashboard` | `pages/dashboard/index.tsx` | App | شمارش واقعی job، پروژه، سرور و نمونه متریک. نمودار نیست |
| `/servers/` | `GET /rahandaz/servers` | `pages/servers/index.tsx` | App | فهرست `local_hosts` همان workspace |
| `/servers/<uuid>/overview/` | `GET /rahandaz/servers/{uuid}` | `pages/servers/show.tsx` | App | فقط فیلد ذخیره‌شده. پایش وصل نیست |
| monitoring، network، settings، console، ssh-keys، services، logs، events، databases، php سرور | — | — | — | آماده نیست. کنسول آزاد ساخته نشده |
| `/catalog/` | `GET /rahandaz/catalog` | `pages/catalog/index.tsx` | App | خواندنی. نصب نیست |
| `/mirrors/` | `GET /rahandaz/mirrors` | `pages/mirrors/index.tsx` | App | خواندنی. apply و rollback نیست |
| `/mirrors/<software_kind>/` | — | — | — | آماده نیست |
| `/services/<uuid>/` | — | — | — | آماده نیست |
| `/projects/` و `/projects/new/` | `GET /rahandaz/projects` و `GET /rahandaz/projects/new` | `pages/projects/index.tsx` و `pages/projects/create.tsx` | App | فهرست واقعی. ساخت برای مالک/مدیر: حساب، مخزن، شاخه و ثبت درخواست استقرار. clone میزبان نیست |
| `/projects/<uuid>/` | `GET /rahandaz/projects/{uuid}` | `pages/projects/show.tsx` | App | مرور پروژه/محیط، انتخاب environment، dropzone ZIP و تاریخچه آرشیو/عملیات. `Accept: application/json` همان قرارداد شناسه را JSON می‌دهد |
| upload منبع ZIP | `POST /rahandaz/projects/{project}/environments/{environment}/source-archives` | `components/projects/archive-upload.tsx` | App | واقعی برای Owner/Admin/Operator؛ private store + inspect + deployment/job/outbox/audit. extraction/build آماده نیست |
| settings پروژه، صفحه مستقل environment | — | — | — | آماده نیست. ساخت اولیه در `/projects/new` است |
| `/jobs/` و `/jobs/<uuid>/` | `GET /rahandaz/jobs` و `/{uuid}` | `pages/jobs/index.tsx` و `show.tsx` | App | وضعیت و لاگ ذخیره‌شده، صفحه‌بندی. لغو و polling نیست |
| استقرارها | `GET/POST /rahandaz/deployments` | `pages/deployments/index.tsx` | App | فهرست/فیلتر و درخواست SHA ثابت با Job؛ executor نیست |
| جزئیات استقرار | `GET /rahandaz/deployments/{uuid}` و `POST /{uuid}/{retry|cancel}` | `pages/deployments/show.tsx` | App | وضعیت DB، retry ثابت، لغو پیش از شروع، polling صفحه فعال؛ rollback بسته |
| دامنه‌ها | `GET/POST /rahandaz/domains` | `pages/domains/index.tsx` | App | درخواست دامنه/Nginx؛ اعمال میزبان نیست |
| جزئیات دامنه | `GET /rahandaz/domains/{uuid}` و `POST /{uuid}/certificate` | `pages/domains/show.tsx` | App | درخواست certificate؛ DNS/ACME تأیید نشده |
| دیتابیس‌ها | `GET/POST /rahandaz/databases` | `pages/databases/index.tsx` | App | درخواست DB/user با encrypted credential؛ SQL executor نیست |
| جزئیات دیتابیس | `GET /rahandaz/databases/{uuid}` | `pages/databases/show.tsx` | App | observed state/timestamp، بدون password/secret |
| پشتیبان‌گیری | `GET/POST /rahandaz/backups` | `pages/backups/index.tsx` | App | ردیف artifact و ثبت job؛ artifact جعلی ساخته نمی‌شود |
| جزئیات پشتیبان‌گیری | `GET /rahandaz/backups/{uuid}` | `pages/backups/show.tsx` | App | checksum/retention/history؛ restore/download/delete بسته |
| زمان‌بندی پشتیبان‌گیری | `GET /rahandaz/backups/schedules` | `pages/backups/schedules.tsx` | App | جدول فقط خواندنی؛ اجرای backup schedule نیست |
| پایش | `GET /rahandaz/monitoring` | `pages/monitoring/index.tsx` | App | samples/services/alerts ذخیره‌شده؛ collector/alert engine نیست |
| آموزش‌ها / جست‌وجو / دسته / نشان‌شده | `GET /rahandaz/tutorials?q=&category=&bookmarked=&page=` | `pages/tutorials/index.tsx` | App | content repository، pagination و query shareable |
| جزئیات و نشان آموزش | `GET /rahandaz/tutorials/{slug}` و `PUT /{slug}/bookmark` | `pages/tutorials/show.tsx` | App | bookmark خصوصی PostgreSQL و متن بدون HTML خام |
| `/users/` | — | — | — | آماده نیست |
| `/audit/` | `GET /rahandaz/audit` | `pages/audit/index.tsx` | App | فقط مالک و مدیر. بدون metadata و بدون export |
| `/notifications/` | `GET /rahandaz/notifications` | `pages/notifications/index.tsx` | App | فهرست گیرنده. خواندن تک‌مورد و همه واقعی است |
| `/settings/profile/` | `GET /rahandaz/settings/profile` | `pages/settings/profile.tsx` | App | نمایش timezone، locale، پوسته. MFA نیست |
| `/settings/updates/` | `GET /rahandaz/settings/updates` و `POST /rahandaz/settings/updates/check` | `pages/settings/updates.tsx` | App | وضعیت نسخه از backend. دکمه بررسی، کپی دستور CLI و تاریخچه. پنل فرمان را اجرا نمی‌کند. پکیج UI تازه ندارد |
| members، system، route | — | — | — | آماده نیست |

مجوز: عضویت workspace برای صفحه‌های پنل. upload/deploy پروژه برای `owner`، `admin` و `operator` است و Viewer رد می‌شود. گزارش فعالیت فقط `owner` و `admin`. اعلان فقط `recipient` همان workspace. شناسه عددی و path آرشیو در props این صفحات نیست.

کنترل پوسته در AuthLayout و AppLayout از کامپوننت مشترک `components/theme-toggle.tsx` استفاده می‌کند. هر کلیک چرخهٔ روشن ← تیره ← سیستم ← روشن را پیش می‌برد؛ آیکون وضعیت فعلی و Tooltip و برچسب دسترس‌پذیر وضعیت فعلی و اقدام بعدی را نشان می‌دهند. انتخاب مهمان محلی و انتخاب کاربر واردشده در Backend ذخیره می‌شود؛ شکست ذخیره انتخاب قبلی را برمی‌گرداند.

آزمون‌های Backend: PanelPages، ProjectArchiveUpload، PanelHttp، BrandAssets، V1FeatureWorkflow و TutorialTest. در ۲۰۲۶-۱۰-۱۴، ۱۲ صفحه جدید بالا اضافه شد. Chrome واقعی با نشست و PG ایزوله، ۲۱ مسیر × ۹ عرض × دو پوسته (۳۷۸ بررسی) بدون overflow گذشت؛ Header با عنوان بلند در 320px اصلاح شد. drawer.right=390 و Escape تأیید شد. پوشش تمام حالت‌های غیرخالی/خطای فرم و visual regression جامع نداریم. `evidence/v1-browser.json` و `V1_TEST_REPORT.md` مرجع اجرای آخرند. ResourceGrant جامع هنوز blocker است؛ workspace scoping کافی برای انتشار نیست.

## حساب‌های GitHub/GitLab — ۲۰۲۶-۱۰-۱۶

- `/git`، `git/index`: تنظیم GitHub App و GitLab OAuth، اتصال حساب، فهرست اتصال‌های مجاز طبق Workspace/ResourceGrant و تاریخچهٔ صفحه‌بندی‌شدهٔ عملیات Git برای مدیر. راز برنامه هرگز در props بازگردانده نمی‌شود.
- `/git/connections/{uuid}`، `git/show`: انتخاب مخزن‌های این صفحه (۵۰ مورد)، قطع دسترسی با Modal مشترک، مدیریت مجوز سرویس، دریافت مجدد توسط صف control و تاریخچهٔ زمان‌دار (۳۰ رویداد در صفحه). انتخاب مخزن تازه پیش‌فرض false است. تاریخ جلالی با timezone کاربر و UTC در Backend؛ polling مشترک فقط هنگام نمایش/آنلاین‌بودن صفحه.
- فرم‌ها، Card، Button، Input، Modal، Pagination و Sidebar مشترک مصرف می‌شوند؛ link در navigation مشترک Desktop/Mobile است. چیدمان mobile-first، مقادیر فنی LTR/monospace، tokenهای روشن/تیره و label و checkbox بومی استفاده شدند. مرورگر قابل کنترل در این نشست موجود نبود؛ تأیید بصری viewportها انجام نشده است.

### سه روش اتصال — ۲۰۲۶-۱۰-۱۶

صفحهٔ `/git` اکنون سه کارت `github`، `gitlab` و `gitlabSelf` دارد. دکمهٔ اصلی در ابتدای هر کارت، پس از تنظیم اولیه، با Inertia external location مرورگر را به سرویس می‌برد. اگر تنظیمات موجود نباشد، فرم یک‌باره را باز و ورودی اول را focus می‌کند. تنظیمات در details جمع‌شونده و جدا از مسیر روزمره‌اند. GitLab شخصی ورودی آدرس HTTPS عمومی، نشانی callback مستقل و نمایش origin حساب دارد. پکیج جدید اضافه نشد؛ بررسی بصری با مرورگر متصل انجام نشد.

### آیکون سرویس‌ها، اتصال دستی و تست — ۲۰۲۶-۱۰-۱۶

کارت‌های Git آیکون محلی اختصاصی GitHub/GitLab و GitLab شخصی با نشان Server دارند؛ عنوان و دکمهٔ اتصال هر دو نشان سرویس را دارند. «تست مسیر اتصال خودکار»، فرم دستی با «تست اتصال دستی» و ذخیرهٔ توکن، و «تست اتصال» در جزئیات حساب اضافه شدند. نتیجهٔ واقعی و وضعیت موفق/ناموفق به تاریخچه اضافه می‌شود. Form مشترک React جای فرم‌های بومی تمام صفحات موجود را گرفت؛ required HTML حذف و isRequired/aria-required حفظ شد. راهنما: `docs/FRONTEND_VALIDATION.md`. مرورگر در این نشست متصل نبود؛ QA بصری انجام نشد.


## ساده‌سازی اتصال گیت — ۲۰۲۶-۱۰-۱۶

صفحهٔ `/git` اکنون حساب‌محور است و «افزودن حساب» Modal با انتخاب سه سرویس دارد. تنظیم پیشرفته و تاریخچه جمع‌شونده‌اند. صفحهٔ جدید `git/register` از POST داخلی `/git/github/register` رندر می‌شود و فرم استاندارد React را با manifest عمومی به GitHub POST می‌کند؛ بازگشت GET `/git/github/registered` است. جست‌وجوی سمت سرور، انتخاب گروهی همان صفحه، شمارنده، نوار ذخیره و مراحل اتصال در `git/show` اضافه شدند. اتصال خودکار GitHub برای نصب بدون برنامه از Manifest استفاده می‌کند؛ GitLab بدون برنامه مسیر سریع توکن و تنظیم پیشرفتهٔ OAuth دارد. رابط RTL/mobile-first و tokenهای موجود را مصرف می‌کند. مرورگر متصل موجود نبود؛ QA بصری این تغییر تأیید نشده است.

## PWA — ۲۰۲۶-۱۰-۱۷

کنترل مشترک نصب/به‌روزرسانی `PwaControls` در AppLayout و AuthLayout قرار گرفت؛ صفحهٔ خصوصی یا منوی موازی تازه ساخته نشد. راهنمای نصب در Modal مشترک، RTL، dark/light، focus trap و متن فارسی است؛ viewport کوتاه اسکرول داخلی دارد. prompt نصب فقط به درخواست کاربر و update فقط پس از تأیید ذخیره فرم انجام می‌شود. fallback آفلاین عمومی مستقل در `public/assets/pwa/offline.html` است و هیچ داده یا عملیات آفلاین ارائه نمی‌کند. راهنما و شواهد: PWA.md.
