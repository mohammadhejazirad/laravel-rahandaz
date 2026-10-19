<p align="center">
  <img src="public/branding/logo.svg" width="360" height="96" alt="راه‌انداز؛ پنل مدیریت سرور محلی">
</p>

# راه‌انداز | Rahandaz

**راه‌انداز؛ پنل مدیریت فارسی و خودمیزبان برای همان سرور Ubuntu که روی آن نصب می‌شود.**

[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel 13.35](https://img.shields.io/badge/Laravel-13.35-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/docs/13.x)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16_dev-4169E1?logo=postgresql&logoColor=white)](https://www.postgresql.org/)

راه‌انداز یک پنل مدیریت خودمیزبان است تا مدیر همان سرور، نصب نرم‌افزار، اتصال Git، استقرار پروژه، دامنه، گواهی، دیتابیس، پشتیبان و وضعیت سرویس‌ها را از یک رابط فارسی انجام دهد. نسخه ۱ فقط میزبان محلی را می‌بیند. مدیریت چند سرور، عامل راه دور و اجرای پنل داخل Docker در این نسخه نیست.

## روش توسعه

این پروژه به روش **وایب‌کدینگ (Vibe Coding)** و با استفاده از ایجنت‌های مختلف هوش مصنوعی توسعه داده می‌شود. این ایجنت‌ها در نوشتن کد، رفع خطا، آزمون و مستندسازی پروژه مشارکت دارند و مسیر توسعه با هدایت مالک پروژه پیش می‌رود.

## نصب سریع و وضعیت نسخه اول

برای نصب نسخهٔ آزمایشی روی **سرور Ubuntu تازه و آزمایشی**، با SSH وارد سرور شوید و همین فرمان را کپی و اجرا کنید:

```bash
(set -eu; rahandaz_install_dir=$(mktemp -d); trap 'rm -rf -- "$rahandaz_install_dir"' EXIT; curl --proto '=https' --proto-redir '=https' --tlsv1.2 -fsSL --connect-timeout 15 --max-time 120 --retry 3 https://github.com/mohammadhejazirad/laravel-rahandaz/releases/download/v1.0.0-beta.14.1/install.sh -o "$rahandaz_install_dir/install.sh"; printf "7fd496625ee8606859520c34dfe0c0df124dc628f8d65eaea5b1ac45350e6d94  %s\n" "$rahandaz_install_dir/install.sh" | sha256sum --check --status; if [ "$(id -u)" -eq 0 ]; then bash "$rahandaz_install_dir/install.sh"; else sudo bash "$rahandaz_install_dir/install.sh"; fi)
```

فرمان ابتدا اسکریپت نسخهٔ مشخص را دانلود و SHA-256 ثابت آن را بررسی می‌کند؛ فقط پس از تطبیق هش، نصب کامل پنل را آغاز می‌کند: بستهٔ آمادهٔ برنامه و رابط را دریافت می‌کند، امضا و checksum بسته را بررسی می‌کند و PHP 8.4 و افزونه‌ها، Nginx، PostgreSQL، Redisهای اختصاصی، worker و scheduler را نصب یا تأیید می‌کند. Docker، Node.js، npm و Composer برای اجرای پنل لازم نیستند. در شروع، نشانی پنل از شما پرسیده می‌شود؛ برای ورود اولیه با HTTP از SSH tunnel طبق [راهنمای KVM](docs/KVM_INSTALLATION_TEST.md) استفاده کنید. اطلاعات ورود اولیه در فایل خصوصی `/root/rahandaz-initial-credentials.txt` قرار می‌گیرد.

پیش‌نیاز شروع این فرمان: Ubuntu Server نسخهٔ 22.04 یا 24.04 با معماری x86_64، دسترسی sudo، اینترنت و ابزارهای `curl`، CA certificates، `openssl`، `python3` و ابزارهای پایهٔ Ubuntu. این ابزارهای bootstrap باید از قبل موجود باشند؛ بقیهٔ وابستگی‌های پنل را نصب‌کننده مدیریت می‌کند. Ubuntu 26.04 فعلاً آزمایشی و غیرپیشنهادی است.

**این نسخه beta است؛ نصب Production هنوز تأیید نشده است.** نصب کامل و reboot/recovery روی سه Ubuntu هنوز گواهی VM ندارند. نصب پنل نیز موتور عملیات میزبان را فعال نمی‌کند؛ حالت پیش‌فرض `disabled` باقی می‌ماند. وضعیت جاری در [گزارش تکمیل عملیاتی](docs/OPERATIONAL_TEST_REPORT.md) و [برنامه اجرایی](docs/OPERATIONAL_COMPLETION_PLAN.md) ثبت شده است.

### جزئیات نصب امضاشده از GitHub

مسیر توزیع راه‌انداز **[GitHub Releases](https://github.com/mohammadhejazirad/laravel-rahandaz/releases)** است؛ به CDN یا Docker برای نصب پنل نیاز نیست. آخرین نسخهٔ آزمایشی امضاشده در زمان بررسی ۲۰۲۶-۱۰-۱۹، [v1.0.0-beta.14.1](https://github.com/mohammadhejazirad/laravel-rahandaz/releases/tag/v1.0.0-beta.14.1) در ۲۰۲۶-۱۰-۱۹ منتشر شد. این prerelease برای آزمون VM است؛ نصب Production هنوز تأیید نشده است. برای فایل‌های نسخه‌های بعدی، صفحه Releases را بررسی کنید.

فرمان کوتاه بالا به فایل یک Release مشخص اشاره می‌کند؛ از شاخهٔ متغیر `main` یا `latest` نصب نمی‌کند. اعتماد اولیهٔ این روش به فرمان بازبینی‌شدهٔ README و HTTPS گیت‌هاب است؛ SHA-256 ثابت در فرمان، پیش از اجرای اسکریپت بررسی می‌شود. خود اسکریپت، امضای manifest و SHA-256 بستهٔ برنامه را با کلید عمومی تعبیه‌شده بررسی می‌کند. امضای `SHA256SUMS` با کلید اعتماد مخزن و تطبیق هش اسکریپت و راهنمای `INSTALL.md` همین نسخه در بازبینی ۲۰۲۶-۱۰-۱۹ تأیید شدند؛ این بررسی گواهی نصب VM نیست.

فرمان بالا از [راهنمای `INSTALL.md` همین Release](https://github.com/mohammadhejazirad/laravel-rahandaz/releases/download/v1.0.0-beta.14.1/INSTALL.md) گرفته شده است؛ روش بررسی دستی و گزینهٔ `--verify-only` نیز در آن راهنما آمده‌اند. برای بررسی امن طرح نصب از checkout مخزن، بدون تغییر میزبان:

```bash
bash installer/bin/rahandaz-install --plan --state-dir /tmp/rahandaz-plan
```

کاربر به سرور SSH می‌زند، فرمان همان نسخه را کپی می‌کند و با حساب root یا دارای sudo اجرا می‌کند. بسته شامل وابستگی‌های PHP و رابط build‌شده است؛ نصب Node.js، npm یا Composer روی سرور صرفاً برای پنل لازم نیست. نصب‌کننده PHP 8.4، Nginx، PostgreSQL، Redis، workerها و scheduler را نصب/تأیید و سلامت را بررسی می‌کند. اطلاعات ورود اولیه فقط در فایل خصوصی مالک ذخیره می‌شود. نصب روی سرور تازه توصیه می‌شود؛ سرویس موجود بدون تأیید پذیرفته نمی‌شود.

هر push به `main`، آزمون و build یک candidate نسخه‌دار را آغاز می‌کند؛ با تنظیم یک‌باره کلید و متغیر انتشار در GitHub، **prerelease امضاشده** خودکار منتشر می‌شود. نسخهٔ پایدار خودکار منتشر نمی‌شود. HTTPS پنل و تکمیل executor هنوز مانع انتشارند؛ نصب‌کننده قابلیت‌های پیاده‌سازی‌نشده را فعال نمی‌کند. راهنمای آماده‌سازی GitHub، امضا، آزمون VM و انتشار در [مدیریت Release](docs/RELEASE_MANAGEMENT.md) است.

صفحات استقرار، دامنه، دیتابیس، پشتیبان‌گیری و پایش اکنون به Backend و PostgreSQL متصل‌اند؛ درخواست‌های عملیاتی Job/Outbox/Audit واقعی دارند، ولی executor میزبان هنوز بسته است. مرکز آموزش‌ها جست‌وجو و نشان‌گذاری واقعی دارد. [گیت‌های انتشار](docs/V1_RELEASE_READINESS.md) و [گزارش آزمون](docs/V1_TEST_REPORT.md) محدوده تأییدشده و مانع‌ها را مشخص می‌کنند. این مخزن هنوز Production-ready نیست.

درخت `installer/` و prerelease عمومی امضاشده موجودند؛ نصب کامل Production هنوز تأیید نشده و مجوز پروژه [Apache-2.0](LICENSE) است. حالت پیش‌فرض `RAHANDAZ_EXECUTION_MODE=disabled` است. در این حالت پنل دستور میزبان اجرا نمی‌کند و هیچ عملیات یا deployment را موفق اعلام نمی‌کند. جدول وضعیت پایین، قابلیت آماده را از طرح محصول جدا می‌کند. جزئیات پیشرفت در [docs/IMPLEMENTATION_PROGRESS.md](docs/IMPLEMENTATION_PROGRESS.md) و مشخصات در [.docs/PROJECT_SPEC.md](.docs/PROJECT_SPEC.md) است.

نشان‌های بالا فقط نسخه قفل‌شده Laravel و محدودیت PHP را می‌گویند. تصویر PostgreSQL به image توسعه `postgres:16-alpine` در `compose.dev.yml` اشاره دارد، نه به یک نسخه production آزموده‌شده روی Ubuntu. فایل مجوز Apache-2.0 موجود است؛ وجود Release عمومی و موفقیت CI باید از GitHub بررسی شود. فایل‌های workflow در `.github/workflows/` هستند و در این مخزن اجرا نشده‌اند، پس نشان سبز CI معنا ندارد.

## فهرست

- [چرا راه‌انداز؟](#why)
- [وضعیت این مخزن](#status)
- [قابلیت‌ها](#features)
- [معماری](#architecture)
- [سازگاری سیستم‌عامل](#compatibility)
- [نصب](#install)
- [انتشار و نصب از GitHub](#github-releases)
- [پس از بالا آمدن پنل](#after-start)
- [استقرار پروژه‌ها](#deployment)
- [پایگاه داده](#database)
- [امنیت](#security)
- [اجرا بدون Docker](#runtime)
- [محیط توسعه](#development)
- [ساختار مخزن](#layout)
- [به‌روزرسانی](#updates)
- [پشتیبان و بازیابی](#backup)
- [عیب‌یابی](#troubleshooting)
- [مستندات](#docs)
- [نقشه راه](#roadmap)
- [مشارکت](#contributing)
- [مجوز](#license)

<a id="why"></a>
## چرا راه‌انداز؟

کار روزمره روی یک VPS معمولاً پراکنده است: نصب PHP و Node، ساخت سایت Nginx، گواهی، کاربر دیتابیس، clone از GitHub یا GitLab، متغیر محیط، و برگشت به release قبلی وقتی استقرار می‌شکند. راه‌انداز برای همین کار روی **یک** سرور Ubuntu طراحی شده است، با رابط فارسی و بدون وابستگی رابط به CDN.

نسخه ۱ این تفاوت‌ها را نسبت به کار کاملاً دستی هدف گرفته است:

- دیدن وضعیت همان میزبان و سرویس‌هایش از پنل، به‌جای جمع‌کردن خروجی دستورها.
- جدا کردن محیط‌های یک مخزن، مثلاً `main` و `beta`، با runtime، متغیر، دیتابیس، دامنه و release مستقل.
- ثبت عملیات طولانی در صف، با وضعیت و لاگ، به‌جای اجرای مستقیم از درخواست HTTP.
- نگهداری راز عملیاتی به‌صورت پاکت رمز، جدا از `APP_KEY`.
- محدود کردن تغییر سیستم به یک helper اجازهٔ مشخص، پس از آنکه نصب اولیه تمام شود. این helper هنوز در مخزن نیست.

راه‌انداز ادعا نمی‌کند از پنل‌های دیگر بهتر است. مقایسه میدانی انجام نشده است. ایزوله‌سازی پروژه‌ها در طرح نسخه ۱ با کاربر یونیکس، ACL و cgroup است و جای ماشین مجازی را نمی‌گیرد.

<a id="status"></a>
## وضعیت این مخزن

| برچسب | معنی |
| --- | --- |
| آماده | در کد فعلی برای همان محدوده کار می‌کند |
| در حال ساخت | طرح یا داده هست؛ عمل نهایی به میزبان یا سرویس کاربر نمی‌رسد |
| برنامه‌ریزی‌شده | در مشخصات نسخه ۱ است و در این مخزن ساخته نشده |

هسته حساب، اسکیما، پوسته فارسی و صفحه‌های خواندنی آماده‌اند. موتور عملیات رکورد را ثبت می‌کند و عمداً آن را `succeeded` نمی‌کند. اسکریپت‌های میزبان در `infrastructure/operations/` هستند و اعمال زنده را روی همین میزبان توسعه مسدود می‌کنند. قرارداد helper در `infrastructure/privilege_helper/` است، ولی daemon سوکت root نصب نشده است. نصب تک‌دستوری، استقرار واقعی، گواهی و پشتیبان اجرایی هنوز production نیستند. نقشه در [.docs/ROADMAP.md](.docs/ROADMAP.md) و وضعیت موتور در [docs/OPERATIONS.md](docs/OPERATIONS.md) است.

<a id="features"></a>
## قابلیت‌ها

### پنل و حساب

| قابلیت | وضعیت | امروز |
| --- | --- | --- |
| ورود با نام کاربری یا ایمیل، بدون ثبت‌نام عمومی | آماده | `GET/POST /rahandaz/login` |
| اجبار تعویض رمز در ورود اول | آماده | `GET/PUT /rahandaz/password` |
| پوسته روشن، تیره و سیستم؛ وزیرمتن محلی؛ RTL | آماده | بدون CDN |
| Toast برای پیام واقعی سیستم و خطای فرم | آماده | React-Toastify پشت `notify` و flash |
| نمایش تاریخ ذخیره‌شده با تقویم جلالی | آماده | `Intl`، منطقه زمانی پیش‌فرض `Asia/Tehran`، ذخیره UTC |
| نقش‌های owner، admin، operator، viewer | در حال ساخت | عضویت و Policy هست. ماتریس کامل مشخصات هنوز به عمل میزبان وصل نیست |
| MFA و بازیابی رمز | برنامه‌ریزی‌شده | صفحه تنظیمات فقط نمایه را نشان می‌دهد |
| کاربران و اعضا | برنامه‌ریزی‌شده | صفحه ندارد |

### میزبان، نرم‌افزار و مشاهده

| قابلیت | وضعیت | امروز |
| --- | --- | --- |
| فهرست و برگه سرور محلی از ردیف‌های ذخیره‌شده | آماده | CPU، دیسک و شبکه زنده را نشان نمی‌دهد |
| راه‌اندازی فقط‌خواندنی | آماده | probe نصب نمی‌کند و سازگاری بسته را `untested` می‌گذارد |
| کاتالوگ و میرور | آماده | فقط خواندن. اعمال میرور و نصب بسته نیست |
| اطلاعات زنده Ubuntu، systemd، UFW، کلید SSH | در حال ساخت | اسکریپت facts هست و سازگاری را `untested` می‌گذارد. پنل آن را اجرا نمی‌کند. کنسول آزاد root نیست |
| نصب PHP، Node، Python، Nginx و سرویس‌ها روی میزبان | برنامه‌ریزی‌شده | اجرا خاموش است |
| صفحه پایش | در حال ساخت | نمونه و سلامت ذخیره‌شده، timestamp، stale و polling فقط در صفحه فعال؛ collector و نمودار زنده نیست |

### پروژه، Git و استقرار

| قابلیت | وضعیت | امروز |
| --- | --- | --- |
| فهرست پروژه و برگه محیط‌ها | آماده | مالک و مدیر از رابط حساب، مخزن و شاخه را انتخاب می‌کنند. نتیجه درخواست استقرار است، نه اجرای میزبان |
| آپلود ZIP با انتخاب فایل یا drag-and-drop | آماده | بررسی امنیتی، ذخیره خصوصی، SHA-256، deployment/job/outbox/audit واقعی؛ extraction و build هنوز اجرا نمی‌شود |
| GitHub، GitLab و GitLab خودمیزبان | برنامه‌ریزی‌شده | جدول‌ها و بررسی امضای وب‌هوک در کد هست. کنترلر HTTP وب‌هوک نیست |
| انتخاب مخزن و شاخه، deploy key، استقرار خودکار | در حال ساخت | انتخاب حساب و مخزن و شاخه و ثبت SHA انجام می‌شود. deploy key، webhook و اجرای میزبان نیست |
| دامنه، Nginx، TLS و تمدید گواهی | در حال ساخت | فهرست/جزئیات و ثبت درخواست دامنه/گواهی؛ اعمال Nginx و ACME واقعی نیست |
| Rollback و استقرار با کمترین وقفه | برنامه‌ریزی‌شده | تضمین صفر شدن وقفه در مشخصات هم مطلق نیست |
| لاگ deployment | در حال ساخت | لاگ job ذخیره‌شده خوانده می‌شود. استقرار واقعی لاگ تولید نمی‌کند |

### داده، صف و امنیت عملیاتی

| قابلیت | وضعیت | امروز |
| --- | --- | --- |
| PostgreSQL راه‌انداز و دو Redis توسعه | آماده | صف `control` و cache جدا |
| ثبت job، idempotency، outbox و lease | در حال ساخت | lease منقضی دزدیده نمی‌شود. runner موفقیت نمی‌نویسد |
| ثبت استقرار، لغو و تلاش مجدد | در حال ساخت | SHA ثابت، جلوگیری از تعارض محیط، retry و لغو پیش از اجرا؛ build/health/switch/rollback زنده نیست |
| PostgreSQL، MySQL، MariaDB و MongoDB برای پروژه کاربر | در حال ساخت | درخواست دیتابیس/کاربر رمز‌شده و Job واقعی؛ SQL/engine executor نیست |
| پشتیبان‌گیری و بازیابی | در حال ساخت | فهرست/جزئیات/زمان‌بندی خواندنی و ثبت درخواست؛ artifact و بازیابی واقعی تأیید نشده |
| آموزش‌ها | آماده در محدوده فعلی | ۲۲ مقاله فارسی در ۸ دسته، جست‌وجو، صفحه‌بندی، نشان کاربر در PostgreSQL و راهنمای زمینه‌ای؛ پوشش محتوای همه قابلیت‌های هدف هنوز کامل نیست |
| اعلان داخل پنل | آماده | فهرست گیرنده، خواندن یکی و خواندن همه |
| گزارش فعالیت | آماده | فقط owner و admin. بدون metadata، hash و IP در صفحه |
| پاکت رمز راز | آماده | HKDF-SHA256 و XChaCha20-Poly1305. چرخش کلید هنوز workflow ندارد |
| Helper ممتاز با allowlist | برنامه‌ریزی‌شده | `ExecutionGate` تغییر میزبان را رد می‌کند |

صفحهٔ دقیق مسیرها در [docs/UI_PAGE_INVENTORY.md](docs/UI_PAGE_INVENTORY.md) است.

<a id="architecture"></a>
## معماری

پنل مدیریت یک Laravel modular monolith است. رابط با Inertia به React می‌رسد. PostgreSQL وضعیت پایدار را نگه می‌دارد. Redis صف و cache را جدا نگه می‌دارد و جای وضعیت job نیست.

نمودار زیر **هدف نسخه ۱** است. در کد فعلی درخواست وب تا PostgreSQL و صفحه‌های خواندنی می‌رسد. انتشار outbox به Redis در `rahandaz:outbox:dispatch` هست. پیکان‌های executor، helper و تغییر Ubuntu هنوز بسته است.

```mermaid
flowchart LR
  browser["Browser: Inertia and React"] --> nginx["Nginx"]
  nginx --> php["PHP-FPM: Laravel"]
  php --> pg["PostgreSQL"]
  php --> cache["Redis cache"]
  pg --> dispatch["Outbox dispatcher"]
  dispatch --> queue["Redis queue"]
  queue --> worker["queue:work"]
  worker --> executor["Local executor"]
  executor --> helper["Privileged helper"]
  helper --> host["Local Ubuntu"]
  worker --> pg
```

### پشته

نسخه نصب‌شده از [docs/DEPENDENCIES.md](docs/DEPENDENCIES.md) است. محدوده نصب بعدی در `composer.json` و `package.json` است.

| لایه | انتخاب فعلی | نسخه قفل‌شده یا محدودیت |
| --- | --- | --- |
| زبان پنل | PHP | محدودیت `^8.4`. باینری نصب‌شده `/usr/bin/php8.4`. این ایستگاه ۸٫۵٫۱۱ است و `php8.4` ندارد |
| Backend | Laravel | ۱۳٫۳۵٫۰ |
| پل رابط | Inertia Laravel | ۳٫۵٫۱ |
| رابط | React و TypeScript سخت‌گیر | React ۱۹٫۳٫۰، TypeScript ۶٫۰٫۳ |
| CSS | Tailwind CSS | ۴٫۳٫۳ |
| Build | Vite | ۸٫۳٫۴. فقط توسعه و CI. اجرای production پنل به Node نیاز ندارد |
| آیکون و اعلان | Lucide، React-Toastify | Lucide ۱٫۵۵٫۰، React-Toastify ۱۱٫۱٫۰ |
| Primitive رابط | shadcn/ui گزینشی، Radix | فقط Sheet، Modal، Dropdown Menu، Tooltip، Avatar، Separator، Breadcrumb و Slot |
| پایگاه | PostgreSQL | image توسعه ۱۶ |
| صف و cache | Redis | image توسعه ۷، دو سرویس جدا |
| ORM و صف | Eloquent، Laravel Queue، Scheduler | Horizon نصب نشده |

shadcn/ui به‌صورت گزینشی و فقط برای Primitiveهای مصرف‌شده روی Radix نصب شده است. React-Toastify همچنان تنها سیستم Toast است. Recharts، React Hook Form، Zod و Motion نصب نشده‌اند؛ دلیل در [docs/FRONTEND_DEPENDENCIES.md](docs/FRONTEND_DEPENDENCIES.md) است. فرم ورود و رمز از `useForm` خود Inertia استفاده می‌کند.

### چرا این مرزها

PostgreSQL منبع کاربر، workspace، پروژه، job، outbox، audit و فراداده راز است. تراکنش و قید یکتایی همین‌جا نشسته‌اند. Redis فقط بیدار کردن worker، cache و هماهنگی کوتاه است. اگر Redis خالی شود، وضعیت قابل‌بازسازی باید در PostgreSQL مانده باشد.

کار طولانی از درخواست HTTP اجرا نمی‌شود. وب، در طرح نسخه ۱، job را در تراکنش ثبت می‌کند. dispatcher ردیف outbox را داخل همان تراکنش به Redis می‌فرستد و بعد `published_at` را می‌نویسد. تحویل at-least-once است. `retry_after` پیش‌فرض ۹۰ ثانیه است و باید از timeout ورکر، ۶۰ ثانیه، بلندتر بماند. worker شناسه job را می‌گیرد و وضعیت را در PostgreSQL می‌نویسد.

Helper ممتاز در طرح، یک برنامه root-owned با اسکیمای ثابت و allowlist است تا حساب پنل sudo عمومی نداشته باشد. فایل قرارداد در `infrastructure/privilege_helper/bin/rahandaz-helper` است و سوکت root را نصب نمی‌کند. dispatcher لاراول تا بسته بودن `ExecutionGate` فرآیندی نمی‌سازد، پس امروز مسیر پنل به APT، Nginx یا systemd نمی‌رسد.

جزئیات مرز ماژول‌ها در [.docs/ARCHITECTURE.md](.docs/ARCHITECTURE.md) است.

<a id="compatibility"></a>
## سازگاری سیستم‌عامل

هدف نسخه ۱ فقط Ubuntu Server روی x86_64 است. «هدف» با «آزموده‌شده» یکی نیست. ماتریس در [docs/OPERATIONS_TEST_MATRIX.md](docs/OPERATIONS_TEST_MATRIX.md) هر سه نسخه را `untested` ثبت کرده است. آزمون VM اجرا نشده است.

| سیستم‌عامل | معماری | نصب تولید | وضعیت |
| --- | --- | --- | --- |
| Ubuntu Server 22.04 LTS | x86_64 | PHP توزیع برای پنل کافی نیست | Not yet verified |
| Ubuntu Server 24.04 LTS | x86_64 | فقط طرح در کانتینر آزمایشگاه | Not yet verified |
| Ubuntu Server 26.04 LTS | x86_64 | فقط طرح در کانتینر آزمایشگاه | Not yet verified |
| ARM64 و توزیع‌های دیگر | — | خارج از نسخه ۱ | Unsupported |

نسخه ۱ چند سرور را مدیریت نمی‌کند. اتصال SSH مدیریتی، Ansible و agent راه دور برای نسخه ۲ و ۳ مانده‌اند. پروژه کاربر هم روی همان میزبان و بدون Docker استقرار نسخه ۱ طراحی شده است.

آزمایش نصب موتور روی ایمیج رسمی Ubuntu جدا از این جدول است و گواهی production نیست. اجرای ۲۰۲۶-۱۰-۱۳ هر سه نسخه را در آزمایشگاه گذراند و ستون «وضعیت» همین جدول را تغییر نمی‌دهد:

```bash
./tests/infrastructure/ubuntu-lab/scripts/lab-test.sh 24.04
```

راهنمای کامل در [tests/infrastructure/ubuntu-lab/README.md](tests/infrastructure/ubuntu-lab/README.md) است.

<a id="install"></a>
## نصب

نصب Production هنوز تأیید نشده است. برای آزمون VM، بسته و راهنمای `INSTALL.md` نسخهٔ [v1.0.0-beta.14.1](https://github.com/mohammadhejazirad/laravel-rahandaz/releases/tag/v1.0.0-beta.14.1) موجود است. فرمان نسخه‌دار و دارای بررسی هش در بخش [نصب سریع و وضعیت نسخه اول](#نصب-سریع-و-وضعیت-نسخه-اول) برای سرور تازه و آزمایشی درج شده است.

وجود Release امضاشده و تأیید فایل‌های عمومی به معنی نصب Production تأییدشده نیست؛ پذیرش Gateهای نصب و گواهی VM همچنان لازم است.

کد نصب‌کننده در `installer/` است. پیش‌فرض آن طرح است و سرور را تغییر نمی‌دهد:

```bash
bash installer/bin/rahandaz-install --plan --state-dir /tmp/rahandaz-plan
```

اعمال بعدی باید نسخه‌دار باشد، قبل از هر تغییر امضا و SHA-256 را بررسی کند، قابل ادامه باشد، سایت و سرویس موجود را بی‌اجازه عوض نکند، SSH جاری را نبندد، و مدیر انسانی `rahandaz` با sudo رمزدار و حساب سرویس محدود `rahandaz-svc` را جدا بسازد. عملیات ممتاز بعد از بالا آمدن پنل فقط از helper می‌گذرد و امروز آن سوکت نصب نیست. سلامت نصب تولید از روی این فایل تأیید نمی‌شود.

انتخاب پیشنهادی فعلی Ubuntu Server 22.04 یا 24.04 است؛ 26.04 آزمایشی و غیرپیشنهادی است. پیش‌نیاز هدف روی x86_64، حداقل ۱ GiB RAM و ۵ GiB دیسک آزاد، و دسترسی root برای اعمال. PHP خود راه‌انداز 8.4 است و از مخزن امضاشده `packages.sury.org` می‌آید؛ PHP پروژه‌های کاربر عوض نمی‌شود. نصب کامل این سه نسخه هنوز اجرا نشده است. Node.js برای اجرای پنل لازم نیست. راهنمای کامل در [docs/INSTALLATION.md](docs/INSTALLATION.md) و به‌روزرسانی در [docs/SELF_UPDATE.md](docs/SELF_UPDATE.md) است.

<a id="github-releases"></a>
### انتشار و نصب از GitHub

بستهٔ آمادهٔ نصب با Source ZIP خودکار GitHub متفاوت است: باید `vendor` تولید و `public/build` را همراه داشته باشد. workflow [Build and publish release candidate](.github/workflows/release-build.yml) هر push به main را با نسخه `v1.0.0-beta.<run>.<attempt>` آزمون و build می‌کند. انتشار خودکار prerelease به متغیر `RAHANDAZ_AUTOMATIC_PRERELEASES=true`، کلید عمومی بررسی‌شده در `installer/trust/rahandaz-release.pub` و secret خصوصی در Environment محافظت‌شدهٔ `release-signing` نیاز دارد. هیچ کلید خصوصی در Git ثبت نمی‌شود.

خروجی امضاشده شامل بستهٔ نسخه‌دار، `release.manifest` و امضای آن، `install.sh`، `INSTALL.md`، کلید عمومی و `SHA256SUMS` امضاشده است. کلید عمومی همراه دانلود صرفاً برای مشاهده است؛ اعتماد bootstrap از کلید pin‌شده داخل اسکریپت و هش اسکریپت در راهنمای بررسی‌شده می‌آید. مراحل تنظیم و محدودیت‌ها در [docs/RELEASE_MANAGEMENT.md](docs/RELEASE_MANAGEMENT.md) آمده است.

برای دسترسی اولیهٔ HTTP از شبکهٔ مورد اعتماد یا SSH tunnel استفاده کنید؛ اعتبار ورود را روی HTTP عمومی نفرستید. TLS پنل در نصب‌کنندهٔ فعلی خودکار آماده نمی‌شود. بعد از قبولی نصب، مسیر ورود `/rahandaz/login` و فایل اعتبار اولیه `/root/rahandaz-initial-credentials.txt` است؛ رمز در لاگ یا خروجی عمومی چاپ نمی‌شود.

### مسیر نصب کاربر بعد از انتشار امضاشده

1. با SSH و حساب root یا sudo وارد Ubuntu تازه شوید؛ 24.04 انتخاب پیشنهادی برای اولین آزمون است.
2. از صفحه همان نسخه در GitHub Releases، فایل `INSTALL.md` را باز کنید و فرمان یک‌خطی تولیدشده را اجرا کنید. این فرمان اسکریپت نسخه ثابت را دانلود، SHA-256 آن را بررسی و سپس اجرا می‌کند؛ bootstrap امضای manifest و صحت کل بسته را بررسی می‌کند. تا انتشار واقعی و آزمون فرمان، URL/هش ساختگی در این README قرار نمی‌گیرد.
3. نصب‌کننده وابستگی‌های کنترل‌پلین، PHP 8.4، Nginx، PostgreSQL، Redisهای اختصاصی، سرویس‌ها و پنل build‌شده را نصب/بررسی می‌کند؛ نصب در Production native است و به Docker نیاز ندارد. Mirror ایران با گزینه `--prefer-iranian-mirrors` قابل انتخاب است؛ این گزینه جای تضمین دسترسی GitHub یا مخزن PHP نیست.
4. پس از healthcheck موفق، حساب اولیه پنل خودکار ساخته شده و مسیر ورود و فایل root-only اعتبار اعلام می‌شود. در اتصال SSH امن، اعتبار را با `sudo cat /root/rahandaz-initial-credentials.txt` ببینید، وارد پنل شوید و رمز اولیه را عوض کنید. رمزها وارد لاگ نصب نمی‌شوند و اجرای مجدد آنها را بازتولید نمی‌کند.
5. در وضعیت فعلی می‌توانید پنل و ثبت درخواست‌ها را تست کنید؛ **مدیریت و نصب/استقرار واقعی از پنل هنوز به تکمیل helper/executor وابسته است**. پیام اجرای میزبان غیرفعال، خطای نصب نیست و با تغییر env دور زده نمی‌شود.

اولین آزمایش شخصی را مطابق [راهنمای نصب روی KVM](docs/KVM_INSTALLATION_TEST.md) انجام دهید. مسیر دریافت بسته Actions و امضای آزمایشی قبل از راه‌اندازی انتشار عمومی نیز در همان سند آمده است. HTTP اولیه فقط از tunnel یا شبکه trusted استفاده شود؛ HTTPS پنل هنوز خودکار صادر نمی‌شود.

### بالا آوردن محیط توسعه

این مسیر برای کارکردن روی خود مخزن است. Compose فقط PostgreSQL و دو Redis را بالا می‌آورد و پنل را داخل Docker اجرا نمی‌کند.

پیش‌نیاز توسعه: PHP ای که محدودیت `^8.4` را برآورده کند، همراه `pdo_pgsql`، `redis`، `sodium`، `zip` و `fileinfo`، Composer، Node.js سازگار با Vite 8، npm، و Docker فقط اگر پایگاه را از Compose می‌خواهید. PHP 8.5 این محدودیت را برآورده می‌کند، ولی باینری پنل روی سرور `/usr/bin/php8.4` است. PHP بدون درایور PostgreSQL به پایگاه وصل نمی‌شود و بدون `ext-zip` upload پروژه بررسی نمی‌شود.

```bash
docker compose -f compose.dev.yml up -d
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan rahandaz:keys:init --local-only
php artisan rahandaz:bootstrap-owner --credentials-file=./var/initial-owner.txt
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

`.env.example` میزبان پایگاه و Redis را `127.0.0.1` گذاشته است. اگر فرایند PHP به loopback میزبان دسترسی ندارد، همان توضیح فایل نمونه را دنبال کنید. جزئیات محیط Lerd در [docs/IMPLEMENTATION_PROGRESS.md](docs/IMPLEMENTATION_PROGRESS.md) است.

`rahandaz:keys:init --local-only` کلید اصلی را جدا از `APP_KEY` می‌سازد. اجرای دوباره، اگر فایل کلید پر باشد، عمداً متوقف می‌شود. کلید را برای «رفع خطا» پاک نکنید. بدون کلید، رازهای بعدی باز نمی‌شوند.

`rahandaz:bootstrap-owner` فقط وقتی مالک نیست کاربر اول را می‌سازد. مسیر فایل الزامی است، داخل `public/` پذیرفته نمی‌شود، و فایل موجود را بازنویسی نمی‌کند. پوشه `/var/` در `.gitignore` است. رمز را در ترمینال چاپ نکنید. فایل حالت `0600` می‌خواهد. اگر دیسک `chmod` را نگه نمی‌دارد، مسیر کلید و canary را به یک volume سازگار با POSIX ببرید. متغیرهایش در `.env.example` است.

بعد از build، ورود توسعه روی [http://127.0.0.1:8000/rahandaz/login](http://127.0.0.1:8000/rahandaz/login) است. `APP_URL` فقط origin است و پیشوند `/rahandaz` را در خودش تکرار نمی‌کند. اولین ورود به عوض کردن رمز می‌رود.

<a id="after-start"></a>
## پس از بالا آمدن پنل

در محیط توسعه، بعد از ورود این‌ها واقعی است:

- تعویض رمز اولیه.
- داشبورد با شمارش ذخیره‌شده، نه نمودار زنده.
- صفحه راه‌اندازی فقط‌خواندنی. پایان راه‌اندازی را با یک دکمه موفقیت علامت نمی‌زند.
- فهرست سرور، کاتالوگ، میرور، پروژه، job، اعلان و نمایه.
- گزارش فعالیت برای owner و admin.

منوی استقرار، دامنه، دیتابیس، پشتیبان‌گیری، پایش و آموزش‌ها فعال است. ثبت درخواست جای موفقیت میزبان نیست؛ هشدار حالت خاموش در صفحه باقی می‌ماند. جست‌وجو و نشان آموزش به نشست و PostgreSQL واقعی متصل است.

طرح نسخه ۱ بعد از نصب تولید این مرحله‌ها را می‌خواهد: بررسی میزبان، انتخاب میرور، انتخاب PHP و Node، انتخاب دیتابیس، سرویس اختیاری، کلید SSH، و سلامت Nginx. سرویس خود راه‌انداز با نرم‌افزار پروژه‌های کاربر یکی نیست. قالب خوشامد در `assets/welcome-site/index.html` و `installer/templates/welcome/index.html` است و placeholderهای `{{PANEL_URL}}`، `{{HEALTH_MARKER}}` و `{{SETUP_STATUS_LABEL}}` را نگه می‌دارد. مسیر `/` امروز متن `Not found` و مسیر ورود پنل را برمی‌گرداند. نصب‌کننده این قالب را فقط بعد از موفقیت `/up` و `/rahandaz/login` منتشر می‌کند. آن healthcheck هنوز اجرا نشده است.

<a id="deployment"></a>
## استقرار پروژه‌ها

این بخش ترکیبی از قابلیت آماده و طرح [.docs/PROJECT_SPEC.md](.docs/PROJECT_SPEC.md) است. runner فعلی deployment را موفق نمی‌کند و وب‌هوک HTTP وصل نیست.

برای پروژه‌ای که از قبل environment دارد، صفحه جزئیات اکنون فایل ZIP را با file picker یا drag-and-drop می‌پذیرد. فایل در disk خصوصی ذخیره، با SHA-256 deduplicate و به deployment دستی و `operation_jobs` متصل می‌شود. ZIP در درخواست HTTP extract یا build نمی‌شود و پیام UI فقط ثبت workflow را تأیید می‌کند. کنترل‌های مسیر، symlink، entry رمز‌شده، سقف حجم/تعداد و compression bomb در [docs/PROJECT_ARCHIVE_UPLOADS.md](docs/PROJECT_ARCHIVE_UPLOADS.md) مستند است.

سناریوی هدف: یک مخزن Laravel با شاخه‌های `main` و `beta` روی همین سرور، با دو دامنه جدا مثل `example.com` و `beta.example.com`. هر environment شاخه، runtime، متغیر محیط، دیتابیس، دامنه، release، لاگ و سیاست استقرار خودش را دارد. دامنه و UID یکی نیستند و کد مخزن با هویت وب پنل اجرا نمی‌شود.

استقرار خودکار در طرح با webhook است. GitHub یا GitLab فقط وقتی شاخه موردنظر به‌روز شد خبر می‌دهد. پنل تحویل را با امضا می‌سنجد، `delivery_id` تکراری را رد می‌کند، و payload خام را ذخیره نمی‌کند. polling دائمی مخزن لازم نیست. تابع امضا در `app/Domain/Git/WebhookSignature.php` هست و هنوز در مسیر HTTP قرار نگرفته است.

استقرار کم‌وقفه در طرح به ظرفیت آزاد، healthcheck مسیر واقعی، و migration سازگار وابسته است. symlink برای سایت ایستا، release به‌اضافه PHP-FPM برای Laravel، و دو port برای Next/Nuxt در مشخصات آمده‌اند. وقفه صفر برای هر پروژه، هر migration ناسازگار، یا سرور کم‌حافظه تضمین نشده است.

<a id="database"></a>
## پایگاه داده

PostgreSQL وضعیت پایدار است. گروه‌های اصلی: کاربر و workspace، فراداده میزبان، اتصال Git، پروژه و environment، آرشیو منبع پروژه، deployment، job و outbox، فراداده credential، backup، و audit.

قرارداد شناسه در کد و migrationهای تازه اعمال شده است، نه فقط هدف بعدی:

- `id` از نوع BIGINT کلید اصلی و کلید رابطه داخلی است.
- `uuid` از نوع native UUIDv7 شناسه API، URL و Inertia است و یکتا و اجباری است.
- کلید خارجی داخلی BIGINT است.
- `uuid` مجوز دسترسی نیست. Policy عضویت workspace را جدا بررسی می‌کند.
- شناسه GitHub یا GitLab ستون خودش را دارد و UUID راه‌انداز نیست.

جدول‌های خود Laravel مثل `sessions`، `cache` و `jobs` این جفت را نمی‌گیرند. `sessions.user_id` نوع `users.id` را دنبال می‌کند. `failed_jobs.uuid` همان رشته Laravel است. job دامنه در `operation_jobs` است.

فهرست جدول‌ها و قیدها در [docs/DATABASE_SCHEMA.md](docs/DATABASE_SCHEMA.md) و [docs/DATABASE_ERD.md](docs/DATABASE_ERD.md) است. تصمیم شناسه در [docs/adr/0004-bigint-primary-key-uuidv7-public-identifier.md](docs/adr/0004-bigint-primary-key-uuidv7-public-identifier.md) است.

<a id="security"></a>
## امنیت

طرح نسخه ۱ حساب لینوکسی `rahandaz` را بدون root، بدون sudo و بدون shell ورود می‌خواهد. فرآیند وب و worker هم root نیستند. قرارداد همین حساب در نصب‌کننده هست و روی این میزبان توسعه `useradd` نشده است.

آنچه امروز در کد هست:

- نشست Laravel، CSRF فرم‌ها، و Policy برای صفحه پنل.
- رمز کاربر با hash خود Laravel. سیاست فعلی حداقل دوازده نویسه با حروف کوچک، بزرگ و عدد است.
- کلید اصلی جدا از `APP_KEY`، فایل خارج از git، و canary برای تشخیص کلید اشتباه.
- پاکت AEAD برای credential. رمزگشایی عملیاتی با `openFor()` فقط برای owner و admin همان workspace است. مجوز `manage_secrets` برای operator هنوز در این مسیر اعمال نشده است.
- رد شدن کلیدهای شبیه راز در plan. لاگ job و صفحه audit متن راز را نشان نمی‌دهند.
- زنجیره hash برای audit در همان پایگاه. این زنجیره لنگر بیرونی ندارد و در برابر مالک پایگاه ضددستکاری نیست.
- بررسی امضای وب‌هوک GitHub با HMAC و GitLab با مقایسه هم‌طول، در تابع دامنه. نرخ‌محدودیت HTTP این مسیر هنوز نیست.
- RLS روشن نیست و جایگزین Policy نیست. نقش migration از نقش اجرای برنامه جدا نشده است.

آنچه هنوز تأیید نشده: سوکت helper، واحدهای systemd روی VM، تأیید مالکیت دامنه و اعمال Nginx. گارد خروجی HTTPS نشانی‌های ویژه و DNS غیرعمومی را رد می‌کند؛ اتصال کامل GitLab خودمیزبان هنوز آماده نیست. ResourceGrant در همه query/actionها اعمال نمی‌شود و مانع امنیتی انتشار است. دانستن `uuid` مجوز نیست و ایزوله یونیکس جای VM در برابر کد مخرب را نمی‌گیرد.

بکاپ پایگاه بدون نسخه جداگانه کلید اصلی، credentialهای رمزشده را برنمی‌گرداند. عوض کردن فایل کلید بدون workflow چرخش، رازهای موجود را ناخوانا می‌کند.

این متن امنیت مطلق ادعا نمی‌کند. [سیاست گزارش امنیتی](SECURITY.md)، [یافته‌های ممیزی](docs/V1_SECURITY_AUDIT.md) و [امنیت داده](docs/DATABASE_SECURITY.md) محدودیت‌ها را توضیح می‌دهند. کانال خصوصی رسمی هنوز نیازمند تصمیم مالک است.

<a id="runtime"></a>
## اجرا بدون Docker

اجرای production پنل و استقرار پروژه‌های کاربر در نسخه ۱ به Docker وابسته نیست. طرح این است که Nginx، PHP-FPM، worker صف و timer زمان‌بند با systemd روی Ubuntu باشند. Compose این مخزن فقط وابستگی توسعه است.

در طرح، هر environment کاربر مسیر، کاربر یونیکس، pool یا port، و سایت Nginx خودش را دارد. Nginx درخواست را به همان پروژه می‌فرستد. Runtime مهمان با runtime پنل یکی نیست. سقف حافظه و CPU پروژه به cgroup بستگی دارد و با نصب‌نشدن Docker کامل نمی‌شود.

پروفایل آزمون هدف در مشخصات، ۱ vCPU و ۲GB RAM است. سقف افزوده راه‌انداز در حالت idle، حداکثر ۵۱۲MiB RAM و میانگین ۰٫۵ vCPU، هدف طراحی است و benchmark نشده است. build کاربر، backup و نصب بسته از مصرف idle پنل جدا هستند و می‌توانند بالاتر بروند. تا ثبت اندازه‌گیری، این عددها تضمین محصول نیستند.

<a id="development"></a>
## محیط توسعه

راهنمای بلندتر در [.docs/DEVELOPMENT.md](.docs/DEVELOPMENT.md) است. فرمان‌های زیر با اسکریپت‌ها و Artisan همین مخزن جورند.

رابط را یک‌بار بسازید، یا در توسعه Vite را جدا اجرا کنید:

```bash
npm run build
npm run dev
```

`npm run dev` فقط Vite است. `composer run dev` سرور PHP، شنونده صف، دنباله لاگ و Vite را با هم از راه `@laravel/multiplex` بالا می‌آورد. این بسته باید در `node_modules/.bin` باشد. `npm ci` همان لینک را می‌سازد. اگر `php` داخل کانتینر باشد، پورت `artisan serve` الزاماً روی `127.0.0.1` مرورگر میزبان نیست.

صف پیکربندی‌شده `control` است. timeout ورکر را ۶۰ بگذارید تا از `retry_after` کوتاه‌تر بماند:

```bash
php artisan queue:work redis --queue=control --sleep=2 --tries=3 --timeout=60
php artisan schedule:run
```

زمان‌بند هر دقیقه `rahandaz:outbox:dispatch` را صدا می‌زند. با اجرای خاموش، worker ممکن است job را بردارد، ولی آن را عملیات موفق میزبان نمی‌کند.

آزمون، تشخیص وابستگی و سبک کد:

```bash
php artisan test
php artisan rahandaz:doctor
vendor/bin/pint --test
npm run lint
npx tsc --noEmit
npm run build
node --experimental-strip-types --test tests/js/datetime.test.ts
```

`php artisan test` از PostgreSQL جداگانه `rahandaz_test` استفاده می‌کند. SQLite جای قیدهای PostgreSQL را نمی‌گیرد. PHPStan نصب نیست. نتیجه آزمون‌های واقعی این برش در [گزارش آزمون](docs/V1_TEST_REPORT.md) ثبت می‌شود؛ CI جدید برای PHP 8.4 هنوز در GitHub اجرا نشده است.

<a id="layout"></a>
## ساختار مخزن

```text
rahandaz/
├── app/
│   ├── Console/Commands/     # doctor، کلید، مالک اولیه، outbox
│   ├── Domain/               # حساب، میزبان، Git، پروژه، job، backup
│   ├── Http/                 # کنترلر، FormRequest، میان‌افزار
│   ├── Infrastructure/       # رمز، executor خاموش، cutover شناسه
│   ├── Jobs/
│   ├── Models/
│   └── Policies/
├── bootstrap/
├── config/
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── docs/                     # طرح پایگاه، UI، پیشرفت، ADR
├── .docs/                    # مشخصات، معماری، توسعه، نقشه
├── infrastructure/           # عملیات، helper، init پایگاه توسعه
├── installer/                # نصب‌کننده؛ Release امضاشده ندارد
├── public/
├── resources/
│   ├── css/
│   ├── fonts/vazirmatn/
│   ├── js/                   # pages، layouts، components
│   └── views/
├── routes/
├── tests/
├── compose.dev.yml
├── composer.json
├── package.json
└── README.md
```

این‌ها در ریشه نیستند: `scripts/`، `LICENSE`، `SECURITY.md`، `CHANGELOG.md` و `CONTRIBUTING.md`. واحدهای systemd فقط قالب داخل `installer/templates/systemd/` هستند و روی میزبان نصب نشده‌اند. `infrastructure/operations/` و قرارداد `infrastructure/privilege_helper/` در مخزن هستند. daemon سوکت نصب نشده است. معماری هدف در [.docs/ARCHITECTURE.md](.docs/ARCHITECTURE.md) است.

<a id="updates"></a>
## به‌روزرسانی

به‌روزرسانی خودکار پنل پیاده‌سازی نشده و فرمانی برای self-update در Artisan نیست.

طرح نسخه ۱ این ترتیب را می‌خواهد: نسخه Release قابل بررسی، backup تأییدشده از پایگاه و کلید، مهاجرت سازگار، تعویض symlink نسخه، healthcheck، و مسیر برگشت کد. برگشت کد با مهاجرت ناسازگار یکی نیست. اگر اسکیما جلو رفته باشد، بازگشت فقط با برنامه بازیابی همان نسخه ممکن است. به‌روزرسانی نباید به همان worker که در حال خاموش شدن است وابسته باشد. تا وجود آن ابزار، فقط مسیر توسعه همین مخزن معتبر است: `composer install`، `npm ci`، `php artisan migrate`، و `npm run build`.

`php artisan rahandaz:doctor` بعد از بالا آمدن وابستگی‌ها این‌ها را گزارش می‌کند: وجود `APP_KEY`، قابل‌استفاده‌بودن کلید اصلی، canary، PostgreSQL، هر دو Redis، نسبت timeout صف، حالت اجرا، و مجوز فایل کلید. این فرمان نصب production را تأیید نمی‌کند.

<a id="backup"></a>
## پشتیبان و بازیابی

طرح، backup را برای پایگاه راه‌انداز، کلید اصلی و ماده بازیابی، پیکربندی متعلق به پنل، فراداده deployment، و backup دیتابیس پروژه‌ها در محدوده پشتیبانی لازم می‌داند. فایل backup داخل PostgreSQL ذخیره نمی‌شود. مسیر مجاز در اسکیما زیر `/var/backups/rahandaz/` است.

backup پایگاه به‌تنهایی credential رمزشده را برنمی‌گرداند. کلید اصلی باید جدا، با دسترسی محدود، و خارج از همان dump باشد. restore در اسکیما وضعیت تأیید جدا دارد. job اجرای restore نوشته نشده و restore آزمایش‌نشده را نباید تضمین‌شده دانست. backup روی همان دیسک سرور، بازیابی بعد از از دست رفتن دیسک نیست.

<a id="troubleshooting"></a>
## عیب‌یابی

| نشانه | بررسی |
| --- | --- |
| سیستم‌عامل غیر از Ubuntu هدف | نسخه ۱ آن را پشتیبانی نمی‌کند. وضعیت آزمون در [جدول سازگاری](#compatibility) است |
| کمبود RAM در نصب | نصب‌کننده زیر ۱ GiB را رد می‌کند و زیر ۲ GiB هشدار می‌دهد. این آستانه روی میزبان توسعه به‌صورت اعمال زنده اجرا نشده |
| قفل APT یا میرور بد | کاتالوگ پنل فقط خواندنی است. نصب‌کننده mirror APT امضاشده را در [docs/IRANIAN_MIRRORS.md](docs/IRANIAN_MIRRORS.md) دارد و در شکست امضا مسیر ناامن را ادامه نمی‌دهد |
| PHP به PostgreSQL وصل نمی‌شود | `pdo_pgsql` باید باشد. میزبان در `.env` باید از دید همان فرایند PHP قابل دسترس باشد |
| `rahandaz:doctor` برای Redis یا پایگاه fail می‌دهد | Compose یا سرویس محلی را ببینید. پورت صف ۶۳۷۹ و cache در نمونه توسعه ۶۳۸۰ است |
| `Refusing to overwrite an existing master key` | کلید قبلاً ساخته شده. فایل را پاک نکنید |
| `vite: not found` یا `multiplex: not found` | `npm ci` را دوباره اجرا کنید تا `node_modules/.bin` ساخته شود |
| worker job را موفق نمی‌کند | `RAHANDAZ_EXECUTION_MODE=disabled` است و این رفتار خواستهٔ فعلی است |
| ZIP پروژه آپلود نمی‌شود | وجود `ext-zip`/`fileinfo`، سقف‌های `RAHANDAZ_ARCHIVE_*` و محدودیت Nginx/PHP upload را بررسی کنید؛ ZIP خطرناک عمداً با خطای فرم رد می‌شود |
| webhook یا SSL کار نمی‌کند | وب‌هوک HTTP/worker و اعمال ACME/Nginx هنوز کامل نیستند؛ ثبت درخواست دامنه به‌معنی فعال‌شدن سایت نیست |
| ورود پنل ۴۰۴ است | مسیر `/rahandaz/login` است، نه `/login` و نه `/` |

فرمان تشخیص وابستگی توسعه:

```bash
php artisan rahandaz:doctor
```

<a id="docs"></a>
## مستندات

| موضوع | فایل |
| --- | --- |
| مشخصات محصول | [.docs/PROJECT_SPEC.md](.docs/PROJECT_SPEC.md) |
| معماری هدف | [.docs/ARCHITECTURE.md](.docs/ARCHITECTURE.md) |
| توسعه | [.docs/DEVELOPMENT.md](.docs/DEVELOPMENT.md) |
| نقشه راه | [.docs/ROADMAP.md](.docs/ROADMAP.md) |
| بازنگری خود اسناد | [.docs/CHANGELOG_DOCUMENTATION.md](.docs/CHANGELOG_DOCUMENTATION.md) |
| پیشرفت پیاده‌سازی | [docs/IMPLEMENTATION_PROGRESS.md](docs/IMPLEMENTATION_PROGRESS.md) |
| نصب | [docs/INSTALLATION.md](docs/INSTALLATION.md) |
| معماری نصب‌کننده | [docs/INSTALLER_ARCHITECTURE.md](docs/INSTALLER_ARCHITECTURE.md) |
| امنیت نصب | [docs/INSTALLER_SECURITY.md](docs/INSTALLER_SECURITY.md) |
| بازیابی نصب | [docs/INSTALLER_RECOVERY.md](docs/INSTALLER_RECOVERY.md) |
| Mirrorهای بررسی‌شده | [docs/IRANIAN_MIRRORS.md](docs/IRANIAN_MIRRORS.md) |
| سازگاری Ubuntu نصب‌کننده | [docs/UBUNTU_COMPATIBILITY.md](docs/UBUNTU_COMPATIBILITY.md) |
| ماتریس آزمون نصب | [docs/INSTALLER_TEST_MATRIX.md](docs/INSTALLER_TEST_MATRIX.md) |
| موتور عملیات | [docs/OPERATIONS.md](docs/OPERATIONS.md) |
| طرح پایگاه و ERD | [docs/DATABASE_SCHEMA.md](docs/DATABASE_SCHEMA.md)، [docs/DATABASE_ERD.md](docs/DATABASE_ERD.md) |
| امنیت داده | [docs/DATABASE_SECURITY.md](docs/DATABASE_SECURITY.md) |
| آپلود امن ZIP پروژه | [docs/PROJECT_ARCHIVE_UPLOADS.md](docs/PROJECT_ARCHIVE_UPLOADS.md) |
| وابستگی‌های قفل‌شده | [docs/DEPENDENCIES.md](docs/DEPENDENCIES.md) |
| تصمیم بسته رابط | [docs/FRONTEND_DEPENDENCIES.md](docs/FRONTEND_DEPENDENCIES.md) |
| هویت بصری و لوگو | [docs/BRAND_GUIDELINES.md](docs/BRAND_GUIDELINES.md) |
| اجزای رابط | [docs/FRONTEND_COMPONENTS.md](docs/FRONTEND_COMPONENTS.md)، [docs/UI_GUIDELINES.md](docs/UI_GUIDELINES.md) |
| موجودی صفحات | [docs/UI_PAGE_INVENTORY.md](docs/UI_PAGE_INVENTORY.md) |
| ADR مونولیت | [docs/adr/0001-laravel-modular-monolith.md](docs/adr/0001-laravel-modular-monolith.md) |
| ADR شناسه | [docs/adr/0004-bigint-primary-key-uuidv7-public-identifier.md](docs/adr/0004-bigint-primary-key-uuidv7-public-identifier.md) |

اسناد تکمیلی: [آمادگی انتشار](docs/V1_RELEASE_READINESS.md)، [ممیزی قابلیت‌ها](docs/V1_FEATURE_INVENTORY.md)، [آزمون‌ها](docs/V1_TEST_REPORT.md)، [معماری آموزش‌ها](docs/TUTORIALS_ARCHITECTURE.md)، [CLI](docs/CLI_REFERENCE.md)، [استراتژی استقرار](docs/DEPLOYMENT_STRATEGIES.md)، [امنیت](SECURITY.md)، [مشارکت](CONTRIBUTING.md) و [تغییرات](CHANGELOG.md). Benchmark منابع VM هنوز اجرا نشده است.

## تصاویر واقعی محیط آزمایشی

این تصاویر از Chrome، نشست واقعی Laravel و PostgreSQL ایزوله در ۲۰۲۶-۱۰-۱۴ گرفته شده‌اند؛ mockup نیستند. اجرای میزبان خاموش است؛ استقرار شکست‌خورده واقعاً توسط worker ثبت شده و نبود متریک با حالت خالی نمایش داده می‌شود. تصاویر تأیید نصب Production نیستند و اطلاعات کاربر واقعی ندارند.

| داشبورد | سرورها |
| --- | --- |
| ![داشبورد آزمایشی](docs/screenshots/dashboard.webp) | ![سرور آزمایشی](docs/screenshots/servers.webp) |
| پروژه‌ها | استقرارها |
| ![پروژه آزمایشی](docs/screenshots/projects.webp) | ![استقرار شکست‌خورده در حالت خاموش](docs/screenshots/deployments.webp) |
| دیتابیس‌ها | پایش |
| ![درخواست دیتابیس؛ بدون نمونه نصب‌شده](docs/screenshots/databases.webp) | ![پایش؛ بدون داده ساختگی](docs/screenshots/monitoring.webp) |

![مرکز آموزش‌ها](docs/screenshots/tutorials.webp)

## CLI موجود

در checkout از `bash installer/bin/rahandaz` استفاده کنید؛ نصب فرمان سراسری هنوز در VM تأیید نشده است. این‌ها فرمان واقعی‌اند، اما آزمون منطق به‌معنی موفقیت apply روی میزبان نیست.

| فرمان | محدوده تأیید |
| --- | --- |
| `rahandaz --help`، `rahandaz version` | خروجی محلی |
| `rahandaz status`، `rahandaz doctor` | تشخیص؛ doctor نبود dependency/نصب را شکست می‌دهد |
| `rahandaz update check`، `rahandaz update history` | metadata/history؛ fixture، نه انتشار زنده |
| `rahandaz update --dry-run` همراه ورودی‌های Release معتبر | قرارداد manifest/امضا/checksum؛ harness |
| `rahandaz update rollback`، `rahandaz repair` | منطق کنترل‌شده؛ بازیابی DB خودکار نیست و VM تأیید نشده |

فرمان‌های PHP/install/service/reboot عمومی CLI هنوز ارائه نمی‌شوند. گزینه‌ها و محدودیت‌ها در [CLI_REFERENCE](docs/CLI_REFERENCE.md) هستند.

<a id="roadmap"></a>
## نقشه راه

تقسیم نسخه‌ها همان [.docs/ROADMAP.md](.docs/ROADMAP.md) است.

نسخه ۱ مدیریت همان سرور Ubuntu است: نرم‌افزار، سرویس، پروژه، استقرار، دامنه، TLS، دیتابیس، backup، پایش و به‌روزرسانی همان میزبان. مرحله ۰ هسته در مخزن هست. مرحله ۱ رابط هنوز با بازبینی viewport بسته نشده. مرحله‌های نصب، Git، سرویس و انتشار عملیاتی مانده‌اند.

نسخه ۲ چند سرور مورد اعتماد، اتصال امن، inventory و credential مقصد است. Ansible فقط بعد از ADR و آزمون هزینه و امنیت مطرح می‌شود.

نسخه ۳ agent توزیع‌شده، API اتوماسیون، provider ابری و telemetry گسترده‌تر است و بعد از تثبیت امنیت نسخه ۱ می‌آید.

<a id="contributing"></a>
## مشارکت

راهنمای [CONTRIBUTING.md](CONTRIBUTING.md) را بخوانید. پیش از تغییر، [.docs/PROJECT_SPEC.md](.docs/PROJECT_SPEC.md)، [.docs/ARCHITECTURE.md](.docs/ARCHITECTURE.md) و [docs/IMPLEMENTATION_PROGRESS.md](docs/IMPLEMENTATION_PROGRESS.md) مرجع‌اند. `AGENTS.md` قواعد کار داخل مخزن را خلاصه کرده است.

- باگ و پیشنهاد را با رفتار فعلی جدول وضعیت بسنجید. قابلیت برنامه‌ریزی‌شده را باگ نبود پیاده‌سازی ندانید.
- تغییر میزبان، systemd و installer بدون قرارداد و آزمون ایمنی اضافه نشود. حالت اجرا پیش‌فرض `disabled` بماند.
- راز، توکن و رمز در commit، fixture و props اینرسی نیاید.
- شناسه عمومی `uuid` بماند و `id` عددی در JSON عمومی ظاهر نشود.
- برای PHP، `vendor/bin/pint --test` و `php artisan test`. برای رابط، `npm run lint`، `npx tsc --noEmit` و `npm run build`.
- موفقیت نصب، استقرار یا پایش را در UI قبل از نتیجه واقعی job نشان ندهید.

<a id="license"></a>
## مجوز

این پروژه با انتخاب مالک تحت [Apache License 2.0](LICENSE) منتشر می‌شود. مجوزهای dependencyها، فونت و دارایی‌های ثالث مستقل‌اند و باید حفظ شوند. انتخاب مجوز به معنی گواهی امنیت، پشتیبانی Production یا تکمیل قابلیت‌های برنامه نیست.
