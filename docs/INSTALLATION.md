# نصب

نصب‌کننده production در این مخزن نیست. `scripts/install.sh` وجود ندارد و فرمانی برای `curl | bash` منتشر نشده است.

آنچه هست اسکریپت `account.create_rahandaz_user` است. روی میزبان واقعی، بدون sandbox تأییدشده، این اسکریپت `LIVE_MUTATION_BLOCKED` برمی‌گرداند و `useradd` را صدا نمی‌زند. داخل sandbox آزمون، حساب با shell برابر `/usr/sbin/nologin` و بدون گروه sudo ساخته می‌شود. گذرواژه تصادفی فقط در فایل root-only با حالت `0600` نوشته و سپس قفل می‌شود. این فایل به رابط فرستاده نمی‌شود. نگهداشتن آن بر عهده مالک است، ولی ورود تعاملی با آن گذرواژه فعال نیست و اتوماسیون از آن استفاده نمی‌کند.

نصب PHP پنل باید `ext-zip` و `fileinfo` را برای بررسی آرشیو پروژه داشته باشد و `rahandaz:doctor` هر دو را fail-closed بررسی می‌کند. Nginx `client_max_body_size` و PHP-FPM `upload_max_filesize`/`post_max_size` باید با `RAHANDAZ_ARCHIVE_MAX_UPLOAD_BYTES` هماهنگ شوند. مسیر `storage/app/project-archives` باید فقط برای هویت web/worker لازم قابل دسترس و از route عمومی جدا باشد.

بسته‌های پنل، از جمله Nginx و PostgreSQL و Redis، تا آزمون VM نصب موفق گزارش نمی‌شوند. Docker برای نصب پنل لازم نیست. آزمایشگاه `tests/infrastructure/ubuntu-lab/` همان موتور را داخل ایمیج رسمی Ubuntu اجرا می‌کند و نتیجه را `container-lab` می‌نامد، نه نصب production. در ۲۰۲۶-۱۰-۱۳ این اجرا روی 22.04 و 24.04 و 26.04 خروج ۰ داشت و همچنان گواهی نصب سرور نیست.

بعد از یک Release واقعی، نصب باید نسخه‌دار باشد، امضا و SHA-256 را پیش از تغییر بررسی کند، سایت موجود را بی‌اجازه عوض نکند، SSH جاری را نبندد، و helper را فقط به‌صورت سوکت محدود root نصب کند. آن Release هنوز ساخته نشده است.
