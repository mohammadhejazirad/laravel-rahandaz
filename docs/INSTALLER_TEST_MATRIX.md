# ماتریس آزمون نصب‌کننده

آخرین تکرار harness در برش صفحات نسخه اول: ۶۹ passed/۰ failed، شامل updater با ۲۱ passed/۰ failed. full application suite روی PG/Redis توسعه نیز اجرا شد؛ این نتیجه نصب fresh یا PHP 8.4/Boot در VM نیست. شواهد و محدودیت در V1_TEST_REPORT و V1_RELEASE_READINESS.

تاریخ اجرای تاریخی: ۲۰۲۶-۱۰-۱۳. ابزاری که در محیط نبود اجرا نشده است: `shellcheck`، `shfmt`، `bats`. workflowهای `.github/workflows/installer-test.yml` و `release-build.yml` در GitHub اجرا نشده‌اند. در برش تازهٔ ۲۰۲۶-۱۰-۱۴، release-build به build candidate/Draft اختیاری تغییر کرد و stable/public نمی‌سازد. نبود کلید Production و مجوز ساخت Draft را متوقف می‌کند.

آخرین نتیجه: installer **۷۰ passed/۰ failed** (updater زیرمجموعه ۲۱/۰)، bootstrap/release **۲۶ passed/۰ failed** روی میزبان و هر سه Ubuntu container-lab. جزئیات، شکست‌های اولیهٔ اصلاح‌شده و شواهد در [RELEASE_PIPELINE_TEST_REPORT.md](RELEASE_PIPELINE_TEST_REPORT.md). نصب fresh/health/boot در VM همچنان تأیید نشده است.

| آزمون | محیط | وضعیت | شاهد |
| --- | --- | --- | --- |
| منطق نصب‌کننده | میزبان توسعه، `bash installer/tests/run.sh` | passed | ۶۳ مورد، ۰ شکست. شامل bash -n، allowlist بسته، symlink سیستم‌عامل، رد secret در state، fixture ایران، retry دانلود، رد TLS ناامن، امضای RSA، حذف artifact پس از checksum ناسازگار، رد `.env` در بسته، رد مسیر `..` در tar، plan بدون `[OK]`، jammy با `PHP_BASELINE_UNAVAILABLE`، apply آفلاین با خروج ۱۰ |
| حفظ کلید در اجرای دوباره | sysroot زیر `/tmp` در همان harness | passed | هش `master.key` در اجرای دوم یکی ماند و مرحله ۰۶ `[SKIP]` شد. این نصب زنده نیست |
| خوشامد پیش از health | همان sysroot | passed | `welcome/index.html` نوشته نشد |
| لاگ بدون کلید | همان harness | passed | هش و بایت کلید در stderr نبود |
| طرح 22.04 | کانتینر `rahandaz-ubuntu-lab-ubuntu-22-1` | passed به‌عنوان شکست مورد انتظار | خروج ۱ و `PHP_BASELINE_UNAVAILABLE` |
| طرح 24.04 | `rahandaz-ubuntu-lab-ubuntu-24-1` | passed به‌عنوان plan | خروج ۰ و `RESULT plan`. بسته‌ای نصب نشد |
| طرح 26.04 | `rahandaz-ubuntu-lab-ubuntu-26-1` | passed به‌عنوان plan | خروج ۰ و `RESULT plan`. بسته‌ای نصب نشد |
| fallback امضای mirror | fixture در harness | passed | mirror پین‌شده سقوط نمی‌کند. شکست همهٔ امضاها `MIRROR_SIGNATURE_INVALID` می‌ماند |
| شبکهٔ محدود زنده | — | not_run | فقط fixture اجرا شد |
| `SecretsAndBootstrapTest` برای `--installer` | PHPUnit با PHP لرد | not_run | اتصال به `192.0.2.1:5432` پایگاه `rahandaz_test` رد شد. assertion اجرا نشد. پایگاه آزمون روشن نشد |
| apply روی `/` | میزبان توسعه | not_run | root و Release امضاشده لازم است. اعمال زنده انجام نشد |
| health `/up` و `/rahandaz/login` | — | not_run | مرحله ۱۴ در plan فقط متن طرح است |
| ساخت مالک، ورود، داشبورد | — | not_run | |
| صف و scheduler واقعاً فعال | — | requires_vm | قالب unit در apply آفلاین نوشته می‌شود. `systemctl enable` در کانتینر صدا زده نمی‌شود |
| reboot و ماندگاری سرویس | — | requires_vm | |
| نصب دوباره روی سرور واقعی | — | not_run | resume فقط در sysroot موقت harness دیده شد |
| گردش GitHub | — | not_run | فایل workflow هست و job اجرا نشده |
| منطق نصب‌کننده پس از PHP 8.4 | میزبان توسعه، ۲۰۲۶-۱۰-۱۴، `bash installer/tests/run.sh` | passed | ۶۹ مورد و ۰ شکست. شامل سوئیت به‌روزرسانی با ۲۱ مورد و ۰ شکست |
| طرح 22.04 / 24.04 / 26.04 پس از پین Sury | کانتینر موقت از ایمیج `rahandaz-ubuntu-lab`، ۲۰۲۶-۱۰-۱۴ | passed به‌عنوان plan | هر سه خروج ۰، `RESULT plan`، مسیر `/usr/bin/php8.4`. بسته‌ای نصب نشد. کانتینر نام‌دار بازسازی نشد |
| `tests/Unit/UpdateSystemTest.php` | PHP میزبان ۸٫۵٫۱۱ | passed | ۱۶ آزمون و ۵۱ assertion. `Http::fake`. درخواست زنده GitHub نبود |
| `composer check-platform-reqs` | PHP میزبان و PHP لرد، هر دو ۸٫۵٫۱۱ | passed برای require کامپوزر | خروج ۰. `pdo_pgsql` در require نیست. میزبان آن extension را ندارد |
| نصب زنده، health، صف، scheduler، VM | — | not_run / requires_vm | اعمال نشد |
| workflow `php-84.yml` | GitHub Actions | not_run | فایل اضافه شد و job اجرا نشد |

