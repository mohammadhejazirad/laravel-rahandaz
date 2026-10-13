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

`--resume` مرحلهٔ `succeeded` را با `[SKIP]` رد می‌کند. `--repair` فقط مرحله‌های علامت‌خورده را دوباره اجرا می‌کند: مخزن، Redis، تنظیم Laravel، Nginx/PHP-FPM، systemd و خلاصه. repair به نصب تازه یا حذف پایگاه تبدیل نمی‌شود.

upgrade کلید اصلی و حساب مالک را بازنویسی نمی‌کند. migration در upgrade به `--approve-migrations` نیاز دارد.

اگر state فایل symlink باشد، `STATE_CORRUPT` است و نصب ادامه پیدا نمی‌کند. doctor وقتی فایل state نیست `not_installed` چاپ می‌کند و دایرکتوری state روی میزبان نمی‌سازد.

## کدهایی که اپراتور می‌بیند

| کد | بازیابی |
| --- | --- |
| `UNSUPPORTED_OS` | فقط Ubuntu 22.04 jammy، 24.04 noble و 26.04 resolute |
| `UNSUPPORTED_ARCHITECTURE` | فقط `amd64` / `x86_64` در این نسخه آزمون شده |
| `PHP_BASELINE_UNAVAILABLE` | 22.04 با PHP توزیع پنل را میزبانی نمی‌کند. مخزن PHP ثالث اضافه نکنید |
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
