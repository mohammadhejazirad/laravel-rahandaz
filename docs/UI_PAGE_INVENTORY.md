# موجودی صفحات

پیشوند واقعی پنل `/rahandaz` است، نه مسیرهای با اسلش پایانی فصل ۱۶. `docs/PROJECT_SPEC.md` در ریشه `docs/` وجود ندارد. مشخصات در `.docs/PROJECT_SPEC.md` است.

صفحه عملیاتی فقط جایی است که داده یا عمل از backend واقعی می‌آید و نتیجه جعلی نشان نمی‌دهد. «آماده نیست» در منو لینک مرده است.

| مسیر سند | مسیر واقعی | صفحه | Layout | وضعیت |
| --- | --- | --- | --- | --- |
| `/` | `/` | پاسخ متنی که ورود پنل را نشان می‌دهد | هیچ | خوشامد موفقیت نصب ساخته نشده است |
| `/auth/login/` | `GET/POST /rahandaz/login` | `pages/auth/login.tsx` | Auth | واقعی. ثبت‌نام عمومی نیست |
| تغییر گذرواژه اول | `GET/PUT /rahandaz/password` | `pages/auth/password.tsx` | Auth | واقعی |
| خطا | رندر خطای اینرسی | `pages/errors/show.tsx` | مستقل | ۴۰۳، ۴۰۴، ۴۱۹، ۵۰۰، ۵۰۳ |
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
| `/projects/<uuid>/` | `GET /rahandaz/projects/{uuid}` | `pages/projects/show.tsx` | App | مرور و محیط‌ها. `Accept: application/json` همان قرارداد شناسه را JSON می‌دهد |
| settings پروژه، environment، deployment | — | — | — | آماده نیست |
| `/jobs/` و `/jobs/<uuid>/` | `GET /rahandaz/jobs` و `/{uuid}` | `pages/jobs/index.tsx` و `show.tsx` | App | وضعیت و لاگ ذخیره‌شده، صفحه‌بندی. لغو و polling نیست |
| `/domains/`، `/databases/`، `/backups/`، `/monitoring/` | — | — | — | آماده نیست |
| `/users/` | — | — | — | آماده نیست |
| `/audit/` | `GET /rahandaz/audit` | `pages/audit/index.tsx` | App | فقط مالک و مدیر. بدون metadata و بدون export |
| `/notifications/` | `GET /rahandaz/notifications` | `pages/notifications/index.tsx` | App | فهرست گیرنده. خواندن تک‌مورد و همه واقعی است |
| `/settings/profile/` | `GET /rahandaz/settings/profile` | `pages/settings/profile.tsx` | App | نمایش timezone، locale، پوسته. MFA نیست |
| members، system، route | — | — | — | آماده نیست |

مجوز: عضویت workspace برای صفحه‌های پنل. گزارش فعالیت فقط `owner` و `admin`. اعلان فقط `recipient` همان workspace. شناسه عددی در props این صفحات نیست.

آزمون: `tests/Feature/PanelPagesTest.php` و بخش داشبورد `tests/Feature/PanelHttpTest.php`. smoke تصویری Chrome headless برای صفحه ورود و صفحه راه‌اندازی واقعی در عرض‌های ۳۶۰، ۷۶۸، ۱۲۸۰ و ۱۴۴۰ و پوسته روشن/تیره انجام شده است. Playwright/E2E جریان‌های عملیاتی و visual regression همه صفحه‌ها هنوز وجود ندارد.
