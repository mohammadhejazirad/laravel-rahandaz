# سازگاری Ubuntu برای نصب‌کننده

هدف نسخه: Ubuntu Server 22.04، 24.04 و 26.04، معماری `amd64`. تولید بدون Docker است. نتیجهٔ کانتینر گواهی VM یا نصب production نیست. خانه‌های `docs/OPERATIONS_TEST_MATRIX.md` همچنان `untested` هستند.

| Ubuntu | Codename | PHP توزیع | طرح نصب‌کننده در کانتینر آزمایشگاه ۲۰۲۶-۱۰-۱۳ | نصب کامل کنترل‌پلین |
| --- | --- | --- | --- | --- |
| 22.04 | jammy | 8.1، زیر `^8.3` | خروج ۱، `PHP_BASELINE_UNAVAILABLE` | پشتیبانی نمی‌شود تا مخزن PHP امضاشده تأیید شود |
| 24.04 | noble | 8.3 در مشاهدهٔ آزمایشگاه عملیات | خروج ۰، `RESULT plan` | `not_run` |
| 26.04 | resolute | 8.5 در مشاهدهٔ آزمایشگاه عملیات | خروج ۰، `RESULT plan` | `not_run` |

طرح روی هر سه تصویر این تعارض‌ها را چاپ کرد، چون آزمایشگاه عملیات از قبل nginx، postgresql، redis-server، php-fpm و php-cli دارد: سرویس حذف نشد و plan ادامه پیدا کرد. RAM دیده‌شده در کانتینر حدود ۳۰ GiB و دیسک آزاد حدود ۹۹ GiB بود. pid 1 برابر `sleep infinity` بود، پس systemd فعال نشد.

`/etc/os-release` در هر سه تصویر و روی میزبان توسعه symlink به `/usr/lib/os-release` است. نصب‌کننده فقط همین هدف را می‌پذیرد. symlink دیگر کد `UNSUPPORTED_OS` می‌گیرد. فایل parse می‌شود و source نمی‌شود.

حداقل منابع در کد: RAM زیر ۱ GiB شکست، زیر ۲ GiB هشدار، دیسک آزاد زیر ۵ GiB شکست. این آستانه روی میزبان توسعه به‌صورت نصب زنده اجرا نشد.

معماری غیر از `amd64` کد `UNSUPPORTED_ARCHITECTURE` می‌گیرد، حتی اگر InRelease آن معماری را فهرست کند.

آزمون VM برای systemd به‌عنوان pid 1، SSH، UFW، reboot و ماندگاری سرویس `requires_vm` است و اجرا نشده است.
