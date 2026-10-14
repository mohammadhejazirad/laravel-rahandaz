# نصب

نتیجه تازه در V1_TEST_REPORT: installer harness دوباره ۶۹ passed/۰ failed (updater زیرمجموعه ۲۱/۰)؛ full PHP suite اجرا شده، اما fresh install/health/login در VM همچنان requires_vm است. وجود صفحات درخواست و آموزش‌ها نصب عمومی را معتبر نمی‌کند؛ V1_RELEASE_READINESS مرجع gate است.

درخت `installer/` در مخزن است و Release امضاشدهٔ عمومی نیست. `scripts/install.sh` وجود ندارد. فرمان `curl | bash` و URL دانلود GitHub در README نیست، چون کلید `installer/trust/rahandaz-release.pub` عمداً غایب است و artifact تأییدشده منتشر نشده است.

طرح بدون تغییر سرور:

```bash
bash installer/bin/rahandaz-install --plan --state-dir /tmp/rahandaz-plan
```

اعمال فقط بعد از manifest، امضا و کلید عمومی از پیش مورد اعتماد است. جزئیات در [INSTALLER_ARCHITECTURE.md](INSTALLER_ARCHITECTURE.md)، [INSTALLER_SECURITY.md](INSTALLER_SECURITY.md)، [INSTALLER_RECOVERY.md](INSTALLER_RECOVERY.md)، [IRANIAN_MIRRORS.md](IRANIAN_MIRRORS.md)، [UBUNTU_COMPATIBILITY.md](UBUNTU_COMPATIBILITY.md) و [INSTALLER_TEST_MATRIX.md](INSTALLER_TEST_MATRIX.md) است.

در ۲۰۲۶-۱۰-۱۳ آزمون منطقی میزبان ۶۳ مورد را بدون شکست گذراند و طرح 22.04 با `PHP_BASELINE_UNAVAILABLE` متوقف شد. در ۲۰۲۶-۱۰-۱۴ همان harness پس از پین PHP 8.4 خروج ۰ داشت: ۶۹ مورد و ۰ شکست. طرح روی ایمیج‌های آزمایشگاه 22.04 و 24.04 و 26.04 هم خروج ۰ و `RESULT plan` داد و `php8.4` را از `/usr/bin/php8.4` نام برد. بسته‌ای نصب نشد، healthcheck و ورود و صف اجرا نشدند. این نتیجه نصب production نیست.

حساب خود نصب‌کننده، وقتی apply زنده شود، کاربر `rahandaz` را بدون فایل گذرواژهٔ سیستمی می‌سازد. آن apply روی میزبان توسعه اجرا نشده است. مسیر جداگانهٔ موتور عملیات هنوز این است: اسکریپت `account.create_rahandaz_user`. روی میزبان واقعی، بدون sandbox تأییدشده، این اسکریپت `LIVE_MUTATION_BLOCKED` برمی‌گرداند و `useradd` را صدا نمی‌زند. داخل sandbox آزمون، حساب با shell برابر `/usr/sbin/nologin` و بدون گروه sudo ساخته می‌شود. گذرواژه تصادفی فقط در فایل root-only با حالت `0600` نوشته و سپس قفل می‌شود. این فایل به رابط فرستاده نمی‌شود. نگهداشتن آن بر عهده مالک است، ولی ورود تعاملی با آن گذرواژه فعال نیست و اتوماسیون از آن استفاده نمی‌کند.

نصب PHP پنل باید `ext-zip` و `fileinfo` را برای بررسی آرشیو پروژه داشته باشد و `rahandaz:doctor` هر دو را fail-closed بررسی می‌کند. Nginx `client_max_body_size` و PHP-FPM `upload_max_filesize`/`post_max_size` باید با `RAHANDAZ_ARCHIVE_MAX_UPLOAD_BYTES` هماهنگ شوند. مسیر `storage/app/project-archives` باید فقط برای هویت web/worker لازم قابل دسترس و از route عمومی جدا باشد.

بسته‌های پنل، از جمله Nginx و PostgreSQL و Redis، تا آزمون VM نصب موفق گزارش نمی‌شوند. Docker برای نصب پنل لازم نیست. آزمایشگاه `tests/infrastructure/ubuntu-lab/` موتور عملیات را داخل ایمیج رسمی Ubuntu اجرا می‌کند و نتیجه را `container-lab` می‌نامد، نه نصب production. در ۲۰۲۶-۱۰-۱۳ آن اجرا روی 22.04 و 24.04 و 26.04 خروج ۰ داشت و همچنان گواهی نصب سرور نیست. طرح نصب‌کننده در همان کانتینرها جدا است و در [UBUNTU_COMPATIBILITY.md](UBUNTU_COMPATIBILITY.md) ثبت شده است.

نصب نسخه‌دار باید امضا و SHA-256 را پیش از تغییر بررسی کند، سایت موجود را بی‌اجازه عوض نکند، SSH جاری را نبندد، و helper را فقط به‌صورت سوکت محدود root نصب کند. آن Release هنوز ساخته نشده است. سوکت helper همچنان نصب نیست.
