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
