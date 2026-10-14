# CLI واقعی راه‌انداز

ورودی اپراتور `installer/bin/rahandaz` است؛ این Bash CLI به نسخه Laravel در حال تعویض وابسته نیست و ExecutionGate پنل را باز نمی‌کند. در checkout فرمان را با `bash installer/bin/rahandaz …` اجرا کنید. نصب `/usr/local/bin/rahandaz` در VM هنوز تأیید نشده است.

| فرمان موجود | نتیجه و محدودیت |
| --- | --- |
| `--help` / `help` | راهنمای ASCII، خروج ۰ |
| `version` | نسخه backend؛ در توسعه `1.0.0 / development`، نه Release رسمی |
| `status` | metadata نسخه و PHP binary؛ نبود binary با `not_installed`، نه سلامت موفق |
| `doctor` | preflight نصب/PHP؛ نبود پیش‌نیاز شکست است |
| `update check` | بررسی metadata، نبود منبع معتبر/شبکه خطاست |
| `update history` | تاریخچه همان نصب، بدون secret |
| `update --dry-run` | بررسی ورودی Release/manifest/امضا/checksum/metadata؛ URL پیش‌فرض ندارد |
| `update` | apply فقط با Release معتبر و تایید migration؛ هنوز گواهی VM ندارد |
| `update rollback` | بازگشت برنامه، نه بازیابی پایگاه؛ recovery DB دستی و تاییدشده لازم است |
| `repair` | repair محدود خود نصب، نه پروژه‌های کاربران؛ VM نیاز دارد |

ورودی update شامل `--version`، `--manifest`، `--signature`، `--public-key`، `--artifact`، `--metadata` و `--metadata-signature` است. کلید عمومی باید از قبل مورد اعتماد باشد؛ دریافت کلید از همان دانلود، اعتماد ایجاد نمی‌کند. مسیر عمومی دانلود تا Release معتبر ثبت نمی‌شود. `--approve-migrations` جای backup/recovery نیست. `--approve-database-recovery` فعلاً بازیابی PostgreSQL انجام نمی‌دهد و وضعیت `manual_recovery_required` را پنهان نمی‌کند.

کد خروج ۰ موفقیت محدوده فرمان است؛ مثلاً status خواندن metadata است. خطاها غیرصفر و apply ناقص با وضعیت incomplete گزارش می‌شود. جزئیات سناریوها در `installer/tests/run.sh` و `installer/tests/update.sh` است. این harness با fixture/sandbox است، نه نصب واقعی.

Artisan داخلی موجود: `rahandaz:doctor`، `rahandaz:bootstrap-owner`، `rahandaz:keys:init`، `rahandaz:outbox:dispatch`، `rahandaz:updates:check` و `rahandaz:metrics:prune`. retention متریک پیش‌فرض هفت روز، حذف در batchهای ۱۰۰۰تایی با سقف ۱۰۰ batch در هر اجرا و گزینه `--dry-run` دارد. زمان‌بند آن روزانه است؛ backup artifact یا audit را حذف نمی‌کند.

فرمان عمومی `install/info/service/server/php/jobs/logs/backup` در این CLI نیست؛ از فهرست پیشنهادی، فرمان قابل اجرا جعل نشده است. reboot/shutdown واقعی انجام نشده و نیازمند VM ایزوله، authorization و تایید صریح است.