خروج ۱۰ در apply آفلاین خواستهٔ harness است: systemd و پایگاه و PHP و health در آن sysroot `not_run` می‌مانند. این خروج موفقیت نصب نیست. apply به‌روزرسانی آفلاین هم خروج ۱۰ و `incomplete` است و symlink `current` را عوض نمی‌کند.

آمادگی Release عمومی: آماده نیست. کلید اعتماد راه‌انداز منتشر نشده، artifact امضاشده ساخته نشده، healthcheck زنده اجرا نشده، و نصب PHP 8.4 روی Ubuntu هدف فقط در حد طرح است.

## آماده‌سازی تعاملی وابستگی‌ها — ۲۰۲۶-۱۰-۱۷

ورودی تازه `installer/bin/rahandaz-prerequisites` با قرارداد مستقل v1 است؛ نصب خود پنل و
دروازهٔ Release امضاشده را دور نمی‌زند. قرارداد و recovery در `INSTALLER_PREREQUISITES.md` است.
تمام آزمون‌های Docker این برش در `tests/infrastructure/ubuntu-lab/` و کانتینرهای موقت بدون
privileged، host namespace یا mount قابل‌نوشتن میزبان اجرا شدند. هیچ بسته‌ای روی میزبان نصب نشد.

- منطق جدید: **۳۸ آزمون موفق، صفر شکست** روی میزبان و هر سه Ubuntu 22.04/24.04/26.04 در
  container-lab. شامل ترتیب تأیید منابع/سؤال ایران، EOF و ورودی نامعتبر، رد OS/معماری، وضعیت
  unpacked، بازیابی با اجازه، سیاست مخزن، تازگی metadata، policy خارجی، سرویس masked/فعال و پورت اشغال است.
