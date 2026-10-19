# بازیابی نصب

وضعیت در `${INSTALLER_STATE_DIR}/state` است. برای apply مسیر پیش‌فرض `/var/lib/rahandaz/installer` است. برای plan یک دایرکتوری موقت است. لاگ کنار state در `installer.log` با حالت `0600` است.

## کد خروج فرایند

| کد | معنی |
| --- | --- |
| ۰ | `result=success` یا `result=plan` |
| ۱ | شکست مرحله، یا doctor وقتی نصب کامل نیست |
| ۲ | آرگومان نامعتبر |
| ۱۰ | `result=incomplete`. مرحله‌ای `not_run` یا ناموفق مانده و موفقیت اعلام نشده |

تابع مرحله اگر ۰ برگرداند، در apply وضعیت `succeeded` می‌گیرد. در plan همان مرحله `planned` است و برچسب `[OK]` نمی‌گیرد. بازگشت ۲ از خود تابع یعنی `not_run`: کار در این محیط قابل تأیید نیست و موفقیت کل نیست. این بازگشت کد خروج فرایند را ۲ نمی‌کند. هر وضعیت دیگر `failed` است. کد خطایی که خود مرحله در state نوشته حفظ می‌شود. اگر مرحله پیش از `installer_error` بمیرد، کد `STAGE_FAILED` می‌ماند.

## ادامه

همان فرمان `--apply` اگر مرحله‌ای را `succeeded` دیده باشد آن را با `[SKIP]` رد می‌کند و پیام `CONTINUE` می‌دهد. `--resume` همان رفتار را دارد. `--repair` فقط مرحله‌های علامت‌خورده را دوباره اجرا می‌کند: مخزن، Redis، تنظیم Laravel، Nginx/PHP-FPM، systemd و خلاصه. repair به نصب تازه یا حذف پایگاه تبدیل نمی‌شود.

اگر نصب قبلاً `success` شده باشد و artifact جدیدتر باشد، همان فرمان فقط نسخه را کنار نسخهٔ فعلی آماده می‌کند. symlink فعلی و دادهٔ PostgreSQL عوض نمی‌شوند و فعال‌سازی به healthcheck روی VM موکول است. نسخهٔ برابر دوباره نصب نمی‌شود.

upgrade کلید اصلی و حساب مالک را بازنویسی نمی‌کند. migration در upgrade به `--approve-migrations` نیاز دارد.

اگر state فایل symlink باشد، `STATE_CORRUPT` است و نصب ادامه پیدا نمی‌کند. doctor وقتی فایل state نیست `not_installed` چاپ می‌کند و دایرکتوری state روی میزبان نمی‌سازد.

## کدهایی که اپراتور می‌بیند

