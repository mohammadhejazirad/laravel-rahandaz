# ماتریس آزمون نصب‌کننده

تاریخ اجرا: ۲۰۲۶-۱۰-۱۳. ابزاری که در محیط نبود اجرا نشده است: `shellcheck`، `shfmt`، `bats`. workflowهای `.github/workflows/installer-test.yml` و `release-build.yml` نوشته شده‌اند و اجرا نشده‌اند. `release-build.yml` انتشار را عمداً انجام نمی‌دهد و نبود `installer/trust/rahandaz-release.pub` را چک می‌کند.

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

خروج ۱۰ در apply آفلاین خواستهٔ harness است: systemd و پایگاه و PHP و health در آن sysroot `not_run` می‌مانند. این خروج موفقیت نصب نیست.

آمادگی Release عمومی: آماده نیست. کلید اعتماد منتشر نشده، artifact برنامه با vendor و asset ساخته‌شده امضا نشده، healthcheck اجرا نشده، و 22.04 برای PHP توزیع بسته است.