- نصب واقعی بسته‌ها، بررسی extensionهای PHP 8.4، `nginx -t` و `php-fpm8.4 -t`، اجرای مجدد،
  unpack عمدی ACL در کانتینر، رد recovery بدون اجازه و بازیابی با اجازه روی هر سه هدف اجرا شد.
  حفظ hash تنظیمات موجود Nginx/Redis و sourceهای اولیهٔ Ubuntu نیز بررسی شد. هر اجرای application
  prerequisite در کانتینر عمداً خروج **۱۰ / services_require_vm** دارد؛ harness برای این نتیجهٔ مورد
  انتظار خروج صفر می‌دهد. این موفقیت نصب/فعال‌سازی پنل یا سرویس‌های systemd نیست.
- دریافت واقعی indexهای Ubuntu از Mirror ایرانی Liara برای هر سه codename همراه signature/TLS،
  updates/security و محدودیت سن metadata موفق بود. این از شبکهٔ فعلی آزمایشگاه بود؛ شبکهٔ محدود VPS
  داخل ایران، دانلود artifact و نصب کامل از Mirror ایرانی گواهی نشده‌اند. fallback سایر providerها fixture است.
- regression نصب‌کننده: **۷۱ passed / صفر failed**؛ updater همان زیرمجموعهٔ **۲۱/۰** است و دوباره
  جمع نمی‌شود. regression بسته‌سازی/bootstrap: **۲۶ آزمون موفق**. بررسی Bash syntax، ASCII پیام‌ها،
  parse فایل‌های YAML و `git diff --check` موفق بود. shellcheck/shfmt و اجرای remote Actions انجام نشدند.

نسخه‌های واقعاً دریافت‌شده در این اجرا: PHP 8.4.26 برای هر سه؛ Ubuntu 22.04 با Nginx 1.18.0،
PostgreSQL 14.24 و Redis 6.0.16؛ Ubuntu 24.04 با Nginx 1.24.0، PostgreSQL 16.15 و Redis 7.0.15؛
Ubuntu 26.04 با Nginx 1.28.3، PostgreSQL 18.6 و Redis 8.0.5 (revisionهای امنیتی Ubuntu در لاگ خام).
این ثبت تاریخی است؛ قرارداد نسخهٔ یکسان همهٔ runtimeها یا pinned patch دائمی نیست.

شواهد محلی (عمداً gitignored):

- fixture نهایی هر سه: `tests/infrastructure/ubuntu-lab/reports/prerequisites-{22.04,24.04,26.04}-20261017T084148Z/`
- نصب/rerun/recovery 22.04: `tests/infrastructure/ubuntu-lab/reports/prerequisites-22.04-20261017T084027Z/`
- نصب/rerun/recovery 24.04 و 26.04: `tests/infrastructure/ubuntu-lab/reports/prerequisites-{24.04,26.04}-20261017T083731Z/`
- indexهای Mirror ایرانی هر سه: `tests/infrastructure/ubuntu-lab/reports/prerequisites-{22.04,24.04,26.04}-20261017T083556Z/`

شکست‌های اولیه صریحاً ثبت می‌شوند: pin قبلی PHP، `php-common` و در jammy `libgd3` لازم را
مسدود می‌کرد. در source اختصاصی این فاز فقط همین استثناهای نام‌دار مجاز شدند؛ همهٔ بسته‌های
دیگر آن origin همچنان مسدودند. pin سراسری نصب‌کنندهٔ کامل در این برش بازنویسی نشد. در harness
22.04 نیز checksum اولیه فقط `sources.list.d` را می‌دید و فایل legacy `sources.list` جا افتاده بود؛
پس از اصلاح harness، اجرا تکرار شد. شکست‌ها به‌عنوان passed شمارش نشده‌اند.

**Matrix VM برای 22.04، 24.04 و 26.04: not_run / requires_vm.** فعال‌سازی واقعی systemd،
health زندهٔ PostgreSQL/Redis/Nginx، reboot، حفظ runtime پروژه‌های موجود، نصب کامل پنل و شبکهٔ
محدود ایران گواهی نشده‌اند. این برش production-ready یا supported بودن نصب کامل را اعلام نمی‌کند.
