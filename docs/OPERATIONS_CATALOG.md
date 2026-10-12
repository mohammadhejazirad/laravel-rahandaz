# کاتالوگ عملیات نسخه ۱

منبع فهرست `infrastructure/operations/contracts/registry.json` است. schema کاتالوگ `1.0` و نسخه هر عملیات `1` است. ۱۲۱ عملیات ثبت شده‌اند: ۸۵ مورد helper، ۱۴ مورد project و ۲۲ مورد read. ۹۹ عملیات mutating هستند.

عملیات archive extraction هنوز به registry اضافه نشده است. `deploy.checkout` قرارداد Git موجود را جایگزین نمی‌کند و نباید برای ZIP با ورودی path آزاد reuse شود؛ قرارداد مستقل و آزمون sandbox/Zip Slip پیش از اتصال worker لازم است.

| گروه | تعداد | نکته ایمنی |
| --- | ---: | --- |
| account | 4 | حساب `rahandaz` بدون sudo و با `nologin`. کاربر پروژه فقط `rhproj_` به‌علاوه ۶ تا ۱۶ نویسه کوچک یا رقم |
| ssh | 5 | کلید خصوصی رد می‌شود. harden بدون recovery و اتصال دوم اعمال نمی‌شود. توقف sshd عملیات نیست |
| package | 4 | کاتالوگ تولید `untested` است و apt را صدا نمی‌زند |
| service | 10 | stop و disable و remove برای nginx، PostgreSQL، Redis، ssh و PHP-FPM و واحد `rahandaz*` رد می‌شود |
| nginx | 8 | فقط فایل دارای نشان `rahandaz-managed`. `..` و `load_module` رد می‌شوند. reload بعد از `nginx -t` |
| db | 52 | PostgreSQL، MySQL، MariaDB و MongoDB جدا هستند. نام محافظت‌شده از جمله `rahandaz` drop نمی‌شود |
| ssl | 6 | صدور و نصب تا آزمون VM برابر `CERTIFICATE_UNTESTED` است |
| deploy | 13 | privilege برابر project. checkout شبکه باز نمی‌کند. فعال‌سازی symlink اتمیک داخل sandbox آزمون شده است |
| firewall | 7 | پورت‌های 22 و 80 و 443 و 8080. flush ثبت نشده است |
| host | 8 | facts سازگاری را `untested` می‌گذارد. reboot اجرا نمی‌شود |
| monitor | 2 | همان مشاهده host، بدون تغییر |
| automation | 2 | اسکریپت سفارشی project است. نوشتن crontab سیستم `CRON_WRITE_FORBIDDEN` است |

بسته‌های اعلام‌شده در کاتالوگ Ubuntu: nginx، PostgreSQL، MySQL، MariaDB، MongoDB، Redis، PHP-FPM، Node.js، Python، FFmpeg، ClamAV، FreshClam، Docker اختیاری، UFW، Supervisor، OpenSSH، Fail2ban و Memcached. Docker پیش‌نیاز پنل نیست. PHP و Node و Python سیستم پیش‌فرض را عوض نمی‌کنند. برای MongoDB و PHP-FPM و Node و Python فیلد apt خالی است، چون مخزن و نسخه روی 22.04 و 24.04 و 26.04 نیازموده است.

MySQL و MariaDB هم‌زمان نصب نمی‌شوند و یکی نام مستعار دیگری نیست. هر سه Ubuntu در کاتالوگ `compatibility: untested` دارند. کدنام‌ها jammy و noble و resolute هستند.

اختلاف capability سرویس در خود اسکریپت است. reload فقط برای nginx و ssh و postgresql پذیرفته می‌شود. بقیه reloadها `INVALID_INPUT` می‌گیرند. status بدون sandbox فقط preview است.
