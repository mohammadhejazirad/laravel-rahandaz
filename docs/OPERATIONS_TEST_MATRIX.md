# ماتریس آزمون عملیات

تاریخ این ثبت ۲۰۲۶-۱۰-۱۲ است. میزبان توسعه این مخزن یک Ubuntu Server آزمایشی نیست. هیچ خانه‌ای را `supported` نکنید.

| موضوع | 22.04 jammy | 24.04 noble | 26.04 resolute |
| --- | --- | --- | --- |
| تشخیص os-release در fixture | تجزیه شد، سازگاری untested | تجزیه شد، سازگاری untested | تجزیه شد، سازگاری untested |
| apt و نام بسته | untested | untested | untested |
| systemd و سرویس واقعی | untested | untested | untested |
| OpenSSH و harden واقعی | untested | untested | untested |
| Nginx واقعی | untested | untested | untested |
| PHP-FPM | untested | untested | untested |
| Redis | untested | untested | untested |
| PostgreSQL | untested | untested | untested |
| MySQL و MariaDB | untested | untested | untested |
| MongoDB | untested | untested | untested |
| Node.js | untested | untested | untested |
| UFW | untested | untested | untested |
| Supervisor | untested | untested | untested |
| Certbot یا کلاینت ACME | untested | untested | untested |

## آنچه روی همین ماشین اجرا شد

`bash infrastructure/operations/tests/run.sh` خروج ۰ داشت. این آزمون مخرب نیست:

- `bash -n` برای هر اسکریپت درخت عملیات
- facts با fixtureهای 22.04 و 24.04 و 26.04: وضعیت عملیات `succeeded`، `recognized` درست، `compatibility` برابر `untested`
- رد کلید خصوصی، توقف nginx، reboot، بسته نیازموده، جهش زنده، پایگاه `rahandaz`، اسکریپت root، تزریق نام عملیات، helper برای اسکریپت سفارشی
- نوشتن سایت Nginx داخل sandbox، رد `..`، و حذف فایل وقتی `nginx -t` شکست می‌خورد
- رد crontab، شناسه اجرا، عملیات ناشناس، flush فایروال، symlink ورودی و symlink sandbox
- preview و apply ناقص SSH بدون نوشتن تنظیم
- کاتالوگ fixture با `verification` برابر `vm-passed` به mock دستور apt رسید. کاتالوگ تولید این مقدار را ندارد و آن mock آزمون VM نیست
- drop پایگاه غیرمحافظ بدون approval به psql نرسید
- تعویض symlink استقرار داخل sandbox
- archive extraction آینده: Zip Slip، symlink/reparse، inode/disk budget، digest mismatch و cleanup staging؛ هنوز در registry/VM اجرا نشده است
- اسکریپت سفارشی با UID 1000 و رد همان عملیات وقتی helper تأیید شده باشد
- checkout بدون شبکه
- `LocalOperationProcess` روی `/usr/bin/php` برای `host.facts` فقط‌خواندنی

`XDEBUG_MODE=off php artisan test --compact tests/Unit/OperationsEngineTest.php` شش آزمون را گذراند و آزمون facts را skip کرد، چون PHP داخل Lerd باینری bash ندارد. دروازه اجرا در این آزمون بسته ماند.

## آنچه اجرا نشد

`shellcheck`، `shfmt` و `bats` نصب نیستند. reboot واقعی، تغییر فایروال، harden واقعی SSH، حذف پایگاه، حذف سرویس، ارتقای بسته و rollback روی سیستم واقعی اجرا نشدند و نباید روی این میزبان توسعه اجرا شوند. تا عبور یک VM ایزوله، هیچ عملیات نصبی production-ready نیست.