| کد | بازیابی |
| --- | --- |
| `UNSUPPORTED_OS` | شناسهٔ سیستم Ubuntu نیست یا os-release قابل اعتماد نیست |
| `UBUNTU_NOT_RECOMMENDED` | نصب و به‌روزرسانی فقط Ubuntu 22.04 و 24.04 LTS را تغییر می‌دهند. 26.04 پیش از هر تغییری متوقف می‌شود |
| `UNSUPPORTED_ARCHITECTURE` | فقط `amd64` / `x86_64` در این نسخه آزمون شده |
| `PHP_BASELINE_UNAVAILABLE` | Ubuntu خارج از jammy و noble و resolute. برای این سه نسخه PHP 8.4 فقط از pin امضاشده Sury می‌آید. مخزن بی‌امضا یا `trusted=yes` اضافه نکنید |
| `LOW_MEMORY` | کمتر از ۱ GiB. زیر ۲ GiB فقط هشدار است |
| `DISK_FULL` | کمتر از ۵ GiB آزاد |
| `EXISTING_SERVICE` | سرویس را پاک نکنید. یا کنار آن با `--acknowledge-existing` ادامه دهید، یا نصب را متوقف کنید |
| `MIRROR_SIGNATURE_INVALID` | منبع را ناامن نکنید. اتصال یا mirror امضاشده را درست کنید و `--resume` |
| `TLS_VERIFY_FAILED` | گواهی یا ساعت را درست کنید. retry ناامن وجود ندارد |
| `TRUST_ROOT_MISSING` | کلید عمومی مورد اعتماد را جدا بیاورید |
| `UNVERIFIED_ARTIFACT` | artifact، manifest و امضا باید هر سه حاضر و سازگار باشند |
| `PACKAGES_NOT_INSTALLED` | apply آفلاین یا sysroot آزمایشی apt را صدا نمی‌زند |
| `DATABASE_PASSWORD_UNKNOWN` | رمز موجود را نچرخانید. فایل `0600` یا `.env` فعلی را بازیابی کنید |
| `MIGRATION_REFUSED` | برای پایگاه از پیش موجود تأیید صریح لازم است |
| `MIGRATION_FAILED` | پایگاه drop نشده. خطا را رفع و `--resume` کنید |
| `NGINX_TEST_FAILED` | سایت راه‌انداز از sites-enabled برداشته شده. پیکربندی را اصلاح کنید |
| `HEALTHCHECK_FAILED` | خوشامد منتشرشدهٔ ناقص حذف می‌شود. سرویس را درست و `--resume` کنید |
| `OWNER_FAILED` | فایل اعتبار بازنویسی نمی‌شود. دستور bootstrap را جدا بررسی کنید |
| `PRIVILEGE_REQUIRED` | apply روی `/` root می‌خواهد |
| `LIVE_APPLY_REFUSED` | sysroot باید `/` یا دایرکتوری آزمون زیر `/tmp` باشد |

قطع شبکه، قفل APT، reboot وسط کار و دیسک پر با همین state قابل ادامه‌اند، به شرطی که مرحلهٔ موفق دوباره راز نسازد. مرحله‌ای که اصلاً تمام نشده در resume دوباره اجرا می‌شود.

doctor:

```bash
bash installer/bin/rahandaz-install --doctor
```

این فرمان healthcheck زندهٔ HTTP نیست. موفقیت doctor یعنی stageهای ذخیره‌شده همگی `succeeded` هستند. تا آن وضعیت ثبت نشود، doctor نصب را تأیید نمی‌کند.


## خطای storage، welcome یا health در نصب beta14

اگر PHP-FPM در نوشتن `storage/logs` یا compiled views خطای Permission denied داد، فقط shared/storage را writable نکنید: بسته ممکن است storage واقعی داخل release داشته باشد. installer اصلاح‌شده مسیر واقعی را در محدودهٔ مجاز بررسی، دسترسی service را فقط روی runtime/cache تنظیم و symlink ناشناخته را رد می‌کند. `chmod -R 777` یا service-owned کردن کل برنامه راه بازیابی نیست. resume پیش از health این دسترسی‌ها را دوباره اصلاح می‌کند.

welcome عمومی باید برای Nginx خواندنی باشد؛ پوشهٔ 0700 و فایل 0600 با موفقیت نصب سازگار نیست. helper جدید directory متعلق به root:www-data با 0750 و HTML با 0640 می‌سازد. private storage نباید برای رفع مشکل welcome عمومی شود. مسیر SSH tunnel باید با Host localhost و 127.0.0.1 به سایت پنل برسد و سایت پیش‌فرض میزبان بی‌اجازه حذف نشود.

نمونهٔ اجراشده، backup خصوصی، verification بستهٔ اصلی و محدودیت‌ها در [گزارش بازیابی VPS](IRAN_VPS_INSTALL_RECOVERY_2026_10_19.md) است. patch سورس یا این runbook، artifact عمومی beta14 را عوض نمی‌کند؛ برای سرور دیگر از installer اصلاح‌شده و artifact با امضا/checksum معتبر استفاده شود.
