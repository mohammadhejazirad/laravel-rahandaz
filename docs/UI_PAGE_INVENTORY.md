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
| `/projects/` و `/projects/new/` | `GET /rahandaz/projects` | `pages/projects/index.tsx` | App | فهرست واقعی. ساخت پروژه نیست |
| `/projects/<uuid>/` | `GET /rahandaz/projects/{uuid}` | `pages/projects/show.tsx` | App | مرور پروژه/محیط، انتخاب environment، dropzone ZIP و تاریخچه آرشیو/عملیات. `Accept: application/json` همان قرارداد شناسه را JSON می‌دهد |
| upload منبع ZIP | `POST /rahandaz/projects/{project}/environments/{environment}/source-archives` | `components/projects/archive-upload.tsx` | App | واقعی برای Owner/Admin/Operator؛ private store + inspect + deployment/job/outbox/audit. extraction/build آماده نیست |
| ساخت پروژه، settings پروژه، صفحه مستقل environment/deployment | — | — | — | آماده نیست |
| `/jobs/` و `/jobs/<uuid>/` | `GET /rahandaz/jobs` و `/{uuid}` | `pages/jobs/index.tsx` و `show.tsx` | App | وضعیت و لاگ ذخیره‌شده، صفحه‌بندی. لغو و polling نیست |
| `/domains/`، `/databases/`، `/backups/`، `/monitoring/` | — | — | — | آماده نیست |
| `/users/` | — | — | — | آماده نیست |
| `/audit/` | `GET /rahandaz/audit` | `pages/audit/index.tsx` | App | فقط مالک و مدیر. بدون metadata و بدون export |
| `/notifications/` | `GET /rahandaz/notifications` | `pages/notifications/index.tsx` | App | فهرست گیرنده. خواندن تک‌مورد و همه واقعی است |
| `/settings/profile/` | `GET /rahandaz/settings/profile` | `pages/settings/profile.tsx` | App | نمایش timezone، locale، پوسته. MFA نیست |
| `/settings/updates/` | `GET /rahandaz/settings/updates` و `POST /rahandaz/settings/updates/check` | `pages/settings/updates.tsx` | App | وضعیت نسخه از backend. دکمه بررسی، کپی دستور CLI و تاریخچه. پنل فرمان را اجرا نمی‌کند. پکیج UI تازه ندارد |
| members، system، route | — | — | — | آماده نیست |

مجوز: عضویت workspace برای صفحه‌های پنل. upload/deploy پروژه برای `owner`، `admin` و `operator` است و Viewer رد می‌شود. گزارش فعالیت فقط `owner` و `admin`. اعلان فقط `recipient` همان workspace. شناسه عددی و path آرشیو در props این صفحات نیست.

آزمون: `tests/Feature/PanelPagesTest.php`، `tests/Feature/ProjectArchiveUploadTest.php`، بخش داشبورد `tests/Feature/PanelHttpTest.php` و asset/metadata برند در `tests/Feature/BrandAssetsTest.php`. smoke تصویری Chrome headless برای ورود و راه‌اندازی واقعی در عرض‌های ۳۶۰، ۷۶۸، ۱۲۸۰ و ۱۴۴۰ و پوسته روشن/تیره، پوسته system و صفحه خطای ۴۰۴ انجام شده است. dropzone تازه build/typecheck شده ولی smoke مرورگر احراز هویت‌شده یا Playwright/E2E و visual regression همه صفحه‌ها هنوز وجود ندارد.
