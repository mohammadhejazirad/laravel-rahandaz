# امنیت موتور عملیات

اولویت این برش: امنیت، سپس یکپارچگی داده، قابلیت بازیابی، اطمینان، درستی، کارایی، و نگهداری.

## مرز اعتماد

مرورگر و درخواست HTTP به bash نمی‌رسند. `OperationDispatcher` ورودی را بررسی می‌کند و سپس به‌خاطر دروازه بسته متوقف می‌شود. Process مستقیم فقط کلاس `LocalOperationProcess` است و runner job آن را صدا نمی‌زند.

ورودی این کلیدها را رد می‌کند: `password`، `secret`، `token`، `private_key`، `authorization`، `credential`. متن `PRIVATE KEY` هم رد می‌شود. ماده حساس دیتابیس فقط از مسیر فایل `0600` داخل sandbox و با `--defaults-extra-file` عبور می‌کند، نه از argv. کلید خروجی JSON هم همان الگو را دارد.

## تغییر میزبان

apply زنده بدون sandbox `LIVE_MUTATION_BLOCKED` است. sandbox باید واقعی باشد، symlink نباشد، و زیر دایرکتوری موقت بماند. کاتالوگ جایگزین هم باید داخل همان sandbox باشد.

سرویس‌های محافظت‌شده از مسیر عادی stop، disable یا remove نمی‌شوند: nginx، PostgreSQL، Redis، ssh/sshd، واحدهای `php*-fpm` و `rahandaz*`. پایگاه‌های `postgres`، `template0`، `template1`، `mysql`، `information_schema`، `performance_schema`، `sys`، `admin`، `local`، `config` و نام `rahandaz` در هر موتور محافظت شده‌اند. drop و restore بدون `approval` و `backup_verified` اجرا نمی‌شوند.

SSH: توقف sshd وجود ندارد. harden پیش از snapshot و `sshd -t` به `recovery_verified` و `second_connection_verified` نیاز دارد. شکست آزمون، فایل قبلی را برمی‌گرداند.

Nginx فقط فایل نشان‌دار را می‌نویسد. `nginx -t` ناموفق فایل تازه‌ای را که قبلاً نبوده حذف می‌کند. فایروال دستور flush ندارد. فعال‌سازی deny به قاعده SSH، مسیر پنل و recovery نیاز دارد و این چک‌ها در اسکریپت enable هستند. خود نصب UFW هنوز `PACKAGE_UNTESTED` است.

Reboot حتی با همه پرچم‌های تأیید اجرا نمی‌شود. موفقیت آن بعد از اتصال دوباره و healthcheck باید جداگانه ثبت شود. این برش آن مشاهده بعد از reboot را ندارد.

## حساب و اسکریپت سفارشی

حساب عملیاتی عضو sudo و adm و docker نمی‌شود. shell آن nologin است و گذرواژه قفل می‌شود. اسکریپت سفارشی UID صفر را رد می‌کند، از helper عبور نمی‌کند، مسیر `/etc` و `/usr` و `..` را رد می‌کند، و در apply با `systemd-run` و سقف CPU و حافظه و TasksMax و RuntimeMaxSec و `NoNewPrivileges` درخواست می‌شود. این سقف روی mock آزمون شده است، نه روی systemd واقعی میزبان.

نوشتن cron سیستم ممنوع است. زمان‌بندی باید در PostgreSQL و Laravel Scheduler بماند. جدول تازه و writer برای `/etc/crontab` اضافه نشده است.

ایزوله‌سازی UID و systemd sandbox جداسازی کامل در برابر کد خصمانه نیست. نسخه اول فقط کاربر و پروژه مورد اعتماد را فرض می‌کند، همان‌طور که معماری گفته است.

## آنچه هنوز ساخته نشده

audit هر عملیات به جدول `audit_events` وصل نیست. daemon helper نصب نیست. آزمون نفوذ مسیر و آزمون VM انجام نشده است. محدودیت منابع build روی سرور ۲ گیگابایت benchmark نشده و مقدارهای ۵۰ درصد CPU و ۲۵۶ یا ۵۱۲ مگابایت فقط سقف اولیه داخل اسکریپت هستند.
