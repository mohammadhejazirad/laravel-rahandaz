# ماتریس آزمون به‌روزرسانی

تاریخ ۲۰۲۶-۱۰-۱۴. `shellcheck`، `shfmt` و `bats` نصب نبودند. workflow گیت‌هاب اجرا نشد.

| مورد | وضعیت | شاهد |
| --- | --- | --- |
| مقایسه SemVer عددی | passed | `1.10` بزرگ‌تر از `1.9` در `installer/tests/update.sh` و PHPUnit |
| بدون کاندید | passed | `update_available no` |
| نسخه نامعتبر و artifact غایب | passed | رد پیش از آماده‌سازی |
| امضای خراب | passed | برگرداندن بایت میانی، `SIGNATURE_INVALID` |
| checksum خراب | passed | همان نام manifest، حذف فایل، بدون فعال‌سازی |
| dry-run | passed | Release آماده نشد |
| کانال stable در برابر beta | passed | رد شد |
| schema مخرب | passed | پیش از آماده‌سازی رد شد |
| قفل هم‌زمان | passed | `UPDATE_LOCKED` |
| apply آفلاین و resume | passed | خروج ۱۰، `current` روی 1.0.0، آماده بودن 1.2.3، اجرای دوم `already prepared` |
| تاریخچه و کلید پشتیبان | passed | حالت `0600`. مقدار کلید در لاگ نبود |
| rollback برنامه | passed | فقط symlink. خروج ۱۰ |
| بازیابی پایگاه | passed به‌عنوان رد | `manual_recovery_required`. داده آزمون حذف نشد |
| repair | passed | سایت و pool مشتری بدون تغییر ماندند |
| دانلود ناتمام | passed | `PARTIAL` با curl ساختگی |
| دوری از سرویس دیگران | passed | نبود `apt-get upgrade`، `update-alternatives`، restart سراسری nginx یا php، `migrate:fresh` و `DROP DATABASE` |
| طرح Ubuntu 22.04/24.04/26.04 | passed به‌عنوان plan | خروج ۰. نصب بسته نشد |
| بررسی GitHub | passed با `Http::fake` | ۱۶ آزمون PHPUnit. شبکه زنده نبود |
| رابط | passed برای lint و typecheck و build | مرورگر باز نشد |
| health زنده، صف، scheduler، VM، SSH | not_run یا requires_vm | اجرا نشد |
| `pg_dump` واقعی | not_run | فقط در برنامه پشتیبان نوشته شد |

هیچ ردیف این جدول به‌معنی production-ready بودن به‌روزرسانی نیست.
